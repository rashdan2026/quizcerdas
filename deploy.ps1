$message = Read-Host "📝 Masukkan pesan commit (atau tekan Enter untuk 'Update otomatis')"
if (-not $message) { $message = "Update otomatis pada $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" }

Write-Host "🚀 Memulai proses upload ke GitHub..." -ForegroundColor Cyan
git add .
git commit -m "$message"
Write-Host "📤 Mengirim kode ke GitHub..." -ForegroundColor Cyan
git push origin main
Write-Host "🎉 SELESAI! Website Anda di Plesk akan segera terupdate." -ForegroundColor Green
