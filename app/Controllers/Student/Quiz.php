<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Libraries\Quiz\QuizScoringService;
use App\Models\Quiz\QuizAttemptModel;
use App\Models\Quiz\QuizModel;

class Quiz extends BaseController
{
    private QuizModel $model;
    private QuizAttemptModel $attemptModel;

    private QuizScoringService $scoring;

    public function __construct()
    {
        $this->model        = new QuizModel();
        $this->attemptModel = new QuizAttemptModel();
        $this->scoring      = new QuizScoringService();
    }

    private function email(): string
    {
        // Session mahasiswa lama: user_id = email mahasiswa (PK tabel students).
        return (string) session('user_id');
    }

    /**
     * Dashboard quiz mahasiswa + PEMULIHAN SESI:
     * jika masih ada attempt aktif milik mahasiswa (ujian belum selesai),
     * jangan minta kode lagi — langsung arahkan ke halaman pengerjaan.
     * Soal aktif yang ditinggalkan tanpa jawaban dihitung "Tidak tahu" (nilai 0)
     * dan mahasiswa melanjutkan ke soal berikutnya.
     */
    public function index()
    {
        $email = $this->email();

        // --- Pemulihan sesi attempt aktif ---
        $active = $this->attemptModel->hasActiveAttemptAnyQuiz($email);
        if ($active !== null) {
            $service = $this->scoring;

            // Batas waktu attempt keseluruhan lewat -> finalisasi otomatis.
            if ($service->isExpired($active)) {
                $service->finalize($active, 'timeout');
            } else {
                // Validasi perangkat: hanya perangkat terikat yang bisa melanjutkan.
                if ($service->verifyDevice($active)) {
                    // Soal aktif yang ditinggalkan = "Tidak tahu" (nilai 0), lanjut ke soal berikutnya.
                    $service->abandonCurrentQuestion($active);

                    return redirect()->to('/student/quiz/attempt/' . $active['id']);
                }

                // Perangkat tidak cocok: tampilkan halaman terkunci (ikatan tetap).
                return view('student/quiz/device_locked', ['attempt' => $active]);
            }
        }

        // Histori percobaan: nomor percobaan dihitung backend per quiz.
        $results = $this->attemptModel->historyForStudent($email);

        return view('student/quiz/index', ['results' => $results]);
    }

    /** Info kuis berdasarkan kode akses + attempt yang bisa dilanjutkan. */
    public function info()
    {
        // Normalisasi + validasi format di backend (kode baru 6 karakter / kode lama).
        $code = strtoupper(trim((string) $this->request->getPost('access_code')));

        if ($code === '') {
            return redirect()->to('/student/quiz')->with('error', 'Masukkan kode kuis.');
        }
        if (! QuizModel::isValidAccessCodeFormat($code)) {
            return redirect()->to('/student/quiz')->with('error', 'Format kode kuis tidak valid. Kode terdiri dari 6 huruf kapital/angka.');
        }

        // Kode dicari pada tabel histori kode quiz_access_codes (bukan kolom quiz).
        $found = $this->model->findByAccessCode($code);
        if ($found === null) {
            return redirect()->to('/student/quiz')->with('error', 'Kode kuis tidak ditemukan.');
        }

        $quiz    = $found['quiz'];
        $codeRow = $found['code'];

        if ((int) $codeRow['is_active'] !== 1) {
            return redirect()->to('/student/quiz')->with('error', 'Kode kuis ini sudah tidak berlaku. Minta kode terbaru dari dosen Anda untuk mengikuti kuis ini.');
        }

        if ($quiz['status'] === 'draft') {
            return redirect()->to('/student/quiz')->with('error', 'Kode kuis tidak ditemukan.');
        }
        if ($quiz['status'] === 'archived' || $quiz['status'] === 'closed') {
            return redirect()->to('/student/quiz')->with('error', 'Kuis sudah ditutup.');
        }

        $now     = time();
        $starts  = $quiz['starts_at'] ? strtotime((string) $quiz['starts_at']) : null;
        $ends    = $quiz['ends_at'] ? strtotime((string) $quiz['ends_at']) : null;

        if ($starts !== null && $now < $starts) {
            return redirect()->to('/student/quiz')->with('error', 'Kuis belum dimulai.');
        }
        if ($ends !== null && $now > $ends) {
            return redirect()->to('/student/quiz')->with('error', 'Kuis sudah berakhir.');
        }

        // Simpan kode di session sementara untuk start.
        session()->set(['quiz_access_code' => $code, 'quiz_access_code_id' => (int) $codeRow['id']]);

        // ONE CODE = ONE ATTEMPT per mahasiswa. Kode yang sudah dipakai
        // mahasiswa ini tidak dapat dipakai memulai attempt kedua.
        if ($this->attemptModel->hasUsedCode((int) $codeRow['id'], $this->email())) {
            return redirect()->to('/student/quiz')->with('error', 'Kode kuis ini sudah pernah Anda gunakan dan tidak dapat digunakan kembali. Gunakan kode baru dari dosen untuk mengikuti kuis ini lagi.');
        }

        $active = $this->attemptModel->hasActiveAttempt((int) $quiz['id'], $this->email());

        // Nomor percobaan berikutnya dihitung per mahasiswa pada quiz yang sama.
        $attemptNo = $this->attemptModel->nextAttemptNo((int) $quiz['id'], $this->email());

        $data = ['quiz' => $quiz, 'code' => $codeRow, 'attemptNo' => $attemptNo, 'active' => $active];

        return view('student/quiz/info', $data);
    }

    /** Mulai attempt (POST): validasi server, snapshot, binding perangkat. */
    public function start(int $id)
    {
        $quiz = $this->model->find($id);
        if ($quiz === null) {
            return redirect()->to('/student/quiz')->with('error', 'Kuis tidak ditemukan.');
        }

        // Kode di session harus cocok dengan quiz (mahasiswa hanya mengakses kuis yang diberikan kepadanya).
        $code       = (string) session('quiz_access_code');
        $codeId     = (int) session('quiz_access_code_id');
        $codeRow    = $codeId > 0 ? (new \App\Models\Quiz\QuizAccessCodeModel())->find($codeId) : null;
        if ($code === '' || $codeRow === null || (int) $codeRow['quiz_id'] !== $id) {
            return redirect()->to('/student/quiz')->with('error', 'Masukkan kode kuis terlebih dahulu.');
        }

        $now    = time();
        $starts = $quiz['starts_at'] ? strtotime((string) $quiz['starts_at']) : null;
        $ends   = $quiz['ends_at'] ? strtotime((string) $quiz['ends_at']) : null;

        if ($quiz['status'] === 'draft' || $quiz['status'] === 'archived' || $quiz['status'] === 'closed'
            || ($starts !== null && $now < $starts) || ($ends !== null && $now > $ends)) {
            return redirect()->to('/student/quiz')->with('error', 'Kuis tidak sedang aktif.');
        }

        $service = new QuizScoringService();

        $active = $this->attemptModel->hasActiveAttempt($id, $this->email());
        if ($active !== null) {
            // Lanjutkan attempt aktif yang sama (perangkat divalidasi di halaman attempt)
            return redirect()->to('/student/quiz/attempt/' . $active['id'])
                ->setCookie(...$this->scoring->deviceCookieParams((int) $active['id'], (string) $active['device_token']));
        }

        if ((int) $codeRow['is_active'] !== 1 || $codeRow['code'] !== $code) {
            return redirect()->to('/student/quiz')->with('error', 'Kode kuis ini sudah tidak berlaku. Minta kode terbaru dari dosen Anda.');
        }

        if ($this->attemptModel->hasUsedCode($codeId, $this->email())) {
            return redirect()->to('/student/quiz')->with('error', 'Kode kuis ini sudah pernah Anda gunakan dan tidak dapat digunakan kembali. Gunakan kode baru dari dosen untuk mengikuti kuis ini lagi.');
        }

        $res = $service->startAttempt($quiz, $this->email(), $codeRow);
        if (! $res['ok']) {
            $msg = match ($res['error']) {
                'code_used'      => 'Kode kuis ini sudah pernah Anda gunakan dan tidak dapat digunakan kembali.',
                'code_inactive'  => 'Kode kuis ini sudah tidak berlaku. Minta kode terbaru dari dosen Anda.',
                'no_questions'   => 'Kuis belum memiliki soal.',
                'active_exists'  => 'Anda masih memiliki attempt aktif.',
                default          => 'Gagal memulai kuis.',
            };

            return redirect()->to('/student/quiz')->with('error', $msg);
        }

        session()->remove(['quiz_access_code', 'quiz_access_code_id']);

        return redirect()->to('/student/quiz/attempt/' . $res['attempt_id'])
            ->setCookie(...$this->scoring->deviceCookieParams((int) $res['attempt_id'], (string) $res['device_token']));
    }

    /** Halaman pengerjaan attempt. Kunci jawaban TIDAK dikirim ke browser. */
    public function attempt(int $attemptId)
    {
        $attempt = $this->attemptModel->findForStudent($attemptId, $this->email());
        if ($attempt === null) {
            return redirect()->to('/student/quiz')->with('error', 'Attempt tidak ditemukan.');
        }

        $service = new QuizScoringService();

        if (in_array($attempt['status'], ['submitted', 'timeout'], true)) {
            return redirect()->to('/student/quiz/attempt/' . $attemptId . '/result');
        }

        if ($service->isExpired($attempt)) {
            $service->finalize($attempt, 'timeout');

            return redirect()->to('/student/quiz/attempt/' . $attemptId . '/result');
        }

        // Satu kode = satu perangkat aktif (validasi server setiap permintaan penting)
        if (! $service->verifyDevice($attempt)) {
            // Perangkat kedua tidak boleh mengambil alih attempt aktif.
            return view('student/quiz/device_locked', ['attempt' => $attempt]);
        }

        // Progres satu soal per halaman (timeout diproses di server).
        $state = $service->progressState($attempt);

        // Semua soal selesai -> finalisasi otomatis.
        if ($state['current'] === null) {
            $service->finalize($attempt, 'submitted');

            return redirect()->to('/student/quiz/attempt/' . $attemptId . '/result')
                ->setCookie(...$this->scoring->clearDeviceCookieParams($attemptId));
        }

        $quiz = $this->model->find((int) $attempt['quiz_id']);

        $question = $service->displayQuestion($state['current']);

        $data = [
            'quiz'         => $quiz,
            'attempt'      => $attempt,
            'question'     => $question,
            'index'        => $state['index'],
            'total'        => $state['total'],
            'answered'     => $state['answered'],
            'remainingSec' => $state['remaining_sec'],
            'durationSec'  => (int) $state['current']['duration_seconds'],
        ];

        return view('student/quiz/attempt', $data);
    }

    /** Simpan satu jawaban (idempotent). Server-side validation penuh. */
    public function answer(int $attemptId)
    {
        $attempt = $this->attemptModel->findForStudent($attemptId, $this->email());
        if ($attempt === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'not_found']);
        }

        $service = new QuizScoringService();

        if (! $service->verifyDevice($attempt)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'error' => 'device_mismatch']);
        }

        $questionId = (int) $this->request->getPost('question_id');
        $selected   = strtoupper(trim((string) $this->request->getPost('selected_option')));

        if (! in_array($selected, ['A', 'B', 'C', 'D', 'E'], true) && $selected !== '') {
            return $this->response->setJSON(['ok' => false, 'error' => 'invalid_option']);
        }

        $res = $service->recordAnswer($attempt, $questionId, $selected === '' ? null : $selected);

        if (! $res['ok'] && ($res['error'] ?? '') === 'expired') {
            return $this->response->setJSON(['ok' => false, 'error' => 'expired', 'redirect' => base_url('/student/quiz/attempt/' . $attemptId)]);
        }
        if (! $res['ok']) {
            return $this->response->setJSON($res);
        }

        // Cek apakah masih ada soal berikutnya; kalau habis -> finalisasi otomatis.
        $state = $service->progressState($attempt);
        if ($state['current'] === null) {
            $service->finalize($attempt, 'submitted');

            return $this->response->setJSON(['ok' => true, 'finished' => true, 'redirect' => base_url('/student/quiz/attempt/' . $attemptId . '/result')]);
        }

        return $this->response->setJSON(['ok' => true, 'next' => base_url('/student/quiz/attempt/' . $attemptId)]);
    }

    /** Submit attempt -> finalisasi & penilaian di server. */
    public function submit(int $attemptId)
    {
        $attempt = $this->attemptModel->findForStudent($attemptId, $this->email());
        if ($attempt === null) {
            return redirect()->to('/student/quiz')->with('error', 'Attempt tidak ditemukan.');
        }

        $service = new QuizScoringService();

        if (! $service->verifyDevice($attempt)) {
            return view('student/quiz/device_locked', ['attempt' => $attempt]);
        }

        if (! in_array($attempt['status'], ['submitted', 'timeout'], true)) {
            // Timeout berdasarkan waktu server menang.
            $status = $service->isExpired($attempt) ? 'timeout' : 'submitted';
            $service->finalize($attempt, $status);
        }

        return redirect()->to('/student/quiz/attempt/' . $attemptId . '/result')
            ->setCookie(...$this->scoring->clearDeviceCookieParams($attemptId));
    }

    /** Hasil: hanya nilai aktual + persentase. Tanpa kunci/pembahasan. */
    public function result(int $attemptId)
    {
        $attempt = $this->attemptModel->findForStudent($attemptId, $this->email());
        if ($attempt === null) {
            return redirect()->to('/student/quiz')->with('error', 'Attempt tidak ditemukan.');
        }

        $service = new QuizScoringService();

        // Auto-finalize bila masih in_progress saat halaman hasil dibuka (mis. timeout di tengah)
        if ($attempt['status'] === 'in_progress' && $service->isExpired($attempt)) {
            $service->finalize($attempt, 'timeout');
            $attempt = $this->attemptModel->find($attemptId);
        }

        if ($attempt['status'] === 'in_progress') {
            return redirect()->to('/student/quiz/attempt/' . $attemptId);
        }

        $quiz = $this->model->find((int) $attempt['quiz_id']);

        return view('student/quiz/result', ['attempt' => $attempt, 'quiz' => $quiz]);
    }
}
