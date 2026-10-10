<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AdService;
use App\Models\AdImpressionModel;
use App\Models\AdModel;

class Ads extends BaseController
{
    protected AdModel $adModel;
    protected AdImpressionModel $impressionModel;
    protected AdService $adService;

    public const PER_PAGE = 10;

    /** Pilihan sort yang diizinkan (whitelist). */
    public const ALLOWED_SORTS = [
        'newest'   => ['label' => 'Terbaru',         'order' => 'id DESC'],
        'oldest'   => ['label' => 'Terlama',         'order' => 'id ASC'],
        'views'    => ['label' => 'View Hari Ini',   'order' => 'views_today DESC, id DESC'],
        'score'    => ['label' => 'Score',           'order' => 'priority_score DESC, id DESC'],
        'title'    => ['label' => 'Judul A→Z',       'order' => 'title ASC, id DESC'],
    ];

    public function __construct()
    {
        $this->adModel         = new AdModel();
        $this->impressionModel = new AdImpressionModel();
        $this->adService       = new AdService();
    }

    private function uploadDir(): string
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'ads' . DIRECTORY_SEPARATOR;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Daftar iklan dengan pagination (10/halaman) + sorting.
     * Default: 'newest' (id DESC). Sort lain: views, score, title, oldest.
     */
    public function index()
    {
        $sort = (string) ($this->request->getGet('sort') ?? 'newest');
        if (! array_key_exists($sort, self::ALLOWED_SORTS)) {
            $sort = 'newest';
        }
        $sortConfig = self::ALLOWED_SORTS[$sort];

        $db    = db_connect();
        $today = date('Y-m-d');

        // Hitung views_today per ad (subquery)
        $viewsSub = $db->table('ad_impressions')
            ->select('ad_id, SUM(view_count) AS views_today')
            ->where('view_date', $today)
            ->groupBy('ad_id')
            ->getCompiledSelect();

        // Query utama: LEFT JOIN dengan subquery views_today.
        // Pakai table alias 'a' untuk ads agar tidak ambigu dengan subquery.
        $builder = $db->table('ads a');
        $builder->select('a.*, COALESCE(vi.views_today, 0) AS views_today');
        $builder->join("({$viewsSub}) vi", 'vi.ad_id = a.id', 'left');

        // Apply sort — sort 'views' & 'score' pakai alias, bukan raw kolom
        switch ($sort) {
            case 'views':
                $builder->orderBy('COALESCE(vi.views_today, 0)', 'DESC', false);
                $builder->orderBy('a.id', 'DESC');
                break;
            case 'score':
                $builder->orderBy('a.priority_score', 'DESC');
                $builder->orderBy('a.id', 'DESC');
                break;
            case 'oldest':
                $builder->orderBy('a.id', 'ASC');
                break;
            case 'title':
                $builder->orderBy('a.title', 'ASC');
                $builder->orderBy('a.id', 'DESC');
                break;
            case 'newest':
            default:
                $builder->orderBy('a.is_active', 'DESC'); // aktif di atas
                $builder->orderBy('a.id', 'DESC');
                break;
        }

        // Total impressions hari ini (semua ad)
        $totalImpressions = (int) ($db->table('ad_impressions')
            ->selectSum('view_count', 'total')
            ->where('view_date', $today)
            ->get()
            ->getRowArray()['total'] ?? 0);

        // Pagination
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = self::PER_PAGE;
        $offset  = ($page - 1) * $perPage;

        $builder->limit($perPage, $offset);
        $items = $builder->get()->getResultArray();

        // Total rows (untuk pager)
        $totalRows = (int) $db->table('ads a')->countAllResults();
        $pageCount = max(1, (int) ceil($totalRows / $perPage));

        // Build pager sederhana (mirip style Mahasiswa)
        $pager = [
            'currentPage' => $page,
            'pageCount'   => $pageCount,
            'perPage'     => $perPage,
            'total'       => $totalRows,
            'baseUrl'     => base_url('admin/ads') . '?sort=' . $sort,
        ];

        $data = [
            'pageTitle'        => 'Manajemen Iklan',
            'pageSubtitle'     => 'Kelola iklan popup GIF',
            'items'            => $items,
            'totalImpressions' => $totalImpressions,
            'pager'            => $pager,
            'sort'             => $sort,
            'sortOptions'      => self::ALLOWED_SORTS,
        ];
        return view('admin/ads/index', $data);
    }

    public function create()
    {
        $data = [
            'pageTitle'    => 'Tambah Iklan',
            'pageSubtitle' => 'Upload GIF baru',
            'ad'           => null,
            'maxFileMb'    => $this->adService->getMaxFileSizeMb(),
            'lockSeconds'  => $this->adService->getLockSeconds(),
            'dailyMax'     => $this->adService->getDailyMax(),
        ];
        return view('admin/ads/form', $data);
    }

    public function edit($id)
    {
        $ad = $this->adModel->find((int) $id);
        if (! $ad) {
            return redirect()->to('/admin/ads')->with('error', 'Iklan tidak ditemukan.');
        }
        $data = [
            'pageTitle'    => 'Edit Iklan',
            'pageSubtitle' => 'Perbarui iklan',
            'ad'           => $ad,
            'maxFileMb'    => $this->adService->getMaxFileSizeMb(),
            'lockSeconds'  => $this->adService->getLockSeconds(),
            'dailyMax'     => $this->adService->getDailyMax(),
        ];
        return view('admin/ads/form', $data);
    }

    public function save()
    {
        $id            = (int) ($this->request->getPost('id') ?? 0);
        $title         = trim((string) $this->request->getPost('title'));
        $targetUrl     = trim((string) $this->request->getPost('target_url'));
        $isActive      = (int) $this->request->getPost('is_active');
        $targetGender  = (string) $this->request->getPost('target_gender');
        $priorityScore = (int) $this->request->getPost('priority_score');

        if ($title === '' || $targetUrl === '') {
            return redirect()->back()->withInput()->with('error', 'Judul dan target URL wajib diisi.');
        }
        if (! filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return redirect()->back()->withInput()->with('error', 'Target URL tidak valid.');
        }
        // Lindungi dari target_url internal/local — iklan harus mengarah ke URL
        // eksternal (Shopee, dll). URL yang mengandung host lokal/baseURL akan
        // ditolak agar tidak terjadi redirect loop atau paparan file internal.
        $parsed = parse_url($targetUrl);
        $host   = strtolower($parsed['host'] ?? '');
        $baseHost = strtolower(parse_url((string) (new \Config\App())->baseURL, PHP_URL_HOST) ?? '');
        if ($host === '' || $host === $baseHost || $host === 'localhost' || $host === '127.0.0.1') {
            return redirect()->back()->withInput()->with('error', 'Target URL harus URL eksternal (bukan URL internal/localhost).');
        }
        // Harus http(s) — bukan scheme lain
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return redirect()->back()->withInput()->with('error', 'Target URL harus ber-scheme http atau https.');
        }
        if (! in_array($targetGender, [\App\Models\AdModel::GENDER_LAKI, \App\Models\AdModel::GENDER_PEREMPUAN, \App\Models\AdModel::GENDER_BOTH], true)) {
            $targetGender = \App\Models\AdModel::GENDER_BOTH;
        }
        if ($priorityScore < 0) {
            $priorityScore = 0;
        }

        $payload = [
            'title'          => $title,
            'target_url'     => $targetUrl,
            'is_active'      => $isActive ? 1 : 0,
            'target_gender'  => $targetGender,
            'priority_score' => $priorityScore,
        ];

        $file = $this->request->getFile('gif_file');
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $ext = strtolower($file->getClientExtension());
            $mime = $file->getClientMimeType() ?: $file->getMimeType();
            if ($ext !== 'gif' || $mime !== 'image/gif') {
                return redirect()->back()->withInput()->with('error', 'File harus berformat GIF (image/gif).');
            }
            $maxBytes = $this->adService->getMaxFileSizeMb() * 1024 * 1024;
            if ($file->getSize() > $maxBytes) {
                return redirect()->back()->withInput()->with('error', 'Ukuran file melebihi ' . $this->adService->getMaxFileSizeMb() . ' MB.');
            }
            $newName = 'gfx_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.gif';
            $file->move($this->uploadDir(), $newName, true);
            $payload['file_name']     = $newName;
            $payload['original_name'] = $file->getClientName();
            $payload['file_size']     = filesize($this->uploadDir() . $newName) ?: $file->getSize();
        } elseif ($id === 0) {
            return redirect()->back()->withInput()->with('error', 'File GIF wajib diupload untuk iklan baru.');
        }

        if ($id > 0) {
            $this->adModel->update($id, $payload);
            return redirect()->to('/admin/ads')->with('success', 'Iklan berhasil diperbarui.');
        }
        $this->adModel->insert($payload);
        return redirect()->to('/admin/ads')->with('success', 'Iklan berhasil ditambahkan.');
    }

    public function toggle($id)
    {
        $ad = $this->adModel->find((int) $id);
        if (! $ad) {
            return redirect()->to('/admin/ads')->with('error', 'Iklan tidak ditemukan.');
        }
        $this->adModel->update($id, ['is_active' => $ad['is_active'] ? 0 : 1]);
        return redirect()->to('/admin/ads')->with('success', 'Status iklan diperbarui.');
    }

    public function delete($id)
    {
        $ad = $this->adModel->find((int) $id);
        if (! $ad) {
            return redirect()->to('/admin/ads')->with('error', 'Iklan tidak ditemukan.');
        }
        if (! empty($ad['file_name'])) {
            $path = $this->uploadDir() . $ad['file_name'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->adModel->delete($id);
        return redirect()->to('/admin/ads')->with('success', 'Iklan berhasil dihapus.');
    }

    public function resetImpressions()
    {
        $db = db_connect();
        $deleted = $db->table('ad_impressions')->countAll();
        $db->table('ad_impressions')->truncate();
        return redirect()->to('/admin/ads')->with('success', "✓ Berhasil reset {$deleted} record impression. Semua user akan melihat iklan lagi sesuai aturan modulus.");
    }

    public function clickReport()
    {
        $periods = [
            '1d'  => ['label' => 'Hari Ini',     'days' => 1],
            '7d'  => ['label' => '7 Hari',       'days' => 7],
            '30d' => ['label' => '30 Hari',      'days' => 30],
            '90d' => ['label' => '90 Hari',      'days' => 90],
        ];

        $period = $this->request->getGet('period') ?? '7d';
        if (! isset($periods[$period])) {
            $period = '7d';
        }

        $days       = $periods[$period]['days'];
        $startDate  = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $endDate    = date('Y-m-d');
        $label      = $periods[$period]['label'];

        $db = db_connect();

        $ads = $this->adModel
            ->orderBy('is_active', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        $clickModel    = new \App\Models\AdClickModel();
        $impModel      = new \App\Models\AdImpressionModel();
        $clicksByAd    = $clickModel->countByAdSince($startDate);
        $impressionsByAd = [];
        $impRows = $db->table('ad_impressions')
            ->select('ad_id, SUM(view_count) AS impressions')
            ->where('view_date >=', $startDate)
            ->groupBy('ad_id')
            ->get()
            ->getResultArray();
        foreach ($impRows as $r) {
            $impressionsByAd[(int) $r['ad_id']] = (int) $r['impressions'];
        }

        $rows = [];
        $totalClicks = 0;
        $totalImpressions = 0;
        foreach ($ads as $ad) {
            $clicks      = $clicksByAd[(int) $ad['id']] ?? 0;
            $impressions = $impressionsByAd[(int) $ad['id']] ?? 0;
            $ctr         = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0;
            $rows[] = [
                'ad'          => $ad,
                'clicks'      => $clicks,
                'impressions' => $impressions,
                'ctr'         => $ctr,
            ];
            $totalClicks      += $clicks;
            $totalImpressions += $impressions;
        }

        usort($rows, fn($a, $b) => $b['clicks'] <=> $a['clicks']);

        $totalCtr = $totalImpressions > 0 ? round($totalClicks / $totalImpressions * 100, 2) : 0.0;

        $data = [
            'pageTitle'        => 'Laporan Klik Iklan',
            'pageSubtitle'     => 'Histori klik iklan oleh mahasiswa',
            'rows'             => $rows,
            'period'           => $period,
            'periods'          => $periods,
            'label'            => $label,
            'days'             => $days,
            'startDate'        => $startDate,
            'endDate'          => $endDate,
            'totalClicks'      => $totalClicks,
            'totalImpressions' => $totalImpressions,
            'totalCtr'         => $totalCtr,
        ];

        return view('admin/ads/click_report', $data);
    }
}
