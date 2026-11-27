<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Quiz\AIProviderManager;
use App\Libraries\Quiz\QuizCrypto;
use App\Models\Quiz\QuizAIProviderModel;
use App\Models\Quiz\QuizAILogModel;

class QuizAI extends BaseController
{
    private QuizAIProviderModel $model;

    public function __construct()
    {
        $this->model = new QuizAIProviderModel();
    }

    public function index()
    {
        $providers = $this->model->orderBy('priority_order', 'ASC')->findAll();

        // API key TIDAK dikirim ke browser: hanya masking.
        foreach ($providers as &$p) {
            $p['key_masked'] = $this->model->maskKey(QuizCrypto::decrypt($p['api_key_encrypted']));
            $p['has_key']    = QuizCrypto::decrypt($p['api_key_encrypted']) !== null;
        }
        unset($p);

        return view('admin/quiz_ai/index', [
            'pageTitle'    => 'Pengaturan AI',
            'pageSubtitle' => 'Konfigurasi OpenRouter untuk pembuatan soal AI',
            'providers'    => $providers,
        ]);
    }

    private function seedDefaults(): void
    {
        if ((int) $this->model->countAll() === 0) {
            $now = date('Y-m-d H:i:s');
            $this->model->insertBatch([
                ['provider_code' => 'openrouter', 'display_name' => 'OpenRouter', 'base_url' => 'https://openrouter.ai/api/v1', 'is_enabled' => 0, 'priority_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function save()
    {
        $id   = (int) $this->request->getPost('id');
        $code = (string) $this->request->getPost('provider_code');

        if ($code !== 'openrouter') {
            return redirect()->to('/admin/quiz-ai')->with('error', 'Provider tidak dikenal. Hanya OpenRouter yang didukung.');
        }

        $baseUrl = trim((string) $this->request->getPost('base_url'));
        $model   = trim((string) $this->request->getPost('model_name'));
        $apiKey  = trim((string) $this->request->getPost('api_key')); // key baru (opsional, hanya untuk rotasi)

        if ($baseUrl === '' || ! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return redirect()->to('/admin/quiz-ai')->with('error', 'Base URL tidak valid.');
        }

        $data = [
            'base_url'   => $baseUrl,
            'model_name' => $model !== '' ? $model : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($apiKey !== '') {
            $data['api_key_encrypted'] = QuizCrypto::encrypt($apiKey);
        }

        $existing = $this->model->where('provider_code', $code)->first();

        if ($existing === null) {
            $this->seedDefaults();
            $existing = $this->model->where('provider_code', $code)->first();
        }

        if ($existing === null) {
            $data += ['provider_code' => 'openrouter', 'display_name' => 'OpenRouter', 'priority_order' => 1, 'is_enabled' => 0, 'created_at' => date('Y-m-d H:i:s')];
            $this->model->insert($data);
        } else {
            if (($data['api_key_encrypted'] ?? null) === null) {
                unset($data['api_key_encrypted']); // pertahankan key lama bila tidak diganti
            }
            $this->model->update((int) $existing['id'], $data);
        }

        return redirect()->to('/admin/quiz-ai')->with('success', 'Konfigurasi provider disimpan.');
    }

    public function toggle(int $id)
    {
        $p = $this->model->find($id);
        if ($p === null) {
            return redirect()->to('/admin/quiz-ai')->with('error', 'Provider tidak ditemukan.');
        }
        if (QuizCrypto::decrypt($p['api_key_encrypted']) === null) {
            return redirect()->to('/admin/quiz-ai')->with('error', 'Atur API key sebelum mengaktifkan provider.');
        }

        $this->model->update($id, ['is_enabled' => ((int) $p['is_enabled'] === 1) ? 0 : 1, 'updated_at' => date('Y-m-d H:i:s')]);

        return redirect()->to('/admin/quiz-ai')->with('success', 'Status provider diperbarui.');
    }

    public function test(int $id)
    {
        $p = $this->model->find($id);
        if ($p === null) {
            return redirect()->to('/admin/quiz-ai')->with('error', 'Provider tidak ditemukan.');
        }

        $res = (new AIProviderManager())->testProvider($p);

        $this->model->update($id, [
            'last_test_status' => $res['ok'] ? 'success' : ('failed:' . ($res['error_category'] ?? 'unknown')),
            'last_test_at'     => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/admin/quiz-ai')->with(
            $res['ok'] ? 'success' : 'error',
            $res['ok']
                ? 'Koneksi berhasil (' . (int) $res['latency_ms'] . ' ms).'
                : 'Uji koneksi gagal: ' . ($res['error_category'] ?? 'unknown')
        );
    }

    public function reorder()
    {
        $ids = $this->request->getPost('priority');
        if (! is_array($ids)) {
            return redirect()->to('/admin/quiz-ai')->with('error', 'Data prioritas tidak valid.');
        }

        foreach (array_values($ids) as $i => $pid) {
            $this->model->update((int) $pid, ['priority_order' => $i + 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        return redirect()->to('/admin/quiz-ai')->with('success', 'Urutan prioritas provider disimpan. Provider pertama menjadi utama, berikutnya sebagai fallback.');
    }

    public function logs()
    {
        $logs = (new QuizAILogModel())->latest(200);

        return view('admin/quiz_ai/logs', [
            'pageTitle'    => 'Log AI',
            'pageSubtitle' => '200 aktivitas penggunaan AI terakhir',
            'logs'         => $logs,
        ]);
    }
}
