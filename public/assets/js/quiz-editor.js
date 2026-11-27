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

        // ---- sinkron balik ke textarea (form submit tetap sama) ----
        function sync() {
            if (quill.getText().trim() === '' && quill.root.querySelectorAll('table').length === 0) {
                textarea.value = '';
                return;
            }
            textarea.value = quill.root.innerHTML;
        }
        quill.on('text-change', sync);
        sync();

        // ---- tombol tambah baris/kolom pada toolbar tabel ----
        var toolbarEl = quill.getModule('toolbar').container;
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
