<?php

namespace App\Controllers\Lecturer;

use App\Controllers\BaseController;
use App\Models\PdfFileModel;

class PdfManager extends BaseController
{
    protected $uploadDir;

    public function __construct()
    {
        $this->uploadDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR;
        if (! is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Daftar file PDF yang pernah diupload
     */
    public function index()
    {
        $dosenId = session('user_id');
        $files   = (new PdfFileModel())
            ->where('dosen_id', $dosenId)
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('lecturer/pdf_manager', ['files' => $files]);
    }

    /**
     * Upload file PDF baru
     */
    public function upload()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to('/lecturer/pdf-manager');
        }

        $judul     = trim((string) $this->request->getPost('judul'));
        $deskripsi = trim((string) $this->request->getPost('deskripsi'));
        $dosenId   = session('user_id');

        // Validasi judul
        if ($judul === '' || strlen($judul) > 30) {
            return redirect()->back()->withInput()->with('error', 'Judul wajib diisi (maksimal 30 karakter).');
        }

        // Validasi deskripsi
        if ($deskripsi !== '' && strlen($deskripsi) > 200) {
            return redirect()->back()->withInput()->with('error', 'Deskripsi maksimal 200 karakter.');
        }

        // Validasi file
        $file = $this->request->getFile('pdf_file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'File PDF wajib dipilih.');
        }

        if ($file->getMimeType() !== 'application/pdf') {
            return redirect()->back()->withInput()->with('error', 'Hanya file PDF yang diperbolehkan.');
        }

        // Validasi ukuran (max 10 MB)
        $maxSize = 10 * 1024 * 1024; // 10 MB
        if ($file->getSize() > $maxSize) {
            return redirect()->back()->withInput()->with('error', 'Ukuran file maksimal 10 MB.');
        }

        // Generate nama file: kata_pertama_judul + 6 digit random
        $fileName = $this->generateFileName($judul) . '.pdf';

        // Simpan file
        $file->move($this->uploadDir, $fileName);

        // Simpan ke database
        (new PdfFileModel())->insert([
            'dosen_id'  => $dosenId,
            'judul'     => $judul,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'file_name' => $fileName,
            'file_size' => $file->getSize(),
        ]);

        return redirect()->to('/lecturer/pdf-manager')->with('success', 'File PDF berhasil diupload.');
    }

    /**
     * Hapus file PDF
     */
    public function delete(int $id)
    {
        $dosenId = session('user_id');
        $model   = new PdfFileModel();

        $pdfFile = $model
            ->where('id', $id)
            ->where('dosen_id', $dosenId)
            ->first();

        if (! $pdfFile) {
            return redirect()->to('/lecturer/pdf-manager')->with('error', 'File tidak ditemukan.');
        }

        // Cek apakah file sedang dipakai di pertemuan
        $usedCount = db_connect()->table('meetings')->where('pdf_file_id', $id)->countAllResults();
        if ($usedCount > 0) {
            return redirect()->to('/lecturer/pdf-manager')->with('error', 'File tidak bisa dihapus karena sedang digunakan di ' . $usedCount . ' pertemuan.');
        }

        // Hapus file fisik
        $filePath = $this->uploadDir . $pdfFile['file_name'];
        if (is_file($filePath)) {
            unlink($filePath);
        }

        $model->delete($id);

        return redirect()->to('/lecturer/pdf-manager')->with('success', 'File PDF berhasil dihapus.');
    }

    /**
     * Generate nama file: kata_pertama_judul + 6 digit random
     */
    private function generateFileName(string $judul): string
    {
        $words  = preg_split('/\s+/', trim($judul));
        $first  = $words[0] ?? 'Materi';
        $clean  = preg_replace('/[^a-zA-Z0-9]/', '', $first);
        if ($clean === '') $clean = 'Materi';
        $random = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return $clean . '_' . $random;
    }
}
