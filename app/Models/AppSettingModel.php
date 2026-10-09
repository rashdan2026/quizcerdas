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

    public function getValue(string $key, $default = null)
    {
        $row = $this->where('setting_key', $key)->first();
        return $row['setting_value'] ?? $default;
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
            return (bool) $this->update($existing['id'], $data);
        }
        if ($description !== null && !isset($data['description'])) {
            $data['description'] = $description;
        }
        return (bool) $this->insert($data);
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
