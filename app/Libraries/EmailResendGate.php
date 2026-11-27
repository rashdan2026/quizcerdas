<?php

namespace App\Libraries;

use App\Models\EmailResendCounterModel;
use App\Models\EmailResendLogModel;

/**
 * Pembatasan kirim-ulang (resend) email — SATU mekanisme untuk seluruh halaman.
 *
 * Aturan:
 * - Maksimal 4 resend per alamat email per hari kalender (Asia/Jakarta).
 * - Pengiriman email AWAL tidak dihitung sebagai resend.
 * - Jeda sebelum resend berikutnya:
 *     resend ke-1 → tanpa jeda tambahan
 *     resend ke-2 → menunggu 5 menit setelah resend ke-1
 *     resend ke-3 → menunggu 10 menit setelah resend ke-2
 *     resend ke-4 → menunggu 15 menit setelah resend ke-3
 * - Counter BERSAMA untuk semua jenis email (OTP/verifikasi) pada alamat yang
 *   sama — berpindah halaman/session tidak mereset batas.
 * - Slot direservasi secara atomik (transaction + row lock) sehingga
 *   double-click / request bersamaan tidak mengirim email ganda.
 * - Slot tetap terpakai meski provider email gagal setelah dikirim (anti-spam);
 *   permintaan yang ditolak validasi internal tidak memakai slot.
 * - Status tiap permintaan dicatat di email_resend_logs (TANPA isi OTP).
 * - Countdown UI memakai sisa detik dari server (next_allowed_at).
 */
class EmailResendGate
{
    public const DAILY_MAX = 4;

    /** Jeda (menit) setelah resend ke-N sebelum resend berikutnya diizinkan. */
    private const DELAY_MINUTES = [1 => 5, 2 => 10, 3 => 15];

    private EmailResendCounterModel $counterModel;
    private EmailResendLogModel $logModel;

    public function __construct()
    {
        $this->counterModel = new EmailResendCounterModel();
        $this->logModel     = new EmailResendLogModel();
    }

    private function tz(): \DateTimeZone
    {
        return new \DateTimeZone('Asia/Jakarta');
    }

    private function now(): \DateTime
    {
        return new \DateTime('now', $this->tz());
    }

    /**
     * Status saat ini untuk UI (countdown & kuota harian).
     *
     * @return array{allowed:bool, wait_seconds:int, remaining_today:int, exhausted:bool}
     */
    public function state(string $email): array
    {
        $row  = $this->counterModel->find(strtolower(trim($email)));
        $tz   = $this->tz();
        $now  = new \DateTime('now', $tz);
        $today = $now->format('Y-m-d');

        $count = 0;
        $nextAllowed = null;

        if ($row && $row['counter_date'] === $today) {
            $count       = (int) $row['resend_count'];
            $nextAllowed = $row['next_allowed_at'] ? new \DateTime($row['next_allowed_at'], $tz) : null;
        }

        $exhausted = $count >= self::DAILY_MAX;
        $wait = 0;
        if (! $exhausted && $nextAllowed && $nextAllowed > $now) {
            $wait = $nextAllowed->getTimestamp() - $now->getTimestamp();
        }

        return [
            'allowed'         => ! $exhausted && $wait === 0,
            'wait_seconds'    => $wait,
            'remaining_today' => max(0, self::DAILY_MAX - $count),
            'exhausted'       => $exhausted,
        ];
    }

    /**
     * Reservasi slot resend (panggil SEBELUM mengirim email).
     * Mengembalikan ['ok'=>true,'seq'=>int,'wait_seconds'=>int] bila diizinkan,
     * atau ['ok'=>false,'message'=>string] bila ditolak.
     *
     * @param string $kind identitas proses, mis. 'mahasiswa_verify' | 'dosen_verify'
     */
    public function reserve(string $email, string $kind): array
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Alamat email tidak valid.'];
        }

        $db = db_connect();
        $tz = $this->tz();
        $now = new \DateTime('now', $tz);
        $today = $now->format('Y-m-d');

        $db->transBegin();
        try {
            $row = $this->counterModel->forUpdate($email); // row lock

            $count = 0;
            $nextAllowed = null;

            if ($row) {
                if ($row['counter_date'] !== $today) {
                    // Hari baru: counter direset.
                    $count = 0;
                } else {
                    $count = (int) $row['resend_count'];
                    $nextAllowed = $row['next_allowed_at'] ? new \DateTime($row['next_allowed_at'], $tz) : null;
                }
            }

            // Batas harian.
            if ($count >= self::DAILY_MAX) {
                $db->transRollback();

                return ['ok' => false, 'message' => 'Anda telah mencapai batas pengiriman ulang email hari ini. Silakan coba kembali besok.'];
            }

            // Jeda sebelum resend ini (berdasarkan pengiriman sebelumnya).
            if ($nextAllowed !== null && $nextAllowed > $now) {
                $wait = $now->getTimestamp() - $nextAllowed->getTimestamp();
                $wait = abs($wait);

                $db->transRollback();

                return ['ok' => false, 'message' => 'Tunggu ' . $this->formatWait($wait) . ' sebelum mengirim ulang email.', 'wait_seconds' => $wait];
            }

            $newCount = $count + 1;

            // Jeda SETELAH resend ini (untuk resend berikutnya).
            $nextDelay = self::DELAY_MINUTES[$newCount] ?? null;
            $nextAllowedAt = null;
            if ($nextDelay !== null) {
                $next = clone $now;
                $next->modify('+' . $nextDelay . ' minutes');
                $nextAllowedAt = $next->format('Y-m-d H:i:s');
            }

            $data = [
                'email'           => $email,
                'counter_date'    => $today,
                'resend_count'    => $newCount,
                'next_allowed_at' => $nextAllowedAt,
                'last_sent_at'    => $now->format('Y-m-d H:i:s'),
                'updated_at'      => $now->format('Y-m-d H:i:s'),
            ];

            if ($row) {
                $this->counterModel->update($email, $data);
            } else {
                $this->counterModel->insert($data);
            }

            // Catat log (tanpa isi OTP/kode).
            $this->logModel->insert([
                'email'      => $email,
                'kind'       => $kind,
                'resend_seq' => $newCount,
                'status'     => 'dispatched',
                'created_at' => $now->format('Y-m-d H:i:s'),
            ]);
            $logId = (int) $this->logModel->getInsertID();

            $db->transCommit();

            return [
                'ok'           => true,
                'seq'          => $newCount,
                'log_id'       => $logId,
                // Countdown untuk tombol berikutnya.
                'wait_seconds' => ($nextDelay ?? 0) * 60,
                'exhausted'    => $newCount >= self::DAILY_MAX,
            ];
        } catch (\Throwable $e) {
            if ($db->transStatus() === false || $db->transLevel() !== 0) {
                $db->transRollback();
            }
            log_message('error', 'EmailResendGate: reservasi gagal - {class}', ['class' => $e::class]);

            return ['ok' => false, 'message' => 'Terjadi kesalahan saat memproses permintaan kirim ulang. Silakan coba lagi.'];
        }
    }

    /**
     * Catat hasil pengiriman setelah MailService dijalankan.
     * Slot TIDAK dikembalikan bila gagal (anti-spam); status dicatat.
     */
    public function finalize(string $email, int $logId, bool $sent, string $errorNote = ''): void
    {
        $this->logModel->update($logId, [
            'status'     => $sent ? 'sent' : 'failed',
            'error_note' => mb_substr(trim(str_replace(["\r", "\n"], ' ', $errorNote)), 0, 255) ?: null,
        ]);
    }

    private function formatWait(int $seconds): string
    {
        if ($seconds >= 60) {
            $m = (int) floor($seconds / 60);
            $s = $seconds % 60;

            return $m . ' menit' . ($s > 0 ? ' ' . $s . ' detik' : '');
        }

        return $seconds . ' detik';
    }
}
