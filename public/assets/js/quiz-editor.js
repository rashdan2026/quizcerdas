/* ============================================================
   Quiz Rich Editor — editor ringan berbasis contenteditable
   untuk pertanyaan soal (dosen). Tanpa dependensi eksternal.
   Inisialisasi: textarea.rich-editor otomatis di-upgrade.
   Isi tersinkron kembali ke textarea (form submit tetap sama).
   ============================================================ */
(function () {
    'use strict';

    var LANGS = ['php', 'javascript', 'python', 'java', 'c', 'cpp', 'sql', 'html', 'css', 'json', 'bash', 'plaintext'];
    var SYMBOLS = ['≤', '≥', '≠', '≈', '±', '×', '÷', '→', '←', '∞', '∑', '√', 'π', 'α', 'β', 'Δ', 'θ', '²', '³', 'ⁿ', '₀', '₁', '₂', '&&', '||', '==', '!=', '=>', '->', '<', '>', '&&amp;', ';', '{', '}', '[]', 'NULL', 'TRUE', 'FALSE'];

    var SEL_LANG = ':quiz-editor-lang';

    function escHtml(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function makeToolbar() {
        var tb = document.createElement('div');
        tb.className = 'qe-toolbar';
        var groups = [
            [['p', '¶ Teks', { cmd: 'formatBlock', val: 'p' }], ['h3', 'H3', { cmd: 'formatBlock', val: 'h3' }], ['h4', 'H4', { cmd: 'formatBlock', val: 'h4' }]],
            [['bold', '<b>B</b>', { cmd: 'bold' }], ['italic', '<i>I</i>', { cmd: 'italic' }], ['underline', '<u>U</u>', { cmd: 'underline' }], ['strike', '<s>S</s>', { cmd: 'strikeThrough' }], ['sub', 'X₂', { cmd: 'subscript' }], ['sup', 'X²', { cmd: 'superscript' }]],
            'COLOR',
            [['jleft', '⟸', { cmd: 'justifyLeft' }], ['jcenter', '≡', { cmd: 'justifyCenter' }], ['jright', '⟹', { cmd: 'justifyRight' }], ['jfull', '☰', { cmd: 'justifyFull' }]],
            [['ul', '• Daftar', { cmd: 'insertUnorderedList' }], ['ol', '1. Daftar', { cmd: 'insertOrderedList' }], ['indent', '⇥', { cmd: 'indent' }], ['outdent', '⇤', { cmd: 'outdent' }], ['quote', '❝', { cmd: 'formatBlock', val: 'blockquote' }], ['hr', '—', { cmd: 'insertHorizontalRule' }]],
            'TABLE', 'CODE', 'CHARS', ['clear', '✕ Format', { cmd: 'removeFormat' }]
        ];
        groups.forEach(function (g) {
            if (g === 'COLOR') {
                var wrap = document.createElement('span');
                wrap.className = 'qe-group';
                wrap.innerHTML = '<label class="qe-btn qe-color" title="Warna font">A<input type="color" data-qe-color="fore"></label>' +
                    '<label class="qe-btn qe-color qe-hl" title="Highlight">A<input type="color" data-qe-color="hilite"></label>';
                tb.appendChild(wrap);
                return;
            }
            if (g === 'TABLE') {
                var tbtn = document.createElement('span');
                tbtn.className = 'qe-group';
                tbtn.innerHTML = '<span class="qe-btn" data-qe-table="insert" title="Sisip tabel 3×3">⊞ Tabel</span>' +
                    '<span class="qe-btn" data-qe-table="row+" title="Tambah baris">+B</span>' +
                    '<span class="qe-btn" data-qe-table="row-" title="Hapus baris">−B</span>' +
                    '<span class="qe-btn" data-qe-table="col+" title="Tambah kolom">+K</span>' +
                    '<span class="qe-btn" data-qe-table="col-" title="Hapus kolom">−K</span>';
                tb.appendChild(tbtn);
                return;
            }
            if (g === 'CODE') {
                var sel = document.createElement('select');
                sel.className = 'qe-select';
                sel.dataset.qeCode = 'lang';
                sel.innerHTML = '<option value="">⟨⟩ Kode…</option>' + LANGS.map(function (l) {
                    return '<option value="' + l + '">' + l + '</option>';
                }).join('');
                tb.appendChild(sel);
                return;
            }
            if (g === 'CHARS') {
                var ch = document.createElement('span');
                ch.className = 'qe-group';
                ch.innerHTML = '<span class="qe-btn" data-qe-chars title="Karakter khusus">Ω</span>';
                tb.appendChild(ch);
                return;
            }
            var gr = document.createElement('span');
            gr.className = 'qe-group';
            g.forEach(function (b) {
                var el = document.createElement('span');
                el.className = 'qe-btn';
                el.dataset.qeCmd = b[2].cmd;
                if (b[2].val) el.dataset.qeVal = b[2].val;
                el.innerHTML = b[1];
                el.title = b[0];
                el.setAttribute('unselectable', 'on');
                gr.appendChild(el);
            });
            tb.appendChild(gr);
        });
        return tb;
    }

    function makeCharMenu() {
        var m = document.createElement('div');
        m.className = 'qe-charmenu d-none';
        m.innerHTML = SYMBOLS.map(function (s) {
            return '<span class="qe-char" data-char="' + escHtml(s) + '">' + s + '</span>';
        }).join('');
        return m;
    }

    function initEditor(textarea) {
        if (textarea.dataset.qeReady) return;
        textarea.dataset.qeReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'qe-wrap';
        var toolbar = makeToolbar();
        var charMenu = makeCharMenu();
        var area = document.createElement('div');
        area.className = 'qe-area form-control';
        area.contentEditable = 'true';
        area.setAttribute('data-placeholder', 'Tulis pertanyaan… (boleh memakai formatting, tabel, dan blok kode)');

        textarea.classList.add('d-none');
        textarea.parentNode.insertBefore(wrap, textarea);
        wrap.appendChild(toolbar);
        wrap.appendChild(area);
        wrap.appendChild(charMenu);

        // Isi awal dari textarea (soal lama = plain text → jadikan paragraf).
        var initial = textarea.value;
        area.innerHTML = initial.trim() === '' ? '' :
            (/<(p|div|ul|ol|table|pre|h3|h4|blockquote)\b/i.test(initial) ? initial : '<p>' + escHtml(initial).replace(/\n/g, '<br>') + '</p>');

        function sync() { textarea.value = area.innerHTML; }
        area.addEventListener('input', sync);
        sync();

        // ---- command ----
        toolbar.addEventListener('mousedown', function (e) {
            // Jaga seleksi tidak hilang saat klik toolbar.
            if (e.target.closest('.qe-btn') || e.target.closest('.qe-select')) e.preventDefault();
        });
        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-qe-cmd]');
            if (btn) {
                area.focus();
                document.execCommand(btn.dataset.qeCmd, false, btn.dataset.qeVal || null);
                sync();
                return;
            }
            var tbl = e.target.closest('[data-qe-table]');
            if (tbl) { tableAction(tbl.dataset.qeTable); sync(); return; }
            if (e.target.closest('[data-qe-chars]')) { charMenu.classList.toggle('d-none'); return; }
        });
        toolbar.addEventListener('change', function (e) {
            var sel = e.target.closest('[data-qe-code]');
            if (sel && sel.value) { insertCodeBlock(sel.value); sel.value = ''; sync(); }
        });
        toolbar.addEventListener('input', function (e) {
            var c = e.target.closest('input[data-qe-color]');
            if (c) {
                area.focus();
                document.execCommand(c.dataset.qeColor === 'fore' ? 'foreColor' : 'hiliteColor', false, c.value);
                // Konversi ke style agar lolos sanitizer (foreColor menghasilkan <font>).
                normalizeFont(area);
                sync();
            }
        });
        charMenu.addEventListener('click', function (e) {
            var ch = e.target.closest('.qe-char');
            if (ch) {
                area.focus();
                document.execCommand('insertText', false, ch.dataset.char);
                charMenu.classList.add('d-none');
                sync();
            }
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-qe-chars]') && !e.target.closest('.qe-charmenu')) charMenu.classList.add('d-none');
        });

        // <font color=...> hasil execCommand → <span style="color:...">
        function normalizeFont(root) {
            root.querySelectorAll('font[color]').forEach(function (f) {
                var span = document.createElement('span');
                span.style.color = f.getAttribute('color');
                span.innerHTML = f.innerHTML;
                f.replaceWith(span);
            });
            root.querySelectorAll('font[size],font[face]').forEach(function (f) {
                var span = document.createElement('span');
                span.innerHTML = f.innerHTML;
                f.replaceWith(span);
            });
        }

        // ---- code block ----
        function currentBlock() {
            var sel = window.getSelection();
            if (!sel.rangeCount) return null;
            var node = sel.getRangeAt(0).startContainer;
            while (node && node !== area) {
                if (node.nodeType === 1) {
                    var tag = node.tagName.toLowerCase();
                    if (tag === 'pre') return node;
                    if (tag === 'code' && node.parentElement && node.parentElement.tagName.toLowerCase() === 'pre') return node.parentElement;
                }
                node = node.parentNode;
            }
            return null;
        }
        function insertCodeBlock(lang) {
            var sel = window.getSelection();
            var selectedText = sel && sel.rangeCount && !sel.getRangeAt(0).collapsed ? sel.toString() : '';
            var html = '<pre><code class="language-' + lang + '">' + escHtml(selectedText || 'tulis kode di sini…') + '\n</code></pre><p><br></p>';
            if (currentBlock()) {
                // Sudah di dalam blok kode: ganti bahasanya saja.
                var blk = currentBlock();
                var code = blk.querySelector('code') || blk;
                code.className = 'language-' + lang;
                code.setAttribute('data-language', lang);
                return;
            }
            area.focus();
            document.execCommand('insertHTML', false, html);
        }

        // ---- table actions ----
        function currentCell() {
            var sel = window.getSelection();
            if (!sel.rangeCount) return null;
            var node = sel.getRangeAt(0).startContainer;
            while (node && node !== area) {
                if (node.nodeType === 1 && node.tagName && node.tagName.toLowerCase() === 'td') return node;
                node = node.parentNode;
            }
            return null;
        }
        function tableAction(action) {
            var cell = currentCell();
            if (action === 'insert') {
                var rows = 3, cols = 3, html = '<table><tbody>';
                for (var r = 0; r < rows; r++) {
                    html += '<tr>';
                    for (var c = 0; c < cols; c++) html += '<td>&nbsp;</td>';
                    html += '</tr>';
                }
                html += '</tbody></table><p><br></p>';
                area.focus();
                document.execCommand('insertHTML', false, html);
                return;
            }
            if (!cell) { alert('Klik di dalam tabel terlebih dahulu.'); return; }
            var tr = cell.parentElement;
            var table = tr.parentElement.closest('table');
            if (!table) return;
            var idx = Array.prototype.indexOf.call(tr.children, cell);
            if (action === 'row+') { table.querySelector('tbody') ? null : null; var clone = tr.cloneNode(true); tr.parentNode.insertBefore(clone, tr.nextSibling); }
            if (action === 'row-') {
                if (tr.parentNode.children.length > 1) tr.remove();
            }
            if (action === 'col+') {
                table.querySelectorAll('tr').forEach(function (row) {
                    var td = document.createElement(row.children[0].tagName.toLowerCase());
                    td.innerHTML = '&nbsp;';
                    row.insertBefore(td, row.children[Math.min(idx + 1, row.children.length)] || null);
                });
            }
            if (action === 'col-') {
                if (tr.children.length > 1) {
                    table.querySelectorAll('tr').forEach(function (row) {
                        var target = row.children[Math.min(idx, row.children.length - 1)];
                        if (target) target.remove();
                    });
                }
            }
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
