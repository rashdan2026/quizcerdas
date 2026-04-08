#!/bin/bash

# ============================================
# DEPLOYMENT SCRIPT - QuizCerdas Application
# ============================================
# Script ini hanya mengupload file-file penting aplikasi
# File testing, temporary, dan non-essential akan diabaikan
# 
# FITUR SMART DEPLOYMENT:
# - Hanya upload jika ada perubahan/new file
# - Skip jika semua file sudah up-to-date
# - Auto-pull dari GitHub sebelum push (hindari konflik)
# - Tampilkan detail file yang akan di-upload
# ============================================

# Nama Remote dan Branch
REMOTE_URL="https://github.com/rashdan2026/quizcerdas.git"
BRANCH="main"

# Warna output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 1. Cek apakah folder ini sudah ada Git-nya
if [ ! -d ".git" ]; then
    echo -e "${BLUE}🗂  Inisialisasi Git baru...${NC}"
    git init
    git remote add origin $REMOTE_URL
    echo "# quizcerdas" >> README.md
fi

# 2. Input pesan commit
echo -e "${YELLOW}📝 Masukkan pesan perubahan (contoh: 'tambah fitur login'):${NC}"
read message

if [ -z "$message" ]; then
    message="Update rutin: $(date +'%Y-%m-%d %H:%M:%S')"
fi

# 3. Tampilkan file yang akan diupload
echo -e "\n${BLUE}📋 Menyiapkan file untuk deployment...${NC}"
echo -e "${YELLOW}✓ File yang akan di-upload:${NC}"
echo "  - app/ (Controllers, Models, Views, Config, Database, dll)"
echo "  - public/ (index.php, .htaccess, assets)"
echo "  - system/ (CodeIgniter framework)"
echo "  - writable/ (.htaccess files only)"
echo "  - composer.json & composer.lock"
echo "  - .env.example"
echo "  - index.php (root)"
echo "  - README.md, LICENSE, UPLOAD_INSTRUCTIONS.md"
echo ""
echo -e "${RED}✗ File yang TIDAK akan di-upload:${NC}"
echo "  - .env (keamanan)"
echo "  - vendor/ (composer dependencies)"
echo "  - test_*.php, *_test.php (file testing)"
echo "  - debug*.php, update_*.php (file debug)"
echo "  - error.jpg, gambar.png (media non-essential)"
echo "  - deploy.sh, deploy.ps1 (deployment scripts)"
echo "  - .qwen/, .vscode/, .idea/ (editor configs)"
echo "  - writable/logs/*, writable/session/*, writable/cache/*, writable/uploads/*"
echo ""

# 4. Reset staging area dan tambahkan file sesuai .gitignore
echo -e "${BLUE}🚀 Memproses file...${NC}"
git reset
git add .

# 5. Cek apakah ada perubahan yang perlu di-upload
echo -e "\n${BLUE}🔍 Mengecek perubahan...${NC}"
CHANGED_FILES=$(git diff --cached --name-only)
NEW_FILES=$(git ls-files --others --exclude-standard)
DELETED_FILES=$(git diff --cached --name-only --diff-filter=D)

if [ -z "$CHANGED_FILES" ] && [ -z "$NEW_FILES" ] && [ -z "$DELETED_FILES" ]; then
    echo -e "${GREEN}✅ Tidak ada perubahan baru. Semua file sudah up-to-date di GitHub!${NC}"
    exit 0
fi

# Tampilkan summary file yang akan di-commit
echo -e "${GREEN}📊 File yang akan di-upload:${NC}"
if [ -n "$CHANGED_FILES" ]; then
    echo -e "${YELLOW}  ✏️  File yang dimodifikasi:${NC}"
    echo "$CHANGED_FILES" | grep -v '^$' | sed 's/^/      /'
fi
if [ -n "$NEW_FILES" ]; then
    echo -e "${YELLOW}  🆕 File baru:${NC}"
    echo "$NEW_FILES" | sed 's/^/      /'
fi
if [ -n "$DELETED_FILES" ]; then
    echo -e "${RED}  🗑️  File yang akan dihapus dari GitHub:${NC}"
    echo "$DELETED_FILES" | sed 's/^/      /'
    echo ""
    echo -e "${YELLOW}  ⚠️  Perhatian: File-file di atas akan dihapus dari repository GitHub!${NC}"
fi
echo ""

# Tampilkan ringkasan
git status --short

# 6. Commit
git commit -m "$message"

# 7. Pastikan branch adalah main
git branch -M $BRANCH

# 8. Pull terbaru dari GitHub untuk menghindari konflik (skip jika first push)
echo -e "\n${BLUE}📥 Mengecek repository GitHub...${NC}"

# Cek apakah remote branch sudah ada
if git ls-remote --heads origin $BRANCH | grep -q $BRANCH; then
    echo -e "${YELLOW}  Repository GitHub sudah ada, menarik perubahan terbaru...${NC}"
    if git pull --rebase origin $BRANCH; then
        echo -e "${GREEN}  ✓ Pull berhasil${NC}"
    else
        echo -e "${RED}❌ GAGAL PULL. Ada konflik dengan perubahan di GitHub.${NC}"
        echo -e "${YELLOW}💡 Tips: Selesaikan konflik manual atau stash perubahan lokal Anda.${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}  ⚠️  Ini adalah push pertama ke GitHub (branch '$BRANCH' belum ada)${NC}"
    echo -e "${YELLOW}  → Skip pull, langsung push...${NC}"
fi

# 9. Push ke GitHub
echo -e "\n${BLUE}📤 Mengirim ke GitHub...${NC}"
if git push -u origin $BRANCH; then
    echo -e "\n${GREEN}------------------------------------------"
    echo -e "✅ BERHASIL! Kode sudah di GitHub."
    echo -e "🌐 Cek Plesk Anda, jika Webhook aktif maka web otomatis update."
    echo -e "------------------------------------------${NC}"
else
    echo -e "\n${RED}❌ GAGAL PUSH. Pastikan koneksi internet oke atau tidak ada konflik kode.${NC}"
    exit 1
fi
