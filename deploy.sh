#!/bin/bash

# Nama Remote dan Branch
REMOTE_URL="https://github.com/rashdan2026/quizcerdas.git"
BRANCH="main"

# 1. Cek apakah folder ini sudah ada Git-nya
if [ ! -d ".git" ]; then
    echo "🗂 Inisialisasi Git baru..."
    git init
    git remote add origin $REMOTE_URL
    echo "# quizcerdas" >> README.md
fi

# 2. Input pesan commit
echo "📝 Masukkan pesan perubahan (contoh: 'tambah fitur login'):"
read message

if [ -z "$message" ]; then
    message="Update rutin: $(date +'%Y-%m-%d %H:%M:%S')"
fi

# 3. Proses Git
echo "🚀 Memproses file..."
git add .
git commit -m "$message"

# 4. Pastikan branch adalah main
git branch -M $BRANCH

# 5. Push ke GitHub
echo "📤 Mengirim ke GitHub..."
if git push -u origin $BRANCH; then
    echo "------------------------------------------"
    echo "✅ BERHASIL! Kode sudah di GitHub."
    echo "🌐 Cek Plesk Anda, jika Webhook aktif maka web otomatis update."
    echo "------------------------------------------"
else
    echo "❌ GAGAL PUSH. Pastikan koneksi internet oke atau tidak ada konflik kode."
fi