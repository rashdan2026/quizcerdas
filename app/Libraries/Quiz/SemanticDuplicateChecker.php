<?php

namespace App\Libraries\Quiz;

/**
 * Pemeriksaan duplikasi soal — berlapis, konteks dihitung SEKALI per batch.
 *
 * Tahap 1 – Normalisasi teks: huruf kecil, tanda baca tak bermakna dibuang,
 *           spasi berlebih diratakan; istilah teknis (c++, tcp/ip) dipertahankan.
 * Tahap 2 – Pemeriksaan lokal (duplikasi yang jelas, presisi tinggi):
 *           fingerprint teks (identik modulo spasi/tanda baca) dan fingerprint
 *           urutan kata isi. Soal lama dinormalisasi satu kali di add(); setiap
 *           kandidat hanya dibandingkan ke indeks yang sudah dihitung. Soal yang
 *           mirip kosakatanya (skor token >= ambang bawah) BELUM diputus di sini.
 * Tahap 3 – Pemeriksaan semantik: pasangan yang belum pasti dinilai AI dalam
 *           SATU request per putaran (judgePrompt / parseJudgeVerdict), bukan
 *           satu request per pasangan.
 *
 * Duplikat = substansi pertanyaan dan tujuan pengujiannya sama/mirip; kesamaan
 * topik atau kata kunci saja bukan duplikat.
 */
class SemanticDuplicateChecker
{
    public const STOPWORDS = [
        'apa', 'yang', 'adalah', 'dari', 'pada', 'dalam', 'untuk', 'dengan', 'itu',
        'ini', 'ke', 'di', 'dan', 'atau', 'sebuah', 'suatu', 'fungsi', 'peran',
        'the', 'a', 'an', 'of', 'in', 'on', 'to', 'is', 'are', 'what', 'which', 'why', 'how',
    ];

    /** Skor token minimal agar pasangan diperiksa secara semantik. */
    private const BAND_FLOOR = 0.45;

    /** Maksimum pasangan belum-pasti per kandidat. */
    private const MAX_NEIGHBORS = 2;

    /** Maksimum pasangan per request semantik. */
    public const MAX_JUDGE_PAIRS = 40;

    /** Maksimum karakter tiap soal yang dikirim ke judge. */
    private const JUDGE_TEXT_CHARS = 300;

    /**
     * Instruksi judge semantik (dipakai QuestionGenerationService). Setiap pasangan
     * dinilai eksplisit: model harus merumuskan dulu apa yang ditanyakan A dan B.
     * Rumusan itu terbukti (uji 30 pasangan x 6 run) menghilangkan false positive
     * pada soal yang bertema sama tetapi menanyakan hal berbeda.
     */
    public const JUDGE_SYSTEM = 'You compare pairs of exam questions and decide, for every pair, whether the two questions are duplicates. '
        . 'Two questions are duplicates ONLY when they ask for the same thing and test the same knowledge, so the correct answer is the same (a rewording, or a changed detail that does not change what is asked). '
        . 'Questions about the same topic or with the same wording pattern but asking about different facts, values, terms, causes, effects, or scenarios are NOT duplicates. '
        . 'For each pair first summarize in at most 5 words what A asks and what B asks; if those differ, the pair is not a duplicate. When unsure answer false. '
        . 'Respond ONLY with JSON: {"verdicts":[{"id":<pair_id>,"a":"<at most 5 words>","b":"<at most 5 words>","duplicate":true|false}]} with exactly one entry per pair.';

    private float $threshold;

    /** @var array<string, array{ref:mixed, text:string, tokens:array<string,true>, hash:string, chash:string}> */
    private array $items = [];

    private ?array $stopSet = null;

    public function __construct(?float $threshold = null)
    {
        $this->threshold = $threshold ?? (float) (env('quiz.dupThreshold') ?? 0.80);
    }

    /** Ambang skor token lokal yang dipakai bila AI penilai tidak tersedia. */
    public function threshold(): float
    {
        return $this->threshold;
    }

    public function count(): int
    {
        return count($this->items);
    }

    // ==================== Tahap 1: normalisasi ====================

    public function normalize(string $text): string
    {
        $t = mb_strtolower($text, 'UTF-8');
        $t = preg_replace('/[^a-z0-9\s+#%&@_\/]/u', ' ', $t) ?? $t;

        return preg_replace('/\s+/u', ' ', trim($t)) ?? trim($t);
    }

    /** Token isi (tanpa stopword) sebagai set; urutan = urutan kemunculan pertama. */
    private function contentTokens(string $text): array
    {
        $this->stopSet ??= array_fill_keys(self::STOPWORDS, true);

        $set = [];
        foreach (explode(' ', $this->normalize($text)) as $word) {
            if ($word !== '' && ! isset($this->stopSet[$word])) {
                $set[$word] = true;
            }
        }

        return $set;
    }

    /** Fingerprint teks: huruf kecil, tanpa spasi/tanda baca (tahan terhadap "Super Key" vs "Superkey"). */
    private function fingerprint(string $normalized): string
    {
        return sha1(str_replace(' ', '', $normalized));
    }

    /**
     * Fingerprint urutan token isi (stopword diabaikan): dua teks dengan kata
     * isi yang sama dan urutan sama adalah soal yang sama. Jika ada SATU kata
     * isi berbeda (angka, nama, istilah) teks TIDAK dianggap pasti duplikat —
     * perbedaan kecil itu bisa mengubah substansi soal, jadi diserahkan ke
     * pemeriksaan semantik.
     */
    private function contentFingerprint(array $tokens): string
    {
        return $tokens === [] ? 'empty' : sha1(implode(' ', array_keys($tokens)));
    }

    /** Skor token 0..1: campuran Jaccard dan overlap coefficient (robust untuk kalimat beda panjang). */
    public function tokenScore(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        [$small, $large] = count($a) <= count($b) ? [$a, $b] : [$b, $a];

        $inter = 0;
        foreach ($small as $token => $_) {
            if (isset($large[$token])) {
                $inter++;
            }
        }

        $union = count($a) + count($b) - $inter;

        return 0.4 * ($union === 0 ? 0.0 : $inter / $union) + 0.6 * ($inter / count($small));
    }

    /** Skor kemiripan token dua teks (kompatibilitas: sama dengan metric sebelumnya). */
    public function similarity(string $textA, string $textB): float
    {
        return $this->tokenScore($this->contentTokens($textA), $this->contentTokens($textB));
    }

    // ==================== Indeks (dibangun sekali) ====================

    /**
     * Daftarkan satu soal yang sudah ada. Normalisasi & fingerprint dihitung
     * di sini, sekali saja — bukan setiap kali ada kandidat.
     *
     * @param int|string $ref penanda soal (id soal lama / kunci kandidat batch)
     */
    public function add($ref, string $text): void
    {
        $tokens = $this->contentTokens($text);

        $this->items[(string) $ref] = [
            'ref'    => $ref,
            'text'   => $text,
            'tokens' => $tokens,
            'hash'   => $this->fingerprint($this->normalize($text)),
            'chash'  => $this->contentFingerprint($tokens),
        ];
    }

    /** Keluarkan soal dari indeks (kandidat batch yang akhirnya ditolak). */
    public function remove($ref): void
    {
        unset($this->items[(string) $ref]);
    }

    // ============ Tahap 2: skrining lokal satu kandidat ============

    /**
     * @return array{decision:string, ref:mixed, score:float, neighbors:list<array{ref:mixed, score:float, text:string}>}
     *         decision: 'duplicate' (pasti duplikat), 'borderline' (perlu
     *         pemeriksaan semantik), atau 'distinct'.
     */
    public function screen(string $text): array
    {
        $tokens = $this->contentTokens($text);
        $hash   = $this->fingerprint($this->normalize($text));
        $chash  = $this->contentFingerprint($tokens);

        // Duplikasi yang jelas: teks identik, atau kata isi sama & urutan sama.
        foreach ($this->items as $item) {
            if ($item['hash'] === $hash || $item['chash'] === $chash) {
                return ['decision' => 'duplicate', 'ref' => $item['ref'], 'score' => 1.0, 'neighbors' => []];
            }
        }

        // Belum pasti: banyak kata isi yang sama → kandidat pemeriksaan semantik.
        $neighbors = [];
        foreach ($this->items as $item) {
            $score = $this->tokenScore($tokens, $item['tokens']);
            if ($score >= self::BAND_FLOOR) {
                $neighbors[] = ['ref' => $item['ref'], 'score' => $score, 'text' => $item['text']];
            }
        }

        if ($neighbors === []) {
            return ['decision' => 'distinct', 'ref' => null, 'score' => 0.0, 'neighbors' => []];
        }

        usort($neighbors, static fn ($x, $y) => $y['score'] <=> $x['score']);

        return [
            'decision'  => 'borderline',
            'ref'       => null,
            'score'     => $neighbors[0]['score'],
            'neighbors' => array_slice($neighbors, 0, self::MAX_NEIGHBORS),
        ];
    }

    // ============ Tahap 3: satu request semantik per putaran ============
    // Pemanggilan AI dilakukan QuestionGenerationService (akuntansi token
    // terpusat); kelas ini menyiapkan prompt dan menafsirkan jawabannya.

    /**
     * Prompt penilaian untuk seluruh pasangan belum-pasti (dikirim SEKALI
     * per putaran, bukan satu request per pasangan).
     *
     * @param list<array{id:int, candidate:string, existing:string}> $pairs
     */
    public function judgePrompt(array $pairs): string
    {
        $pairs = array_slice($pairs, 0, self::MAX_JUDGE_PAIRS);

        return json_encode(['pairs' => array_map(static fn ($p) => [
            'id' => (int) $p['id'],
            'a'  => mb_substr((string) $p['candidate'], 0, self::JUDGE_TEXT_CHARS),
            'b'  => mb_substr((string) $p['existing'], 0, self::JUDGE_TEXT_CHARS),
        ], $pairs)], JSON_UNESCAPED_UNICODE);
    }

    /** Jumlah token output yang cukup untuk jawaban judge (~25 token per pasangan). */
    public static function judgeMaxTokens(int $pairCount): int
    {
        return 200 + 45 * min(self::MAX_JUDGE_PAIRS, max(1, $pairCount));
    }

    /**
     * Tafsirkan jawaban judge menjadi id pasangan yang duplikat.
     *
     * @return list<int>|null null bila jawaban tidak dapat dibaca
     */
    public function parseJudgeVerdict(string $content, array $pairs): ?array
    {
        $json = json_decode(trim($content), true);
        if (! is_array($json)) {
            return null;
        }

        $pairs = array_slice($pairs, 0, self::MAX_JUDGE_PAIRS);
        $valid = array_map(static fn ($p) => (int) $p['id'], $pairs);

        if (isset($json['verdicts']) && is_array($json['verdicts'])) {
            $flagged = [];
            foreach ($json['verdicts'] as $verdict) {
                if (is_array($verdict) && array_key_exists('id', $verdict)
                    && filter_var($verdict['duplicate'] ?? false, FILTER_VALIDATE_BOOLEAN) === true) {
                    $flagged[] = (int) $verdict['id'];
                }
            }

            return array_values(array_intersect($flagged, $valid));
        }

        // Toleransi: model menjawab dengan format daftar sederhana.
        if (isset($json['duplicates']) && is_array($json['duplicates'])) {
            return array_values(array_intersect(array_map('intval', $json['duplicates']), $valid));
        }

        return null;
    }
}
