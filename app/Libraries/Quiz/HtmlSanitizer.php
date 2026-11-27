<?php

namespace App\Libraries\Quiz;

/**
 * Sanitizer HTML berbasis allowlist untuk konten soal (input dosen & hasil AI).
 *
 * Prinsip:
 * - ALLOWLIST tag/atribut/CSS: hanya yang tercantum yang lolos, sisanya dihapus.
 * - Tag berbahaya (script, iframe, object, embed, form, dsb) dihapus SELURUHNYA
 *   beserta isinya; tag diperbolehkan lain yang tidak dikenal di-unwrap.
 * - Isi blok kode (<pre><code>) adalah TEKS, bukan HTML: karakternya
 *   dipertahankan persis dan hanya di-escape saat output — tidak pernah diubah.
 * - SQL injection BUKAN tugas kelas ini: penyimpanan selalu memakai Query
 *   Builder (parameter binding). Kelas ini mencegah XSS saat HTML dirender.
 *
 * Ringan: DOMDocument bawaan PHP (tanpa dependensi eksternal), aman untuk
 * shared hosting.
 */
final class HtmlSanitizer
{
    /** Panjang maksimum konten pertanyaan (karakter) setelah sanitasi. */
    public const MAX_QUESTION_CHARS = 10000;

    /** Panjang maksimum satu opsi / penjelasan (karakter). */
    public const MAX_OPTION_CHARS = 2000;

    /** Tag blok yang boleh muncul pada pertanyaan. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
        'h3', 'h4', 'ul', 'ol', 'li', 'blockquote',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'pre', 'code', 'span', 'div',
    ];

    /** Tag yang dihapus BESERTA ISINYA (bisa mengeksekusi / menyisipkan konten). */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'video', 'audio',
        'canvas', 'frame', 'frameset', 'applet', 'noscript', 'template', 'title', 'head',
    ];

    /** Atribut global yang boleh dipertahankan setelah divalidasi nilainya. */
    private const ALLOWED_ATTRS = ['style', 'class', 'data-language'];

    /** Properti CSS yang diizinkan pada atribut style. */
    private const ALLOWED_CSS_PROPS = [
        'color', 'background-color', 'text-align', 'font-weight', 'font-style', 'text-decoration',
    ];

    /** Nilai CSS yang mencurigakan (ditolak). */
    private const CSS_FORBIDDEN = ['url(', 'expression', 'behavior', 'javascript', '@import', 'vbscript', '<', '>', '"', "'"];

    // Kurung diperbolehkan untuk nilai warna rgb()/rgba(); url() tetap diblokir blacklist.
    private const CSS_VALUE_RE = '/^[#0-9a-zA-Z ,.%()\-]+$/';

    /** Bahasa code block yang diizinkan (metadata divalidasi; di luar daftar → plaintext). */
    private const ALLOWED_LANGS = [
        'sql', 'php', 'javascript', 'python', 'java', 'c', 'cpp', 'html', 'css',
        'json', 'bash', 'xml', 'plaintext',
    ];

    /** Alias bahasa (hasil paste/AI/editor) dinormalisasi sebelum validasi. */
    private const LANG_ALIASES = [
        'plain' => 'plaintext', 'text' => 'plaintext', 'plaintext' => 'plaintext',
        'js' => 'javascript', 'py' => 'python', 'c++' => 'cpp',
        'sh' => 'bash', 'shell' => 'bash', 'zsh' => 'bash',
    ];

    /** Normalisasi + validasi bahasa code block; nilai tidak dikenal → plaintext. */
    private static function normalizeLanguage(string $raw): string
    {
        $lang = strtolower(trim($raw));
        if (isset(self::LANG_ALIASES[$lang])) {
            $lang = self::LANG_ALIASES[$lang];
        }
        if (preg_match('/^[a-z0-9#+.\-]+$/', $lang) !== 1 || ! in_array($lang, self::ALLOWED_LANGS, true)) {
            return 'plaintext';
        }

        return $lang;
    }

    // ==================== API publik ====================

    /**
     * Sanitasi HTML pertanyaan (blok penuh: boleh tabel, list, pre/code).
     * Input plain text (tanpa tag) dikembalikan apa adanya — kompatibel
     * dengan soal lama.
     */
    public static function sanitizeQuestion(string $html): string
    {
        return self::sanitize($html, false);
    }

    /**
     * Sanitasi konten inline (opsi jawaban / penjelasan): tag blok & container
     * dibuka-unwrap, hanya formatting inline yang dipertahankan.
     */
    public static function sanitizeInline(string $html): string
    {
        return self::sanitize($html, true);
    }

    /** Apakah string mengandung tag HTML yang dikenal (vs plain text lama). */
    public static function isHtml(string $text): bool
    {
        // Tanpa \s setelah '<': plain text seperti "a < b > c" bukan tag.
        return (bool) preg_match('/<\/?(' . implode('|', self::ALLOWED_TAGS) . ')(\s|\/?>)/i', $text);
    }

    /**
     * Siap aman untuk di-echo mentah ke view: re-sanitasi (validasi render),
     * atau — bila plain text lama — escape + nl2br agar tampil apa adanya.
     */
    public static function render(string $stored): string
    {
        if (! self::isHtml($stored)) {
            return nl2br(htmlspecialchars($stored, ENT_QUOTES, 'UTF-8'));
        }

        return self::sanitize($stored, false);
    }

    /** Versi teks polos untuk duplicate detection / perbandingan. */
    public static function toPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/(p|div|li|h3|h4|tr|blockquote)>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }

    // ==================== Inti sanitizer ====================

    private static function sanitize(string $html, bool $inlineOnly): string
    {
        $html = self::scrubEncoding($html);
        if ($html === '') {
            return '';
        }

        // Blok kode diekstrak SEBELUM parsing DOM: isinya adalah teks kode mentah
        // (mis. "<?php", "a < b"), bukan HTML — parser DOM akan merusaknya.
        // Isinya dipulihkan verbatim pada langkah terakhir.
        $salt     = bin2hex(random_bytes(4));
        $preTags  = [];
        $html     = preg_replace_callback(
            '/<pre\b[^>]*>.*?<\/pre\s*>/is',
            static function (array $m) use (&$preTags, $salt): string {
                $token      = '@@QZPRE' . $salt . count($preTags) . '@@';
                $preTags[$token] = $m[0];

                return $token;
            },
            $html
        ) ?? $html;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $dom->loadHTML(
            '<?xml encoding="UTF-8"><!DOCTYPE html><body>' . $html . '</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        $out  = '';
        if ($ok && $body !== null) {
            foreach ($body->childNodes as $child) {
                $out .= self::renderNode($dom, $child, $inlineOnly);
            }
        } else {
            // Tidak bisa diproses dengan aman → simpan sebagai plain text hasil strip.
            $out = htmlspecialchars(self::toPlainText($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Pulihkan blok kode (isi utuh, hanya di-escape untuk keamanan render).
        foreach ($preTags as $token => $raw) {
            $out = str_replace($token, self::renderCodeBlockRaw($raw), $out);
        }

        return trim($out);
    }

    /** Render satu node menjadi HTML bersih (rekursif). */
    private static function renderNode(\DOMDocument $dom, \DOMNode $node, bool $inlineOnly): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        if (! $node instanceof \DOMElement) {
            // Comment / CDATA / doctype: buang.
            return '';
        }

        $tag = strtolower($node->tagName);

        // 1) Tag berbahaya: buang node beserta seluruh isinya.
        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            return '';
        }

        // 2) Blok kode: isi dipertahankan PERSIS sebagai teks.
        if ($tag === 'pre') {
            return self::renderCodeBlock($node);
        }

        // 3) Tag di luar allowlist: buka (pertahankan anak-anaknya).
        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            $inner = '';
            foreach ($node->childNodes as $child) {
                $inner .= self::renderNode($dom, $child, $inlineOnly);
            }

            return $inner;
        }

        // 4) Inline-only: tag container/blok tidak boleh muncul — pakai isinya saja.
        if ($inlineOnly && in_array($tag, ['p', 'div', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'pre', 'hr'], true)) {
            if ($tag === 'br') {
                return ' ';
            }
            $inner = '';
            foreach ($node->childNodes as $child) {
                $inner .= self::renderNode($dom, $child, true);
            }

            return $inner;
        }

        // 5) Tag diperbolehkan: render dengan atribut tervalidasi.
        // Konvensi Quill.js: daftar BULLET ditulis <ol data-list="bullet"> pada
        // <li> — konversi menjadi <ul> agar semantik daftar tetap benar.
        $renderTag = $tag;
        if ($tag === 'ol' && self::isBulletList($node)) {
            $renderTag = 'ul';
        }

        $attrs = self::renderAttrs($node, $tag);
        $open  = '<' . $renderTag . $attrs . '>';

        if ($tag === 'br' || $tag === 'hr') {
            return $open;
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= self::renderNode($dom, $child, $inlineOnly);
        }

        // Tag kosong yang butuh konten minimal agar struktur tetap valid.
        if ($inner === '' && in_array($tag, ['p', 'td', 'th', 'li', 'h3', 'h4', 'div', 'span'], true)) {
            return $open . '&nbsp;';
        }

        return $open . $inner . '</' . $renderTag . '>';
    }

    /** Apakah <ol> berisi <li data-list="bullet|checked|unchecked"> (keluaran Quill). */
    private static function isBulletList(\DOMElement $ol): bool
    {
        foreach ($ol->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'li') {
                $dl = strtolower(trim($child->getAttribute('data-list')));

                return in_array($dl, ['bullet', 'checked', 'unchecked'], true);
            }
        }

        return false;
    }

    /**
     * Blok kode dari string mentah (hasil ekstraksi regex): isi dipulihkan
     * PERSIS sebagai teks — entity di-decode sekali, tag formatting liar yang
     * mungkin dibungkus AI dibuang, lalu seluruh isi di-escape untuk output.
     * Karakter < > & ; { } " ' tetap utuh secara semantik.
     */
    private static function renderCodeBlockRaw(string $rawPre): string
    {
        // Bahasa dari atribut tag pembuka pre/code.
        $language = 'plaintext';
        if (preg_match('/language-([a-z0-9#+.\-]+)/i', $rawPre, $m)) {
            $language = self::normalizeLanguage($m[1]);
        } elseif (preg_match('/data-language="([a-z0-9#+.\-]+)"/i', $rawPre, $m)) {
            // Konvensi Quill.js: <pre data-language="...">
            $language = self::normalizeLanguage($m[1]);
        }

        // Buang tag pembuka/penutup pre & code.
        $inner = preg_replace('/^\s*<pre\b[^>]*>/i', '', $rawPre) ?? $rawPre;
        $inner = preg_replace('/<\/pre\s*>\s*$/i', '', $inner) ?? $inner;
        $inner = preg_replace('/<\/?code\b[^>]*>/i', '', $inner) ?? $inner;

        // Decode sekali: input bisa berupa kode yang sudah di-escape (dari
        // editor/AI) atau kode mentah. Setelah ini teks kode adalah murni.
        $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Buang tag formatting liar yang tidak wajar di dalam kode.
        $inner = preg_replace('/<\/?(?:p|div|span|br|b|strong|i|em|u|font|hr|sub|sup)\b[^>]*>/i', '', $inner) ?? $inner;

        return '<pre><code class="language-' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(rtrim($inner, "\n"), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            . '</code></pre>';
    }

    /**
     * Blok kode dari node DOM (jalur fallback bila pre lolos ekstraksi regex):
     * isi diambil sebagai textContent murni dan di-escape saat output.
     */
    private static function renderCodeBlock(\DOMElement $pre): string
    {
        $language = self::codeLanguage($pre);
        $text     = $pre->textContent ?? '';

        return '<pre><code class="language-' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(rtrim($text, "\n"), ENT_QUOTES | ENT_XML1, 'UTF-8')
            . '</code></pre>';
    }

    /** Ambil bahasa syntax dari data-language/class pada pre atau code di dalamnya. */
    private static function codeLanguage(\DOMElement $pre): string
    {
        foreach ([$pre] + iterator_to_array($pre->getElementsByTagName('code')) as $el) {
            $dl = trim((string) $el->getAttribute('data-language'));
            if ($dl !== '') {
                return self::normalizeLanguage($dl);
            }
            $class = trim((string) $el->getAttribute('class'));
            if ($class !== '' && preg_match('/language-([a-z0-9#+.\-]+)/i', $class, $m)) {
                return self::normalizeLanguage($m[1]);
            }
        }

        return 'plaintext';
    }

    /** Atribut tervalidasi untuk satu tag. */
    private static function renderAttrs(\DOMElement $node, string $tag): string
    {
        $out        = '';
        $styleDecls = [];

        foreach ($node->attributes as $attr) {
            $name = strtolower($attr->name);

            // Semua event handler & atribut asing otomatis gugur (tidak ada di daftar).
            if (! in_array($name, self::ALLOWED_ATTRS, true)) {
                continue;
            }
            if (str_starts_with($name, 'on')) {
                continue;
            }

            if ($name === 'style') {
                array_push($styleDecls, ...self::styleDecls($attr->value));
                continue;
            }

            if ($name === 'class') {
                // Kelas editor Quill (ql-align-*, ql-indent-*) dikonversi ke style.
                array_push($styleDecls, ...self::quillClassToStyles($attr->value));
                $allowed = self::sanitizeClasses($attr->value, $tag);
                if ($allowed !== '') {
                    $out .= ' class="' . htmlspecialchars($allowed, ENT_QUOTES, 'UTF-8') . '"';
                }
                continue;
            }

            // data-language: hanya pada pre/code, nilai dibatasi karakter aman.
            if ($name === 'data-language' && in_array($tag, ['pre', 'code'], true)) {
                $val = strtolower(preg_replace('/[^a-z0-9#+.\-]/i', '', $attr->value) ?? '');
                if ($val !== '') {
                    $out .= ' data-language="' . $val . '"';
                }
            }
        }

        if ($styleDecls !== []) {
            $out = ' style="' . htmlspecialchars(implode('; ', array_unique($styleDecls)), ENT_QUOTES, 'UTF-8') . '"' . $out;
        }

        return $out;
    }

    /** Hanya kelas language-* pada code; kelas editor Quill dikonversi ke style. */
    private static function sanitizeClasses(string $raw, string $tag): string
    {
        $keep = [];
        foreach (preg_split('/\s+/', trim($raw)) ?: [] as $class) {
            if ($class === '') {
                continue;
            }
            if ($tag === 'code' && preg_match('/^language-[a-z0-9#+.\-]+$/i', $class)) {
                $keep[] = strtolower($class);
            }
        }

        return implode(' ', array_unique($keep));
    }

    /**
     * Konversi class khusus Quill.js menjadi style standar:
     * - ql-align-center/right/justify -> style text-align
     * - ql-indent-N                   -> style padding-left (N x 1.5em)
     * Kelas Quill tidak pernah disimpan mentah ke database.
     *
     * @return list<string> deklarasi CSS tervalidasi
     */
    private static function quillClassToStyles(string $raw): array
    {
        $styles = [];
        foreach (preg_split('/\s+/', trim($raw)) ?: [] as $class) {
            $class = strtolower($class);
            if (preg_match('/^ql-align-(center|right|justify)$/', $class, $m)) {
                $styles[] = 'text-align: ' . $m[1];
            } elseif (preg_match('/^ql-indent-([1-8])$/', $class, $m)) {
                $em = number_format(((int) $m[1]) * 1.5, 1, '.', '');
                $styles[] = 'padding-left: ' . $em . 'em';
            }
        }

        return $styles;
    }

    /** Filter atribut style terhadap allowlist properti + validasi nilai; hasil berupa daftar deklarasi. */
    private static function styleDecls(string $raw): array
    {
        $keep = [];
        foreach (explode(';', $raw) as $decl) {
            if (! str_contains($decl, ':')) {
                continue;
            }
            [$prop, $value] = explode(':', $decl, 2);
            $prop  = strtolower(trim($prop));
            $value = strtolower(trim($value));
            if ($prop === '' || $value === '') {
                continue;
            }
            if (! in_array($prop, self::ALLOWED_CSS_PROPS, true)) {
                continue;
            }
            if (! preg_match(self::CSS_VALUE_RE, $value)) {
                continue;
            }
            foreach (self::CSS_FORBIDDEN as $bad) {
                if (str_contains($value, $bad)) {
                    continue 2;
                }
            }
            $keep[] = $prop . ': ' . $value;
        }

        return $keep;
    }

    /** Normalisasi encoding input: byte UTF-8 invalid akan merusak DOM parser. */
    private static function scrubEncoding(string $text): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);

        return ($clean === false) ? mb_convert_encoding($text, 'UTF-8', 'UTF-8') : $clean;
    }
}
