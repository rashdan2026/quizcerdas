<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LecturerLimits;
use App\Models\LecturerLimitOverrideModel;
use App\Models\LecturerLimitSettingsModel;
use App\Models\LimitationAuditLogModel;

/**
 * Pengaturan Limitasi Dosen (v10.0).
 * Hanya diakses admin (route dilindungi authguard:admin).
 * Semua perubahan tercatat di limitation_audit_logs.
 */
class Limitations extends BaseController
{
    private LecturerLimits $limits;

    /** Rentang nilai yang diizinkan [min, max]. */
    private const RANGES = [
        'subjects' => [1, 100],
        'meetings' => [1, 200],
        'ai'       => [0, 5000],
        'quizzes'  => [1, 100],
    ];

    public function __construct()
    {
        $this->limits = new LecturerLimits();
    }

    public function index()
    {
        $q      = trim((string) ($this->request->getGet('q') ?? ''));
        $status = (string) ($this->request->getGet('status') ?? 'all');
        if (! in_array($status, ['all', 'default', 'custom'], true)) {
            $status = 'all';
        }

        $db = db_connect();
        $weekStart = $this->limits->weekStart();

        $sql = "SELECT l.id, l.nama, l.email,
                l.limit_max_subjects, l.limit_max_meetings, l.limit_max_ai_questions, l.limit_max_quizzes,
                o.id AS override_id, o.max_subjects AS o_subjects, o.max_meetings AS o_meetings,
                o.max_ai_questions AS o_ai, o.max_quizzes AS o_quizzes,
                s.max_subjects AS d_subjects, s.max_meetings AS d_meetings,
                s.max_ai_questions AS d_ai, s.max_quizzes AS d_quizzes,
                (SELECT COUNT(*) FROM subjects sub WHERE sub.dosen_id = l.id) AS used_subjects,
                (SELECT COUNT(*) FROM quiz_quizzes qz WHERE qz.lecturer_id = l.id) AS used_quizzes,
                (SELECT COUNT(*) FROM quiz_questions qq JOIN quiz_quizzes z2 ON z2.id = qq.quiz_id
                 WHERE z2.lecturer_id = l.id AND qq.source_type = 'ai_pdf' AND qq.created_at >= ?) AS used_ai_actual,
                (SELECT COALESCE(SUM(b.requested_count), 0) FROM quiz_question_batches b
                 WHERE b.lecturer_id = l.id AND b.created_at >= ? AND b.status IN ('queued','quarantine','processing')) AS used_ai_reserved
            FROM lecturers l
            CROSS JOIN lecturer_limit_settings s
            LEFT JOIN lecturer_limit_overrides o ON o.lecturer_id = l.id
            WHERE 1 = 1";

        $params = [$weekStart, $weekStart];

        if ($q !== '') {
            $sql .= ' AND (l.nama LIKE ? OR l.email LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if ($status === 'custom') {
            $sql .= ' AND o.id IS NOT NULL';
        } elseif ($status === 'default') {
            $sql .= ' AND o.id IS NULL';
        }

        $sql .= ' ORDER BY l.nama ASC LIMIT 200';
        $rows = $db->query($sql, $params)->getResultArray();

        foreach ($rows as &$r) {
            $r['eff_subjects'] = (int) ($r['o_subjects'] ?? $r['limit_max_subjects'] ?? $r['d_subjects']);
            $r['eff_meetings'] = (int) ($r['o_meetings'] ?? $r['limit_max_meetings'] ?? $r['d_meetings']);
            $r['eff_ai']       = (int) ($r['o_ai'] ?? $r['limit_max_ai_questions'] ?? $r['d_ai']);
            $r['eff_quizzes']  = (int) ($r['o_quizzes'] ?? $r['limit_max_quizzes'] ?? $r['d_quizzes']);
            $r['used_ai']      = (int) $r['used_ai_actual'] + (int) $r['used_ai_reserved'];
            $r['is_custom']    = $r['override_id'] !== null;

            // Ringkasan pertemuan per mata kuliah milik dosen ini.
            $subs = $db->query(
                'SELECT sub.kode_mk, (SELECT COUNT(*) FROM meetings m WHERE m.subject_id = sub.id) AS n
                 FROM subjects sub WHERE sub.dosen_id = ? ORDER BY sub.kode_mk',
                [$r['id']]
            )->getResultArray();
            $meet = [];
            foreach ($subs as $s) {
                $meet[] = $s['kode_mk'] . ': ' . (int) $s['n'] . '/' . $r['eff_meetings'];
            }
            $r['meetings_summary'] = $subs ? implode(', ', $meet) : '—';
        }
        unset($r);

        return view('admin/limitations/index', [
            'pageTitle'    => 'Pengaturan Limitasi Dosen',
            'pageSubtitle' => 'Batas penggunaan resource per akun dosen',
            'settings'     => $this->limits->defaults(),
            'rows'         => $rows,
            'q'            => $q,
            'status'       => $status,
            'auditLogs'    => (new LimitationAuditLogModel())->recent(20),
            'weekStart'    => $weekStart,
        ]);
    }

    public function saveDefault()
    {
        $post = $this->validated();
        if ($post === null) {
            return redirect()->to('/admin/limitations')->with('error', 'Nilai limitasi tidak valid.');
        }

        $model    = new LecturerLimitSettingsModel();
        $existing = $model->singleton();
        $old      = $existing ? [
            'subjects' => (int) $existing['max_subjects'], 'meetings' => (int) $existing['max_meetings'],
            'ai' => (int) $existing['max_ai_questions'], 'quizzes' => (int) $existing['max_quizzes'],
        ] : null;

        $model->update($existing['id'] ?? 1, [
            'max_subjects'     => $post['subjects'],
            'max_meetings'     => $post['meetings'],
            'max_ai_questions' => $post['ai'],
            'max_quizzes'      => $post['quizzes'],
            'updated_by'       => (int) session('user_id'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->audit('update_default', null, $old, $post);

        return redirect()->to('/admin/limitations')
            ->with('success', 'Limitasi default diperbarui. Dosen yang sudah terdaftar tetap menggunakan limitasi akunnya masing-masing.');
    }

    public function saveOverride()
    {
        $lecturerId = (int) $this->request->getPost('lecturer_id');
        $lecturer   = (new \App\Models\LecturerModel())->find($lecturerId);

        if (! $lecturer) {
            return redirect()->to('/admin/limitations')->with('error', 'Dosen tidak ditemukan.');
        }

        $post = $this->validated();
        if ($post === null) {
            return redirect()->to('/admin/limitations')->with('error', 'Nilai limitasi khusus tidak valid.');
        }

        $model = new LecturerLimitOverrideModel();
        $old   = $model->forLecturer($lecturerId);
        $oldVal = $old ? [
            'subjects' => (int) $old['max_subjects'], 'meetings' => (int) $old['max_meetings'],
            'ai' => (int) $old['max_ai_questions'], 'quizzes' => (int) $old['max_quizzes'],
        ] : null;

        if ($old) {
            $model->update($old['id'], [
                'max_subjects'     => $post['subjects'],
                'max_meetings'     => $post['meetings'],
                'max_ai_questions' => $post['ai'],
                'max_quizzes'      => $post['quizzes'],
                'updated_by'       => (int) session('user_id'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        } else {
            $model->insert([
                'lecturer_id'      => $lecturerId,
                'max_subjects'     => $post['subjects'],
                'max_meetings'     => $post['meetings'],
                'max_ai_questions' => $post['ai'],
                'max_quizzes'      => $post['quizzes'],
                'updated_by'       => (int) session('user_id'),
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        }

        $this->audit('set_override', $lecturerId, $oldVal, $post);

        return redirect()->to('/admin/limitations')
            ->with('success', 'Limitasi khusus untuk ' . $lecturer['nama'] . ' disimpan.');
    }

    public function removeOverride($overrideId)
    {
        $overrideId = (int) $overrideId;
        $model      = new LecturerLimitOverrideModel();
        $old        = $model->find($overrideId);

        if (! $old) {
            return redirect()->to('/admin/limitations')->with('error', 'Limitasi khusus tidak ditemukan.');
        }

        $lecturer = (new \App\Models\LecturerModel())->find((int) $old['lecturer_id']);

        $model->delete($old['id']);
        $this->audit('remove_override', (int) $old['lecturer_id'], [
            'subjects' => (int) $old['max_subjects'], 'meetings' => (int) $old['max_meetings'],
            'ai' => (int) $old['max_ai_questions'], 'quizzes' => (int) $old['max_quizzes'],
        ], null);

        return redirect()->to('/admin/limitations')
            ->with('success', 'Limitasi khusus dihapus. ' . ($lecturer['nama'] ?? 'Dosen') . ' kembali menggunakan limitasi akun/default.');
    }

    // ==================== Helpers ====================

    private function validated(): ?array
    {
        $out = [];
        foreach (['subjects', 'meetings', 'ai', 'quizzes'] as $key) {
            $val = (int) $this->request->getPost('max_' . $key);
            [$min, $max] = self::RANGES[$key];
            if ($val < $min || $val > $max) {
                return null;
            }
            $out[$key] = $val;
        }

        return $out;
    }

    private function audit(string $action, ?int $lecturerId, ?array $old, ?array $new): void
    {
        (new LimitationAuditLogModel())->insert([
            'admin_id'    => session('role') === 'admin' ? (int) session('user_id') : null,
            'admin_email' => $this->adminEmail(),
            'action'      => $action,
            'lecturer_id' => $lecturerId,
            'old_value'   => $old ? json_encode($old) : null,
            'new_value'   => $new ? json_encode($new) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function adminEmail(): ?string
    {
        $admin = (new \App\Models\AdminModel())->find(session('user_id'));

        return $admin['email'] ?? null;
    }
}
