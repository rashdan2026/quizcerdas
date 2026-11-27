<?php

namespace App\Libraries;

use App\Models\LecturerModel;
use App\Models\StudentModel;
use DateTime;
use DateTimeZone;

/**
 * Reset password aman (v11.0).
 *
 * Prinsip:
 * - Meminta reset TIDAK mengubah password. Password hanya berubah setelah
 *   pemilik akun membuka token valid dan menyimpan password baru.
 * - Token acak 32 byte (hex 64), disimpan sebagai SHA-256 (bukan mentah),
 *   berlaku sesuai `token_ttl_minutes` saat diterbitkan, sekali pakai.
 * - Permintaan baru mencabut seluruh token aktif akun tersebut.
 * - Pembatasan per email: maks `max_requests` dalam periode bergulir 24 jam,
 *   dengan jeda progresif setelah permintaan ke-1/2/3 (5/10/15 menit default).
 * - Pembatasan per IP: maks `ip_max_per_hour` permintaan terkirim/jam.
 * - Semua keputusan dihitung dari timestamp server (Asia/Jakarta).
 * - Tidak pernah menyimpan/mencatat password atau token mentah.
 */
class PasswordResetService
{
    private const FALLBACK = [
        'max_requests'      => 4,
        'delay_2_minutes'   => 5,
        'delay_3_minutes'   => 10,
        'delay_4_minutes'   => 15,
        'token_ttl_minutes' => 15,
        'ip_max_per_hour'   => 20,
    ];

    private const WINDOW_HOURS = 24;

    private function tz(): DateTimeZone
    {
        return new DateTimeZone('Asia/Jakarta');
    }

    private function now(): DateTime
    {
        return new DateTime('now', $this->tz());
    }

    // ==================== Pengaturan ====================

    public function settings(): array
    {
        $row = db_connect()->table('password_reset_settings')->orderBy('id', 'ASC')->get()->getRowArray();

        if (! $row) {
            db_connect()->table('password_reset_settings')->insert(self::FALLBACK + [
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $row = self::FALLBACK;
        }

        return [
            'max_requests'      => (int) ($row['max_requests'] ?? self::FALLBACK['max_requests']),
            'delay_2_minutes'   => (int) ($row['delay_2_minutes'] ?? self::FALLBACK['delay_2_minutes']),
            'delay_3_minutes'   => (int) ($row['delay_3_minutes'] ?? self::FALLBACK['delay_3_minutes']),
            'delay_4_minutes'   => (int) ($row['delay_4_minutes'] ?? self::FALLBACK['delay_4_minutes']),
            'token_ttl_minutes' => (int) ($row['token_ttl_minutes'] ?? self::FALLBACK['token_ttl_minutes']),
            'ip_max_per_hour'   => (int) ($row['ip_max_per_hour'] ?? self::FALLBACK['ip_max_per_hour']),
        ];
    }

    /** Jeda (menit) setelah permintaan ke-N sebelum permintaan berikutnya. */
    private function delayAfter(int $seq, array $s): ?int
    {
        return match ($seq) {
            1 => $s['delay_2_minutes'],
            2 => $s['delay_3_minutes'],
            3 => $s['delay_4_minutes'],
            default => null, // setelah permintaan terakhir: tunggu window 24 jam
        };
    }

    // ==================== Status untuk UI ====================

    /**
     * @return array{allowed:bool,wait_seconds:int,remaining:int,window_reset_seconds:int}
     */
    public function state(string $email): array
    {
        $email = strtolower(trim($email));
        $s     = $this->settings();
        $now   = $this->now();
        $row   = db_connect()->table('password_reset_counters')->where('email', $email)->get()->getRowArray();

        $count = 0;
        $windowStart = null;
        $next = null;

        if ($row) {
            $windowStart = $row['window_started_at'] ? new DateTime($row['window_started_at'], $this->tz()) : null;
            if ($windowStart === null || $now->getTimestamp() - $windowStart->getTimestamp() >= self::WINDOW_HOURS * 3600) {
                $count = 0;
            } else {
                $count = (int) $row['sent_count'];
                $next  = $row['next_allowed_at'] ? new DateTime($row['next_allowed_at'], $this->tz()) : null;
            }
        }

        $wait = 0;
        if ($count > 0 && $next && $next > $now) {
            $wait = $next->getTimestamp() - $now->getTimestamp();
        }

        $windowReset = 0;
        if ($count >= $s['max_requests'] && $windowStart) {
            $windowReset = max(0, ($windowStart->getTimestamp() + self::WINDOW_HOURS * 3600) - $now->getTimestamp());
        }

        return [
            'allowed'              => $count < $s['max_requests'] && $wait === 0,
            'wait_seconds'         => $wait,
            'remaining'            => max(0, $s['max_requests'] - $count),
            'window_reset_seconds' => $windowReset,
        ];
    }

    // ==================== Permintaan reset ====================

    /**
     * Proses permintaan reset. Password TIDAK diubah di sini.
     *
     * @return array{status:string,wait_seconds:int,remaining:int,message:string}
     *   status: sent | cooldown | limit | ip_limit | invalid
     */
    public function request(string $email, string $ip): array
    {
        $email = strtolower(trim($email));
        $s     = $this->settings();
        $now   = $this->now();
        $nowStr= $now->format('Y-m-d H:i:s');
        $db    = db_connect();

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->result('invalid', 0, $s['max_requests'], 'Format email tidak valid.');
        }

        // Bersihkan data lama secara oportunistik. Semua batas waktu memakai
        // timestamp PHP (WIB) agar tidak bergantung timezone server MySQL.
        $nowTs = $now->getTimestamp();
        $db->query("DELETE FROM password_reset_tokens WHERE expires_at < ? LIMIT 200", [date('Y-m-d H:i:s', $nowTs - 7 * 86400)]);
        $db->query("DELETE FROM password_reset_requests WHERE created_at < ? LIMIT 200", [date('Y-m-d H:i:s', $nowTs - 30 * 86400)]);
        $db->query("DELETE FROM password_reset_counters WHERE window_started_at IS NOT NULL AND window_started_at < ? LIMIT 100", [date('Y-m-d H:i:s', $nowTs - 2 * 86400)]);

        // ===== Pembatasan IP (tidak mempercayai header proxy sembarangan:
        // memakai getIPAddress() yang menghormati Config\App::$proxyIPs).
        // Semua permintaan dihitung (termasuk email tidak terdaftar) agar
        // tidak bisa dipakai untuk enumerasi massal.
        $ipCount = (int) $db->query(
            "SELECT COUNT(*) AS n FROM password_reset_requests
             WHERE ip = ? AND created_at >= ?",
            [$ip, date('Y-m-d H:i:s', $nowTs - 3600)]
        )->getRow()->n;

        if ($ip !== null && $ip !== '' && $ipCount >= $s['ip_max_per_hour']) {
            $this->logRequest($db, $email, null, $ip, 0, 'ip_limit', null, $nowStr);

            return $this->result('ip_limit', 0, 0, 'Terlalu banyak permintaan reset password dari jaringan ini. Silakan coba lagi nanti.');
        }

        // Kunci per email (serialisasi request bersamaan — anti email ganda).
        $lockName = 'pwreset:' . sha1($email);
        $gotLock = (int) $db->query('SELECT GET_LOCK(?, 5) AS l', [$lockName])->getRow()->l;
        if ($gotLock !== 1) {
            return $this->result('cooldown', 5, 0, 'Permintaan sedang diproses. Silakan coba lagi sesaat lagi.');
        }

        $token = null;
        $account = null;
        $publicWait = 0;
        $remaining = 0;
        $status = 'sent';

        $db->transBegin();
        try {
            $row = $db->query('SELECT * FROM password_reset_counters WHERE email = ? FOR UPDATE', [$email])->getRowArray();

            $count = 0;
            $windowStart = null;
            $next = null;

            if ($row) {
                $windowStart = $row['window_started_at'] ? new DateTime($row['window_started_at'], $this->tz()) : null;
                $windowExpired = $windowStart === null || ($now->getTimestamp() - $windowStart->getTimestamp() >= self::WINDOW_HOURS * 3600);

                if (! $windowExpired) {
                    $count = (int) $row['sent_count'];
                    $next  = $row['next_allowed_at'] ? new DateTime($row['next_allowed_at'], $this->tz()) : null;
                } else {
                    $windowStart = null;
                }
            }

            // Batas 24 jam bergulir.
            if ($count >= $s['max_requests']) {
                $resetAt = $windowStart ? $windowStart->getTimestamp() + self::WINDOW_HOURS * 3600 : $now->getTimestamp();
                $wait = max(0, $resetAt - $now->getTimestamp());
                $status = 'limit';
                $publicWait = $wait;
                $this->logRequest($db, $email, null, $ip, $count, 'limit', null, $nowStr);

                $db->transCommit();
                $this->releaseLock($db, $lockName);

                return $this->result('limit', $wait, 0,
                    'Anda telah mencapai batas permintaan reset password. Silakan coba kembali setelah ' . ceil($wait / 60) . ' menit.');
            }

            // Cooldown progresif.
            if ($next && $next > $now) {
                $wait = $next->getTimestamp() - $now->getTimestamp();
                $status = 'cooldown';
                $publicWait = $wait;
                $this->logRequest($db, $email, null, $ip, $count, 'cooldown', null, $nowStr);

                $db->transCommit();
                $this->releaseLock($db, $lockName);

                return $this->result('cooldown', $wait, max(0, $s['max_requests'] - $count),
                    'Anda dapat meminta link reset password kembali dalam ' . floor($wait / 60) . ' menit ' . ($wait % 60) . ' detik.');
            }

            // ===== Diizinkan =====
            $count++;
            $newWindowStart = $windowStart?->format('Y-m-d H:i:s') ?? $nowStr;
            $delay = $this->delayAfter($count, $s);
            $nextAllowed = $delay !== null
                ? (clone $now)->modify('+' . $delay . ' minutes')->format('Y-m-d H:i:s')
                : null;
            $remaining = max(0, $s['max_requests'] - $count);
            $publicWait = $delay !== null ? $delay * 60 : 0;

            if ($row) {
                $db->table('password_reset_counters')->where('email', $email)->update([
                    'window_started_at' => $newWindowStart,
                    'sent_count'        => $count,
                    'last_sent_at'      => $nowStr,
                    'next_allowed_at'   => $nextAllowed,
                    'updated_at'        => $nowStr,
                ]);
            } else {
                $db->table('password_reset_counters')->insert([
                    'email'             => $email,
                    'window_started_at' => $newWindowStart,
                    'sent_count'        => $count,
                    'last_sent_at'      => $nowStr,
                    'next_allowed_at'   => $nextAllowed,
                    'updated_at'        => $nowStr,
                ]);
            }

            // Cari akun (student dahulu, lalu lecturer). Informasi ini TIDAK
            // pernah diungkap ke respons publik.
            $account = $this->findAccount($email);

            if ($account !== null) {
                // Permintaan baru = token lama akun ini dicabut.
                $db->table('password_reset_tokens')
                    ->where('role', $account['role'])
                    ->where('user_id', $account['user_id'])
                    ->where('used_at', null)
                    ->where('revoked_at', null)
                    ->update(['revoked_at' => $nowStr]);

                $raw = bin2hex(random_bytes(32));
                $db->table('password_reset_tokens')->insert([
                    'role'       => $account['role'],
                    'user_id'    => $account['user_id'],
                    'token_hash' => hash('sha256', $raw),
                    'ip'         => $ip,
                    'expires_at' => (clone $now)->modify('+' . $s['token_ttl_minutes'] . ' minutes')->format('Y-m-d H:i:s'),
                    'created_at' => $nowStr,
                ]);
                $token = $raw;
            }

            $this->logRequest($db, $email, $account['role'] ?? null, $ip, $count, $account ? 'sent' : 'not_found', null, $nowStr);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $this->releaseLock($db, $lockName);
            log_message('error', 'PasswordReset: request gagal - {class}', ['class' => $e::class]);

            return $this->result('invalid', 0, 0, 'Terjadi kesalahan saat memproses permintaan. Silakan coba lagi.');
        }

        $this->releaseLock($db, $lockName);

        // Kirim email di luar transaksi (tidak menahan lock DB saat I/O).
        if ($token !== null && $account !== null) {
            $ok = $this->sendResetEmail($account, $token, $s['token_ttl_minutes']);
            $db->table('password_reset_requests')
                ->where('email', $email)
                ->where('created_at', $nowStr)
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->update(['email_status' => $ok ? 'sent' : 'failed']);
        }

        return $this->result('sent', $publicWait, $remaining, '');
    }

    // ==================== Validasi & penyelesaian token ====================

    public function validateToken(string $raw): array
    {
        $raw = trim($raw);

        if (! preg_match('/^[a-f0-9]{64}$/i', $raw)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $now = $this->now()->format('Y-m-d H:i:s');
        $row = db_connect()->table('password_reset_tokens')
            ->where('token_hash', hash('sha256', strtolower($raw)))
            ->get()
            ->getRowArray();

        if (! $row) {
            return ['ok' => false, 'reason' => 'invalid'];
        }
        if ($row['used_at'] !== null) {
            return ['ok' => false, 'reason' => 'used'];
        }
        if ($row['revoked_at'] !== null) {
            return ['ok' => false, 'reason' => 'revoked'];
        }
        if (strtotime($row['expires_at']) <= strtotime($now)) {
            return ['ok' => false, 'reason' => 'expired', 'expired_at' => $row['expires_at']];
        }

        $account = $this->loadAccount($row['role'], $row['user_id']);
        if ($account === null) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        return [
            'ok'          => true,
            'token'       => $row,
            'account'     => $account,
            'seconds_left'=> max(0, strtotime($row['expires_at']) - strtotime($now)),
        ];
    }

    /**
     * Simpan password baru (transaksi + row lock).
     *
     * @return array{ok:bool,reason?:string,account?:array}
     */
    public function completeReset(string $raw, string $newPassword, string $ip): array
    {
        $raw = trim($raw);
        if (! preg_match('/^[a-f0-9]{64}$/i', $raw)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $db  = db_connect();
        $now = $this->now()->format('Y-m-d H:i:s');
        $hash = hash('sha256', strtolower($raw));

        $db->transBegin();
        try {
            $row = $db->query('SELECT * FROM password_reset_tokens WHERE token_hash = ? FOR UPDATE', [$hash])->getRowArray();

            if (! $row) {
                $db->transRollback();
                return ['ok' => false, 'reason' => 'invalid'];
            }
            if ($row['used_at'] !== null) {
                $db->transRollback();
                return ['ok' => false, 'reason' => 'used'];
            }
            if ($row['revoked_at'] !== null) {
                $db->transRollback();
                return ['ok' => false, 'reason' => 'revoked'];
            }
            if (strtotime($row['expires_at']) <= strtotime($now)) {
                $db->transRollback();
                return ['ok' => false, 'reason' => 'expired'];
            }

            $account = $this->loadAccount($row['role'], $row['user_id']);
            if ($account === null) {
                $db->transRollback();
                return ['ok' => false, 'reason' => 'invalid'];
            }

            $hashPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            if ($row['role'] === 'student') {
                $db->table('students')->where('email', $account['user_id'])
                    ->update(['password' => $hashPassword, 'updated_at' => $now]);
            } else {
                $db->table('lecturers')->where('id', (int) $account['user_id'])
                    ->update(['password' => $hashPassword, 'updated_at' => $now]);
            }

            // Token sekali pakai.
            $db->table('password_reset_tokens')->where('id', (int) $row['id'])
                ->update(['used_at' => $now]);

            // Cabut token lain akun ini.
            $db->table('password_reset_tokens')
                ->where('role', $row['role'])
                ->where('user_id', $row['user_id'])
                ->where('used_at', null)
                ->where('revoked_at', null)
                ->update(['revoked_at' => $now]);

            // Audit (tanpa password/token).
            $db->table('password_reset_audit')->insert([
                'event'      => 'reset_completed',
                'role'       => $row['role'],
                'user_ref'   => $row['user_id'],
                'email'      => $account['email'],
                'ip'         => $ip,
                'status'     => 'success',
                'detail'     => json_encode(['source' => 'forgot_password']),
                'created_at' => $now,
            ]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'PasswordReset: complete gagal - {class}', ['class' => $e::class]);

            return ['ok' => false, 'reason' => 'error'];
        }

        // Notifikasi email (best effort; kegagalan tidak membatalkan reset).
        $this->sendChangedEmail($account);

        return ['ok' => true, 'account' => $account];
    }

    // ==================== Admin ====================

    public function updateSettings(array $data, int $adminId, string $adminEmail): bool
    {
        $old = $this->settings();

        db_connect()->table('password_reset_settings')->update([
            'max_requests'      => $data['max_requests'],
            'delay_2_minutes'   => $data['delay_2_minutes'],
            'delay_3_minutes'   => $data['delay_3_minutes'],
            'delay_4_minutes'   => $data['delay_4_minutes'],
            'token_ttl_minutes' => $data['token_ttl_minutes'],
            'ip_max_per_hour'   => $data['ip_max_per_hour'],
            'updated_by'        => $adminId,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        db_connect()->table('password_reset_audit')->insert([
            'event'      => 'admin_settings',
            'role'       => 'admin',
            'user_ref'   => (string) $adminId,
            'email'      => $adminEmail,
            'status'     => 'updated',
            'detail'     => json_encode(['old' => $old, 'new' => $data]),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    // ==================== Internal ====================

    private function findAccount(string $email): ?array
    {
        $student = (new StudentModel())->find($email);
        if ($student) {
            return ['role' => 'student', 'user_id' => $student['email'], 'email' => $student['email'], 'name' => $student['nama']];
        }

        $lecturer = (new LecturerModel())->where('email', $email)->first();
        if ($lecturer) {
            return ['role' => 'lecturer', 'user_id' => (string) $lecturer['id'], 'email' => $lecturer['email'], 'name' => $lecturer['nama']];
        }

        return null;
    }

    private function loadAccount(string $role, string $userId): ?array
    {
        if ($role === 'student') {
            $row = (new StudentModel())->find($userId);
            return $row ? ['role' => 'student', 'user_id' => $row['email'], 'email' => $row['email'], 'name' => $row['nama']] : null;
        }

        $row = (new LecturerModel())->find((int) $userId);
        return $row ? ['role' => 'lecturer', 'user_id' => (string) $row['id'], 'email' => $row['email'], 'name' => $row['nama']] : null;
    }

    private function logRequest($db, string $email, ?string $role, string $ip, int $seq, string $result, ?string $mailStatus, string $now): void
    {
        $db->table('password_reset_requests')->insert([
            'email'        => $email,
            'role'         => $role,
            'ip'           => $ip,
            'sequence_no'  => $seq,
            'result'       => $result,
            'email_status' => $mailStatus,
            'created_at'   => $now,
        ]);
    }

    private function releaseLock($db, string $lockName): void
    {
        $db->query('SELECT RELEASE_LOCK(?) AS r', [$lockName]);
    }

    private function result(string $status, int $wait, int $remaining, string $message): array
    {
        return ['status' => $status, 'wait_seconds' => $wait, 'remaining' => $remaining, 'message' => $message];
    }

    private function resetUrl(string $rawToken): string
    {
        return rtrim(base_url(), '/') . '/reset-password/' . $rawToken;
    }

    private function sendResetEmail(array $account, string $rawToken, int $ttl): bool
    {
        $app   = app_name();
        $link  = $this->resetUrl($rawToken);
        $name  = $account['name'];
        $subject = 'Permintaan Pengaturan Ulang Password – ' . $app;

        $text = "Halo {$name},\n\n"
              . "Kami menerima permintaan untuk mengatur ulang password akun {$app} Anda.\n\n"
              . "Buka tautan berikut untuk membuat password baru:\n{$link}\n\n"
              . "Tautan ini hanya berlaku selama {$ttl} menit dan hanya dapat digunakan satu kali.\n\n"
              . "Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini. Password akun Anda tidak akan berubah selama Anda tidak menyelesaikan proses pengaturan ulang password.\n\n"
              . "Salam,\nTim {$app}";

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:24px">'
              . '<h2 style="color:#1D4ED8;margin:0 0 16px">' . esc($app) . '</h2>'
              . '<p>Halo <strong>' . esc($name) . '</strong>,</p>'
              . '<p>Kami menerima permintaan untuk mengatur ulang password akun ' . esc($app) . ' Anda.</p>'
              . '<p style="margin:24px 0"><a href="' . esc($link, 'attr') . '" style="background:#2563EB;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;display:inline-block">Atur Ulang Password</a></p>'
              . '<p style="font-size:13px;color:#475569">Tautan ini hanya berlaku selama <strong>' . $ttl . ' menit</strong> dan hanya dapat digunakan satu kali.</p>'
              . '<p style="font-size:13px;color:#475569">Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini. Password akun Anda tidak akan berubah selama Anda tidak menyelesaikan proses pengaturan ulang password.</p>'
              . '<p style="font-size:12px;color:#94A3B8">Salam,<br>Tim ' . esc($app) . '</p></div>';

        $res = (new MailService())->send($account['email'], $subject, $html, $text);
        if (! $res['ok']) {
            log_message('error', 'PasswordReset: email reset gagal dikirim ({provider}).', ['provider' => $res['provider'] ?? '-']);
        }

        return (bool) $res['ok'];
    }

    private function sendChangedEmail(array $account): void
    {
        $app = app_name();
        $subject = 'Password Akun ' . $app . ' Berhasil Diubah';
        $text = "Halo {$account['name']},\n\n"
              . "Password akun {$app} Anda telah berhasil diperbarui melalui fitur lupa password.\n\n"
              . "Jika Anda tidak melakukan perubahan ini, segera hubungi administrator {$app}.\n\n"
              . "Salam,\nTim {$app}";
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;padding:24px">'
              . '<h2 style="color:#1D4ED8;margin:0 0 16px">' . esc($app) . '</h2>'
              . '<p>Halo <strong>' . esc($account['name']) . '</strong>,</p>'
              . '<p>Password akun ' . esc($app) . ' Anda telah berhasil diperbarui.</p>'
              . '<p>Jika Anda tidak melakukan perubahan ini, segera hubungi administrator ' . esc($app) . '.</p>'
              . '<p style="font-size:12px;color:#94A3B8">Salam,<br>Tim ' . esc($app) . '</p></div>';

        (new MailService())->send($account['email'], $subject, $html, $text);
    }
}
