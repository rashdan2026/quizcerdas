<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Libraries\Quiz\QuizScoringService;
use App\Models\Quiz\QuizAccessCodeModel;
use App\Models\Quiz\QuizAttemptModel;
use App\Models\Quiz\QuizBatchModel;
use App\Models\Quiz\QuizModel;
use App\Models\Quiz\QuizQuestionModel;
use App\Models\SubjectModel;

class Quiz extends BaseController
{
    private QuizModel $model;

    public function __construct()
    {
        $this->model = new QuizModel();
    }

    private function lid(): int
    {
        return (int) session('user_id');
    }

    public function index()
    {
        $quizzes = $this->model->forLecturer($this->lid());

        // Jumlah soal aktif untuk semua kuis dalam SATU query (bukan per kuis).
        $quizIds  = array_map(static fn ($q) => (int) $q['id'], $quizzes);
        $actives  = (new QuizAccessCodeModel())->activeForQuizzes($quizIds);
        $questionCounts = (new QuizQuestionModel())->activeCountsForQuizzes($quizIds);

        foreach ($quizzes as &$q) {
            $q['question_count'] = $questionCounts[(int) $q['id']] ?? 0;
            // Kode aktif dari tabel histori kode (bukan kode lama yang sudah dinonaktifkan).
            $q['access_code'] = $actives[(int) $q['id']]['code'] ?? $q['access_code'];
        }
        unset($q);

        return view('lecturer/quiz/index', ['quizzes' => $quizzes]);
    }

    public function create()
    {
        if ($this->request->getMethod() !== 'POST') {
            // Hanya mata kuliah aktif milik dosen yang ditampilkan di pilihan.
            $subjects = (new SubjectModel())
                ->where('dosen_id', $this->lid())
                ->where('is_active', 1)
                ->orderBy('nama_mk', 'ASC')
                ->findAll();

            return view('lecturer/quiz/create_form', ['subjects' => $subjects]);
        }

        $subjectId = (int) $this->request->getPost('subject_id');
        $title     = trim((string) $this->request->getPost('title'));
        $desc      = trim((string) $this->request->getPost('description'));

        // Ownership subject dicek di server + hanya mata kuliah aktif
        $subject = (new SubjectModel())
            ->where('id', $subjectId)
            ->where('dosen_id', $this->lid())
            ->where('is_active', 1)
            ->first();
        if ($subject === null) {
            return redirect()->back()->withInput()->with('error', 'Mata kuliah tidak ditemukan, nonaktif, atau bukan milik Anda.');
        }

        if ($title === '' || mb_strlen($title) > 255) {
            return redirect()->back()->withInput()->with('error', 'Judul kuis wajib diisi (maks 255 karakter).');
        }

        $correctPoints = (int) ($this->request->getPost('correct_points') ?: 5);
        $wrongPenalty  = (int) ($this->request->getPost('wrong_penalty') ?? 1);
        if ($correctPoints < 1 || $correctPoints > 5) {
            $correctPoints = 5;
        }
        if ($wrongPenalty < 0 || $wrongPenalty > 5) {
            $wrongPenalty = 1;
        }

        // Jadwal diatur sejak pembuatan kuis. Waktu berakhir dihitung otomatis:
        // ends_at = starts_at + duration_minutes (tidak diinput dosen).
        $schedule = $this->parseSchedule();
        if ($schedule === null) {
            return redirect()->back()->withInput()->with('error', 'Waktu mulai wajib diisi dan durasi harus 1-1440 menit.');
        }

        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['draft', 'active'], true)) {
            $status = 'draft';
        }

        $id = $this->model->insert([
            'lecturer_id'    => $this->lid(),
            'subject_id'     => $subjectId,
            'title'          => $title,
            'description'    => $desc !== '' ? $desc : null,
            'status'         => $status,
            'access_code'    => $this->model->generateAccessCode(),
            'correct_points' => $correctPoints,
            'wrong_penalty'  => $wrongPenalty,
            'starts_at'        => $schedule['starts_at'],
            'ends_at'          => $schedule['ends_at'],
            'duration_minutes' => $schedule['duration'],
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ], true);

        // Terbitkan kode awal ke tabel histori kode quiz_access_codes.
        (new QuizAccessCodeModel())->insert([
            'quiz_id'        => $id,
            'code'           => $this->model->find($id)['access_code'],
            'is_active'      => 1,
            'created_by'     => $this->lid(),
            'created_at'     => date('Y-m-d H:i:s'),
            'deactivated_at' => null,
        ]);

        return redirect()->to('/lecturer/quiz/' . $id)
            ->with('success', 'Kuis berhasil dibuat dengan kode peserta: ' . $this->model->find($id)['access_code'] . '. Silakan tambahkan soal.');
    }

    /**
     * Parse jadwal dari input dosen: starts_at (wajib) + durasi (default 60).
     * ends_at dihitung otomatis = starts_at + durasi.
     */
    private function parseSchedule(): ?array
    {
        $startsAt = (string) $this->request->getPost('starts_at');
        $duration = (int) ($this->request->getPost('duration_minutes') ?: 60);

        $ts1 = strtotime($startsAt);
        if ($ts1 === false || $duration < 1 || $duration > 1440) {
            return null;
        }

        return [
            'starts_at' => date('Y-m-d H:i:s', $ts1),
            'ends_at'   => date('Y-m-d H:i:s', $ts1 + $duration * 60),
            'duration'  => $duration,
        ];
    }

    private function ownedOrFail(int $id): ?array
    {
        $quiz = $this->model->findOwned($id, $this->lid());
        if ($quiz === null) {
            throw new \CodeIgniter\HTTP\Exceptions\PageNotFoundException();
        }

        return $quiz;
    }

    public function show(int $id)
    {
        $quiz = $this->ownedOrFail($id);

        $questionModel = new QuizQuestionModel();
        $batchModel    = new QuizBatchModel();

        // Bank soal: terbaru di atas, pagination backend 10/halaman, pencarian.
        $search   = trim((string) ($this->request->getGet('q') ?? ''));
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage  = 10;
        $offset   = ($page - 1) * $perPage;
        $totalQ   = $questionModel->countForQuiz($id, $search !== '' ? $search : null);
        $totalPage = max(1, (int) ceil($totalQ / $perPage));

        $questions = $questionModel->forQuizPaginated($id, $search !== '' ? $search : null, $perPage, $offset);

        // Nomor urut global: posisi berdasarkan id DESC (terbaru = nomor 1).
        $globalNumbers = [];
        foreach ($questions as $i => $q) {
            $globalNumbers[(int) $q['id']] = $offset + $i + 1;
        }

        // Informasi provider/model AI adalah internal admin:
        // jangan kirim ke frontend dosen (SDD: dosen tidak melihat provider).
        // Batch menggantung (proses mati / "queued" warisan versi lama) ditandai
        // gagal agar tidak memblokir generate baru.
        $batchModel->failStaleProcessing($id);
        $batchRows = $batchModel->forQuiz($id);

        $batches = array_map(static function (array $b): array {
            unset($b['provider_used'], $b['model_used'], $b['extract_path'], $b['pdf_path'], $b['file_sha256']);

            return $b;
        }, $batchRows);

        // Generate yang sedang berjalan untuk kuis ini (halaman memantau prosesnya;
        // refresh halaman TIDAK membuat batch baru).
        $activeBatch = $batchModel->activeForQuiz($id);

        // Durasi efektif per soal (NULL = default otomatis per difficulty/manual)
        // agar dropdown bank soal menampilkan nilai yang benar, bukan 30.
        $scoring      = new \App\Libraries\Quiz\QuizScoringService();
        $questions = array_map(static function (array $q) use ($scoring): array {
            $q['effective_duration'] = $scoring->resolveDuration(
                $q['duration_seconds'] !== null ? (int) $q['duration_seconds'] : null,
                $q['difficulty_level']
            );

            return $q;
        }, $questions);

        $stats = $questionModel->difficultyStats($id);
        $maxScore = (int) $quiz['correct_points'] * $stats['total'];

        $codeModel  = new QuizAccessCodeModel();
        $activeCode = $codeModel->activeForQuiz($id);
        $codeHistory = $codeModel->historyForQuiz($id);

        // Kode aktif dari tabel kode (fallback legacy: kolom quiz_quizzes).
        $quiz['access_code'] = $activeCode['code'] ?? $quiz['access_code'];

        return view('lecturer/quiz/show', [
            'quiz'     => $quiz,
            'questions'=> $questions,
            'batches'  => $batches,
            'stats'    => $stats,
            'maxScore' => $maxScore,
            'counts'   => \App\Libraries\Quiz\QuestionGenerationService::ALLOWED_COUNTS,
            'maxQuestions' => \App\Libraries\Quiz\QuestionGenerationService::MAX_QUESTIONS_PER_QUIZ,
            'capacity' => max(0, \App\Libraries\Quiz\QuestionGenerationService::MAX_QUESTIONS_PER_QUIZ - (int) $stats['total']),
            'activeBatch'  => $activeBatch ? ['id' => (int) $activeBatch['id'], 'status' => (string) $activeBatch['status']] : null,
            'search'   => $search,
            'page'     => $page,
            'totalPage'=> $totalPage,
            'totalQ'   => $totalQ,
            'globalNumbers' => $globalNumbers,
            'codeHistory'   => $codeHistory,
        ]);
    }

    /** Halaman pengaturan kuis (terpisah dari bank soal). */
    public function settingsForm(int $id)
    {
        $quiz = $this->ownedOrFail($id);
        $total = (new QuizQuestionModel())->where('quiz_id', $id)->where('is_active', 1)->countAllResults();

        return view('lecturer/quiz/settings', [
            'quiz'     => $quiz,
            'total'    => $total,
            'maxScore' => $total * (int) $quiz['correct_points'],
            'codeHistory' => (new QuizAccessCodeModel())->historyForQuiz($id),
        ]);
    }

    /** Simpan pengaturan pelaksanaan: waktu mulai + durasi; berakhir dihitung otomatis. */
    public function settings(int $id)
    {
        $quiz = $this->ownedOrFail($id);

        $schedule = $this->parseSchedule();
        if ($schedule === null) {
            return redirect()->to('/lecturer/quiz/' . $id . '/settings')->with('error', 'Waktu mulai wajib diisi dan durasi harus 1-1440 menit.');
        }

        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['draft', 'active', 'closed', 'archived'], true)) {
            $status = $quiz['status'];
        }

        $this->model->update($id, [
            'starts_at'        => $schedule['starts_at'],
            'ends_at'          => $schedule['ends_at'],
            'duration_minutes' => $schedule['duration'],
            'status'           => $status,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/lecturer/quiz/' . $id . '/settings')
            ->with('success', 'Pengaturan pelaksanaan disimpan. Waktu berakhir otomatis: ' . $schedule['ends_at'] . '.');
    }

    /**
     * Regenerasi Kode Kuis (khusus dosen pemilik quiz):
     * kode lama dinonaktifkan (tetap tersimpan sebagai histori), kode baru
     * diterbitkan dan menjadi kode aktif. Attempt yang sedang berjalan dengan
     * kode lama tidak terpengaruh; kode lama tidak bisa memulai attempt baru.
     */
    public function regenerateCode(int $id)
    {
        $quiz = $this->ownedOrFail($id);

        $codeModel = new QuizAccessCodeModel();

        try {
            $newCode = $this->model->generateAccessCode();
            $row     = $codeModel->publishNewCode($id, $this->lid(), $newCode);
        } catch (\RuntimeException $e) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => 'generate_failed']);
            }

            return redirect()->to('/lecturer/quiz/' . $id)->with('error', 'Gagal menghasilkan kode baru. Coba lagi.');
        }

        if ($row === []) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => 'generate_failed']);
            }

            return redirect()->to('/lecturer/quiz/' . $id)->with('error', 'Gagal menerbitkan kode baru. Coba lagi.');
        }

        $message = 'Kode Kuis baru: ' . $newCode . '. Kode lama (' . $quiz['access_code'] . ') tidak berlaku untuk attempt baru. Histori dan hasil percobaan mahasiswa tetap tersimpan.';

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true, 'code' => $newCode, 'message' => $message]);
        }

        return redirect()->to('/lecturer/quiz/' . $id)->with('success', $message);
    }

    /** Histori kode kuis sebuah quiz (JSON, milik dosen). */
    public function codeHistory(int $id)
    {
        $this->ownedOrFail($id);

        $codes = (new QuizAccessCodeModel())->historyForQuiz($id);

        return $this->response->setJSON(['ok' => true, 'codes' => $codes]);
    }

    public function delete(int $id)
    {
        $quiz = $this->ownedOrFail($id);

        // Lindungi histori nilai: kuis yang sudah pernah dikerjakan tidak boleh dihapus, hanya diarsipkan.
        $hasAttempts = (new QuizAttemptModel())->where('quiz_id', $id)->countAllResults();
        if ($hasAttempts > 0) {
            $this->model->update($id, ['status' => 'archived', 'updated_at' => date('Y-m-d H:i:s')]);

            return redirect()->to('/lecturer/quiz')->with('info', 'Kuis memiliki riwayat attempt sehingga diarsipkan, bukan dihapus.');
        }

        $this->model->delete($id);

        return redirect()->to('/lecturer/quiz')->with('success', 'Kuis dihapus.');
    }

    /** Daftar kuis milik dosen (submenu Nilai Mahasiswa). */
    public function nilaiIndex()
    {
        $quizzes = $this->model->forLecturer($this->lid());

        // Statistik per kuis dengan query GROUP BY (2 query untuk seluruh
        // daftar, bukan 2 query per kuis).
        $quizIds = array_map(static fn ($q) => (int) $q['id'], $quizzes);
        $actives = (new QuizAccessCodeModel())->activeForQuizzes($quizIds);
        $questionCounts = (new QuizQuestionModel())->activeCountsForQuizzes($quizIds);
        $gradedCounts   = (new QuizAttemptModel())->gradedCountsForQuizzes($quizIds);

        foreach ($quizzes as &$q) {
            $q['question_count'] = $questionCounts[(int) $q['id']] ?? 0;
            $q['graded_count']   = $gradedCounts[(int) $q['id']] ?? 0;
            $q['access_code']    = $actives[(int) $q['id']]['code'] ?? $q['access_code'];
        }
        unset($q);

        return view('lecturer/quiz/nilai_index', ['quizzes' => $quizzes]);
    }

    /** Nilai mahasiswa satu kuis (tabel + pencarian + pagination). */
    public function nilai(int $id)
    {
        $quiz = $this->ownedOrFail($id);

        $attemptModel = new QuizAttemptModel();
        $search = trim((string) ($this->request->getGet('q') ?? ''));
        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $total   = $attemptModel->countGradesForQuiz($id, $search !== '' ? $search : null);
        $grades  = $attemptModel->gradesForQuiz($id, $search !== '' ? $search : null, $perPage, $offset);
        $totalPage = max(1, (int) ceil($total / $perPage));

        return view('lecturer/quiz/nilai', [
            'quiz'     => $quiz,
            'grades'   => $grades,
            'search'   => $search,
            'page'     => $page,
            'totalPage'=> $totalPage,
            'total'    => $total,
        ]);
    }

    /** Export Excel (.xlsx) nilai mahasiswa satu kuis — kepemilikan divalidasi backend. */
    public function exportNilai(int $id)
    {
        $quiz = $this->ownedOrFail($id);

        $attemptModel = new QuizAttemptModel();
        $grades = $attemptModel->allGradesForQuiz($id);

        $exporter = new \App\Libraries\Quiz\XlsxExporter([
            'No.', 'NPM', 'Nama Mahasiswa', 'Kelas', 'Status', 'Skor Aktual', 'Persentase', 'Waktu Penyelesaian', 'Kode Kuis',
        ]);

        $no = 1;
        foreach ($grades as $g) {
            $status = ((int) $g['max_score']) > 0 ? 'Selesai' : 'Tidak Selesai';
            $exporter->addRow([
                $no++,
                $g['npm'] !== null ? (string) $g['npm'] : $g['student_email'],
                $g['nama'],
                $g['kelas'],
                $g['status'] === 'submitted' ? 'Selesai' : ($g['status'] === 'timeout' ? 'Tidak Selesai (Waktu Habis)' : ucfirst($g['status'])),
                (int) $g['raw_score'],
                $g['percentage'] !== null ? (float) $g['percentage'] : 0,
                (string) $g['submitted_at'],
                $g['access_code'] ?? $quiz['access_code'],
            ]);
        }

        $exportCode = $quiz['access_code'];
        $path = WRITEPATH . 'exports/Nilai_Kuis_' . $exportCode . '.xlsx';
        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        $exporter->save($path);

        return $this->response->download($path, null)->setFileName('Nilai_Kuis_' . $exportCode . '.xlsx');
    }

    /** Analitik dosen. */
    public function analytics(int $id)
    {
        $quiz = $this->ownedOrFail($id);

        $question = new QuizQuestionModel();
        $attemptM = new QuizAttemptModel();

        $questions = $question->forQuiz($id);
        $stats     = $question->difficultyStats($id);

        // Rata-rata skor/peserta via agregat (tanpa memuat seluruh attempt).
        $agg = $attemptM->aggregateForQuiz($id);
        $avgRaw = round($agg['avg_raw'], 2);
        $avgPct = round($agg['avg_pct'], 2);

        // Analitik per soal: statistik seluruh soal dalam SATU query GROUP BY
        // (menggantikan satu query jawaban per soal).
        $answerStats = $question->answerStatsForQuiz($id);

        $perQuestion = [];
        foreach ($questions as $q) {
            $s  = $answerStats[(int) $q['id']] ?? null;
            $n  = $s !== null ? $s['n'] : 0;

            $perQuestion[] = [
                'question'   => $q,
                'responses'  => $n,
                'right'      => $s !== null ? $s['right'] : 0,
                'wrong'      => $s !== null ? $s['wrong'] : 0,
                'blank'      => $s !== null ? $s['blank'] : 0,
                'right_pct'  => $n > 0 ? round(($s !== null ? $s['right'] : 0) / $n * 100, 1) : null,
                'avg_rt_sec' => ($n > 0 && $s !== null) ? round($s['rt_sum'] / $n / 1000, 1) : null,
            ];
        }

        usort($perQuestion, static fn ($a, $b) => ($b['avg_rt_sec'] ?? -1) <=> ($a['avg_rt_sec'] ?? -1));

        return view('lecturer/quiz/analytics', [
            'quiz'         => $quiz,
            'stats'        => $stats,
            'attemptCount' => $agg['n'],
            'avgRaw'       => $avgRaw,
            'avgPct'       => $avgPct,
            'perQuestion'  => $perQuestion,
        ]);
    }
}
