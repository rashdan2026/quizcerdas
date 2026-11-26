<?php

namespace App\Models;

use CodeIgniter\Model;

class AppSettingModel extends Model
{
    protected $table         = 'app_settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'setting_key',
        'setting_value',
        'description',
        'updated_at',
    ];

    protected $useTimestamps = false;
    protected $useAutoIncrement = true;

    /**
     * Cache nilai setting per-request PHP. PHP berbagi-tak-berbagi state antar
     * request, jadi cache ini hanya hidup satu request (tanpa risiko data
     * basi), namun menghemat query berulang: satu halaman dashboard memanggil
     * getValue() 4-6x (AdService + layout).
     */
    private static array $requestCache = [];

    public function getValue(string $key, $default = null)
    {
        if (array_key_exists($key, self::$requestCache)) {
            $cached = self::$requestCache[$key];

            return $cached !== null ? $cached : $default;
        }

        $row = $this->where('setting_key', $key)->first();
        $value = $row['setting_value'] ?? null;

        self::$requestCache[$key] = $value;

        return $value !== null ? $value : $default;
    }

    public function setValue(string $key, $value, ?string $description = null): bool
    {
        $existing = $this->where('setting_key', $key)->first();
        $data = [
            'setting_key'   => $key,
            'setting_value' => (string) $value,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
        if ($description !== null) {
            $data['description'] = $description;
        }

        if ($existing) {
            $ok = (bool) $this->update($existing['id'], $data);
        } else {
            if ($description !== null && !isset($data['description'])) {
                $data['description'] = $description;
            }
            $ok = (bool) $this->insert($data);
        }

        if ($ok) {
            // Sinkronkan cache per-request agar nilai baru langsung terlihat.
            self::$requestCache[$key] = (string) $value;
        }

        return $ok;
    }

    public function getAllAsArray(): array
    {
        $rows = $this->orderBy('setting_key', 'ASC')->findAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r;
        }
        return $out;
    }
}
