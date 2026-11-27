# ============================================
# DEPLOYMENT SCRIPT - QuizCerdas Application (Windows PowerShell)
# ============================================
# Script ini hanya mengupload file-file penting aplikasi
# File testing, temporary, dan non-essential akan diabaikan
# ============================================

$REMOTE_URL = "https://github.com/rashdan2026/quizcerdas.git"
$BRANCH = "main"

# 1. Cek apakah folder ini sudah ada Git-nya
if (-not (Test-Path ".git")) {
    Write-Host "🗂  Inisialisasi Git baru..." -ForegroundColor Blue
    git init
    git remote add origin $REMOTE_URL
    Add-Content -Path "README.md" -Value "# quizcerdas"
}

# 2. Input pesan commit
$message = Read-Host "📝 Masukkan pesan perubahan (contoh: 'tambah fitur login')"
if (-not $message) { 
    $message = "Update rutin: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" 
}

# 3. Tampilkan file yang akan diupload
Write-Host "`n📋 Menyiapkan file untuk deployment..." -ForegroundColor Blue
Write-Host "✓ File yang akan di-upload:" -ForegroundColor Yellow
Write-Host "  - app/ (Controllers, Models, Views, Config, Database, dll)"
Write-Host "  - public/ (index.php, .htaccess, assets)"
Write-Host "  - system/ (CodeIgniter framework)"
Write-Host "  - writable/ (.htaccess files only)"
Write-Host "  - composer.json & composer.lock"
Write-Host "  - .env.example"
Write-Host "  - index.php (root)"
Write-Host "  - README.md, LICENSE, UPLOAD_INSTRUCTIONS.md"
Write-Host ""
Write-Host "✗ File yang TIDAK akan di-upload:" -ForegroundColor Red
Write-Host "  - .env (keamanan)"
Write-Host "  - vendor/ (composer dependencies)"
Write-Host "  - doc/ (dokumentasi lokal)"
Write-Host "  - test_*.php, *_test.php (file testing)"
Write-Host "  - debug*.php, update_*.php (file debug)"
Write-Host "  - error.jpg, gambar.png (media non-essential)"
Write-Host "  - deploy.sh, deploy.ps1 (deployment scripts)"
Write-Host "  - .qwen/, .vscode/, .idea/ (editor configs)"
Write-Host "  - writable/logs/*, writable/session/*, writable/cache/*, writable/uploads/*"
Write-Host ""

# 4. Reset staging area, buang doc/ dari index (jika pernah ter-track), dan tambahkan file sesuai .gitignore
Write-Host "🚀 Memproses file..." -ForegroundColor Blue
git reset
# Untrack folder doc/ dari git (file lokal tetap ada, hanya tidak di-push)
$docTracked = git ls-files -- doc 2>$null
if ($docTracked) {
    Write-Host "  ✓ Menghapus doc/ dari tracking Git (lokal tetap ada)" -ForegroundColor Yellow
    git rm -r --cached doc 2>$null
}
git add .

# 5. Cek apakah ada perubahan yang perlu di-upload
Write-Host "`n🔍 Mengecek perubahan..." -ForegroundColor Blue
$changedFiles = git diff --cached --name-only
$newFiles = git ls-files --others --exclude-standard
$deletedFiles = git diff --cached --name-only --diff-filter=D

if (-not $changedFiles -and -not $newFiles -and -not $deletedFiles) {
    Write-Host "✅ Tidak ada perubahan baru. Semua file sudah up-to-date di GitHub!" -ForegroundColor Green
    exit 0
}

# Tampilkan summary file yang akan di-commit
Write-Host "`n📊 File yang akan di-upload:" -ForegroundColor Green
if ($changedFiles) {
    Write-Host "  ✏️  File yang dimodifikasi:" -ForegroundColor Yellow
    $changedFiles | ForEach-Object { Write-Host "      $_" }
}
if ($newFiles) {
    Write-Host "  🆕 File baru:" -ForegroundColor Yellow
    $newFiles | ForEach-Object { Write-Host "      $_" }
}
if ($deletedFiles) {
    Write-Host "  🗑️  File yang akan dihapus dari GitHub:" -ForegroundColor Red
    $deletedFiles | ForEach-Object { Write-Host "      $_" }
    Write-Host ""
    Write-Host "  ⚠️  Perhatian: File-file di atas akan dihapus dari repository GitHub!" -ForegroundColor Yellow
}
Write-Host ""

# Tampilkan ringkasan
git status --short

# 6. Commit
git commit -m $message

# 7. Pastikan branch adalah main
git branch -M $BRANCH

# 8. Pull terbaru dari GitHub untuk menghindari konflik (skip jika first push)
Write-Host "`n📥 Mengecek repository GitHub..." -ForegroundColor Blue

# Cek apakah remote branch sudah ada
$remoteBranchExists = git ls-remote --heads origin $BRANCH 2>$null
if ($remoteBranchExists -match $BRANCH) {
    Write-Host "  Repository GitHub sudah ada, menarik perubahan terbaru..." -ForegroundColor Yellow

    # Retry: koneksi ke GitHub bisa ter-reset (masalah jaringan), coba ulang 3x
    $pulled = $false
    foreach ($i in 1..3) {
        if (git pull --rebase origin $BRANCH) {
            Write-Host "  ✓ Pull berhasil" -ForegroundColor Green
            $pulled = $true
            break
        }
        if ($i -lt 3) {
            Write-Host "  ⚠️  Pull gagal (percobaan $i dari 3), mengulang dalam 5 detik..." -ForegroundColor Yellow
            Start-Sleep -Seconds 5
        }
    }
    if (-not $pulled) {
        Write-Host "❌ GAGAL PULL setelah 3 percobaan." -ForegroundColor Red
        Write-Host "💡 Kemungkinan penyebab:" -ForegroundColor Yellow
        Write-Host "   - Koneksi internet tidak stabil / GitHub di-reset jaringan (coba lagi atau ganti jaringan/hotspot)"
        Write-Host "   - Konflik dengan perubahan di GitHub (selesaikan manual atau: git stash, lalu deploy ulang)"
        exit 1
    }
} else {
    Write-Host "  ⚠️  Ini adalah push pertama ke GitHub (branch '$BRANCH' belum ada)" -ForegroundColor Yellow
    Write-Host "  → Skip pull, langsung push..." -ForegroundColor Yellow
}

# 9. Push ke GitHub (retry 3x karena koneksi ke GitHub bisa ter-reset)
Write-Host "`n📤 Mengirim ke GitHub..." -ForegroundColor Blue
$pushed = $false
foreach ($i in 1..3) {
    if (git push -u origin $BRANCH) {
        $pushed = $true
        break
    }
    if ($i -lt 3) {
        Write-Host "  ⚠️  Push gagal (percobaan $i dari 3), mengulang dalam 5 detik..." -ForegroundColor Yellow
        Start-Sleep -Seconds 5
    }
}
if ($pushed) {
    Write-Host "`n------------------------------------------" -ForegroundColor Green
    Write-Host "✅ BERHASIL! Kode sudah di GitHub." -ForegroundColor Green
    Write-Host "🌐 Cek Plesk Anda, jika Webhook aktif maka web otomatis update." -ForegroundColor Green
    Write-Host "------------------------------------------" -ForegroundColor Green
} else {
    Write-Host "`n❌ GAGAL PUSH setelah 3 percobaan. Periksa koneksi internet Anda (coba ganti jaringan/hotspot) atau cek konflik kode." -ForegroundColor Red
    exit 1
}
