/* ============================================================
   Quiz Editor — WYSIWYG berbasis Quill.js 2.x (lokal).
   Dosen menulis soal seperti dokumen biasa; HTML hanya format
   penyimpanan di belakang layar. Inisialisasi otomatis pada
   textarea.rich-editor; isi tersinkron kembali ke textarea.
   ============================================================ */
(function () {
    'use strict';

    // Toolbar ringkas & mudah dipahami dosen.
    var TOOLBAR = [
        [{ header: [3, 4, false] }],
        ['bold', 'italic', 'underline', 'strike', { script: 'sub' }, { script: 'super' }],
        [{ color: [] }, { background: [] }],
        [{ align: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
        ['blockquote', 'code-block'],
        ['table-insert', 'table-row', 'table-col'],
        ['clean']
    ];

    function escHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ---- Metadata bahasa Code Block -------------------------------------
    // Quill 2.0.3 membundel blot SyntaxCodeBlock (modules/syntax) yang
    // mendukung nilai format STRING: quill.format('code-block', 'sql')
    // menyimpan data-language="sql" pada blot — metadata bahasa menjadi
    // bagian format internal Delta, bukan atribut HTML sementara.
    // Modul syntax penuh TIDAK diaktifkan (tidak wajib hljs, tanpa auto-detect).
    var LANGUAGES = ['sql', 'php', 'javascript', 'python', 'java', 'c', 'cpp', 'html', 'css', 'json', 'bash', 'plaintext'];
    var LANG_ALIASES = {
        js: 'javascript', py: 'python', 'c++': 'cpp', sh: 'bash', shell: 'bash',
        plain: 'plaintext', text: 'plaintext', plaintext: 'plaintext'
    };

    function normLang(raw) {
        if (typeof raw !== 'string') return null;
        var l = raw.toLowerCase().trim();
        if (LANG_ALIASES[l]) return LANG_ALIASES[l];
        return LANGUAGES.indexOf(l) !== -1 ? l : null;
    }

    function langFromNode(node) {
        if (!node || !node.getAttribute) return null;
        var cls = node.getAttribute('class') || '';
        var m = /(?:^|\s)language-([a-z0-9#+.\-]+)/i.exec(cls);
        if (m) {
            var norm = normLang(m[1]);
            if (norm) return norm;
        }
        return normLang(node.getAttribute('data-language'));
    }

    // Daftarkan blot code-block berbasis bahasa (sekali, sebelum Quill dibuat).
    function enableLanguageCodeBlock() {
        if (typeof Quill === 'undefined') return;
        if (Quill.__qeLangEnabled) return;
        try {
            var Syntax = Quill.import('modules/syntax');
            if (Syntax && typeof Syntax.register === 'function') Syntax.register();
            Quill.__qeLangEnabled = true;
        } catch (err) {
            console.error('QuizEditor: gagal mendaftarkan code-block bahasa —', err);
        }
    }
    enableLanguageCodeBlock();

    // Matcher clipboard: pertahankan bahasa dari <pre data-language>,
    // <code class="language-x"> (format lama) saat muat soal & paste.
    // Atribut diterapkan pada SEMUA op string (termasuk "\n" penentu blok).
    function langMatcher(node, delta) {
        var lang = langFromNode(node);
        if (!lang && node && node.tagName === 'PRE') {
            var code = node.querySelector ? node.querySelector('code') : null;
            lang = code ? langFromNode(code) : null;
        }
        if (!lang) return delta;
        delta.ops.forEach(function (op) {
            if (op.insert && typeof op.insert === 'string') {
                op.attributes = Object.assign({}, op.attributes, { 'code-block': lang });
            }
        });
        return delta;
    }

    function currentTd(quill) {
        var sel = window.getSelection();
        if (!sel.rangeCount) return null;
        var node = sel.getRangeAt(0).startContainer;
        while (node && node !== quill.root) {
            if (node.nodeType === 1 && node.tagName && node.tagName.toLowerCase() === 'td') return node;
            node = node.parentNode;
        }
        return null;
    }

    // Operasi tabel ringan langsung di DOM container Quill (tanpa plugin).
    function tableAction(quill, action) {
        var cell = currentTd(quill);
        if (action === 'insert') {
            var html = '<table><tbody>';
            for (var r = 0; r < 3; r++) {
                html += '<tr>';
                for (var c = 0; c < 3; c++) html += '<td>&nbsp;</td>';
                html += '</tr>';
            }
            html += '</tbody></table>';
            var range = quill.getSelection(true);
            quill.clipboard.dangerouslyPasteHTML(range.index, html, 'user');
            return;
        }
        if (!cell) { alert('Klik di dalam tabel terlebih dahulu.'); return; }
        var tr = cell.parentElement;
        var table = tr.closest('table');
        if (!table) return;
        var idx = Array.prototype.indexOf.call(tr.children, cell);
        if (action === 'row') {
            if (tr.parentNode.children.length <= 1) return;
            tr.remove();
            return;
        }
        if (action === 'col') {
            if (tr.children.length <= 1) return;
            table.querySelectorAll('tr').forEach(function (row) {
                var target = row.children[Math.min(idx, row.children.length - 1)];
                if (target) target.remove();
            });
            return;
        }
        var mode = action.split(':')[0]; // 'row+' | 'col+'
        if (mode === 'row+') {
            var clone = tr.cloneNode(true);
            clone.querySelectorAll('td, th').forEach(function (td) { td.innerHTML = '&nbsp;'; });
            tr.parentNode.insertBefore(clone, tr.nextSibling);
            return;
        }
        if (mode === 'col+') {
            table.querySelectorAll('tr').forEach(function (row) {
                var td = document.createElement(row.children[0].tagName.toLowerCase());
                td.innerHTML = '&nbsp;';
                row.insertBefore(td, row.children[Math.min(idx + 1, row.children.length)] || null);
            });
        }
    }

    function initEditor(textarea) {
        if (textarea.dataset.qeReady) return;
        if (typeof Quill === 'undefined') {
            console.error('QuizEditor: Quill.js tidak termuat.');
            return;
        }
        textarea.dataset.qeReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'qe-wrap';
        var editorDiv = document.createElement('div');
        editorDiv.className = 'qe-editor';

        textarea.classList.add('d-none');
        textarea.parentNode.insertBefore(wrap, textarea);
        wrap.appendChild(editorDiv);

        try {
        var quill = new Quill(editorDiv, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: TOOLBAR,
                    handlers: {
                        'table-insert': function () { tableAction(quill, 'insert'); },
                        'table-row': function () { tableAction(quill, 'row'); },
                        'table-col': function () { tableAction(quill, 'col'); },
                        'table-row-add': function () { tableAction(quill, 'row+'); },
                        'table-col-add': function () { tableAction(quill, 'col+'); }
                    }
                }
            }
        });

        // ---- clipboard matcher metadata bahasa (muat soal & paste) ----
        quill.clipboard.addMatcher('PRE', langMatcher);
        quill.clipboard.addMatcher('CODE', langMatcher);

        // ---- muat isi awal: HTML (soal baru/AI) ATAU plain text (soal lama) ----
        var initial = textarea.value;
        if (initial.trim() !== '') {
            if (/<(p|div|ul|ol|table|pre|h2|h3|h4|blockquote|br)\b/i.test(initial)) {
                quill.clipboard.dangerouslyPasteHTML(initial, 'silent');
            } else {
                // Soal lama teks biasa: tampilkan apa adanya sebagai paragraf.
                quill.clipboard.dangerouslyPasteHTML(
                    '<p>' + escHtml(initial).replace(/\n/g, '<br>') + '</p>',
                    'silent'
                );
            }
        }

        // ---- pilih bahasa code block (independen per blok) ----
        var langSelect = document.createElement('select');
        langSelect.className = 'ql-qe-tool qe-lang';
        langSelect.title = 'Bahasa code block (klik di dalam blok kode, lalu pilih)';
        var optAny = document.createElement('option');
        optAny.value = ''; optAny.textContent = 'Bahasa…';
        langSelect.appendChild(optAny);
        LANGUAGES.forEach(function (lang) {
            var o = document.createElement('option');
            o.value = lang;
            o.textContent = lang === 'plaintext' ? 'plain text' : lang;
            langSelect.appendChild(o);
        });
        langSelect.addEventListener('change', function () {
            var lang = normLang(langSelect.value);
            if (!lang) return;
            quill.format('code-block', lang);
            quill.focus();
        });
        function currentCodeLang() {
            var range = quill.getSelection();
            if (!range) return null;
            var f = quill.getFormat(range.index, Math.max(range.length, 1));
            if (!f || !('code-block' in f)) return null;
            return typeof f['code-block'] === 'string' ? normLang(f['code-block']) : 'plaintext';
        }
        quill.on('selection-change', function () {
            langSelect.value = currentCodeLang() || '';
        });

        // ---- sinkron balik ke textarea (form submit tetap sama) ----
        // Quill merender code-block sebagai div (ql-code-block-container);
        // konversi kembali menjadi <pre data-language="x"> agar metadata
        // bahasa ikut tersimpan & dikenali HtmlSanitizer.
        function editorHtmlToStorage() {
            var clone = quill.root.cloneNode(true);
            var containers = clone.querySelectorAll('.ql-code-block-container');
            if (containers.length === 0) return quill.root.innerHTML;
            Array.prototype.forEach.call(containers, function (c) {
                var lines = c.querySelectorAll('.ql-code-block');
                var text = Array.prototype.map.call(lines, function (l) { return l.textContent; }).join('\n');
                var src = lines[0] || c.querySelector('[data-language]');
                var lang = (src && normLang(src.getAttribute('data-language'))) || 'plaintext';
                var pre = document.createElement('pre');
                pre.setAttribute('data-language', lang);
                pre.textContent = text;
                c.parentNode.replaceChild(pre, c);
            });
            return clone.innerHTML;
        }
        function sync() {
            if (quill.getText().trim() === '' && quill.root.querySelectorAll('table').length === 0) {
                textarea.value = '';
                return;
            }
            textarea.value = editorHtmlToStorage();
        }
        quill.on('text-change', sync);
        sync();

        // ---- tombol tambah baris/kolom pada toolbar tabel ----
        var toolbarEl = quill.getModule('toolbar').container;
        // Dropdown bahasa code block: tepat setelah tombol code-block.
        var codeBtn = toolbarEl.querySelector('.ql-code-block');
        if (codeBtn && codeBtn.parentNode) {
            codeBtn.parentNode.insertBefore(langSelect, codeBtn.nextSibling);
        } else {
            toolbarEl.appendChild(langSelect);
        }
        function addTool(title, label, handler) {
            var existing = toolbarEl.querySelector('.qe-add');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ql-qe-tool qe-add';
            btn.title = title;
            btn.textContent = label;
            btn.addEventListener('click', function (e) { e.preventDefault(); handler(); });
            (existing ? existing.parentNode : toolbarEl).appendChild(btn);
            return btn;
        }
        var rowAdd = addTool('Tambah baris (klik di dalam tabel)', '+ Baris', function () { tableAction(quill, 'row+'); });
        var colAdd = addTool('Tambah kolom (klik di dalam tabel)', '+ Kolom', function () { tableAction(quill, 'col+'); });
        // Pindahkan setelah tombol hapus kolom.
        var colDel = toolbarEl.querySelector('.ql-table-col');
        if (colDel && colDel.parentNode) {
            colDel.parentNode.insertBefore(rowAdd, colDel.nextSibling);
            colDel.parentNode.insertBefore(colAdd, rowAdd.nextSibling);
        }

        // Label tombol tabel agar tidak menampilkan teks mentah CSS content.
        ['table-insert', 'table-row', 'table-col'].forEach(function (name) {
            var b = toolbarEl.querySelector('.ql-' + name);
            if (b) {
                b.style.fontSize = '.72rem';
                b.style.width = 'auto';
                b.style.padding = '0 6px';
                b.textContent = name === 'table-insert' ? 'Tabel' : (name === 'table-row' ? '− Baris' : '− Kolom');
            }
        });
        } catch (err) {
            // Fallback aman: kembalikan textarea (isi soal tetap bisa diedit &
            // disimpan) — jangan sampai HTML mentah menjadi satu-satunya tampilan.
            console.error('QuizEditor: gagal inisialisasi Quill —', err);
            wrap.remove();
            textarea.classList.remove('d-none');
        }
    }

    function initAll(root) {
        (root || document).querySelectorAll('textarea.rich-editor').forEach(initEditor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }
    window.QuizRichEditor = { initAll: initAll };
})();
