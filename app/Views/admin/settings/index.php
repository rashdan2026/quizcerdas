<?php
/** @var array $settings */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<form action="<?= base_url('/admin/settings/save') ?>" method="post">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-app-indicator me-2"></i>Aplikasi</div>
                <div class="card-body">
                    <?php $s = $settings['app_name']; ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Aplikasi</label>
                        <input type="text" name="settings[app_name]" class="form-control" maxlength="100" value="<?= esc($s['value']) ?>">
                        <div class="form-text"><?= esc($s['description']) ?></div>
                    </div>
                    <?php $sm = $settings['app_maintenance_mode']; ?>
                    <div class="mb-0">
                        <label class="form-label">Mode Aplikasi</label>
                        <select name="settings[app_maintenance_mode]" class="form-select">
                            <option value="1" <?= (string) $sm['value'] === '1' ? 'selected' : '' ?>>🟢 Aktif — aplikasi berjalan normal</option>
                            <option value="0" <?= (string) $sm['value'] === '0' ? 'selected' : '' ?>>🟡 Maintenance — tampilkan halaman maintenance ke user</option>
                        </select>
                        <div class="form-text"><?= esc($sm['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Keamanan Admin</div>
                <div class="card-body">
                    <?php $s1 = $settings['admin_login_rate_limit']; ?>
                    <div class="mb-3">
                        <label class="form-label">Rate Limit Login (percobaan gagal)</label>
                        <input type="number" min="1" name="settings[admin_login_rate_limit]" class="form-control" value="<?= esc($s1['value']) ?>">
                        <div class="form-text"><?= esc($s1['description']) ?></div>
                    </div>
                    <?php $s2 = $settings['admin_login_lock_minutes']; ?>
                    <div class="mb-0">
                        <label class="form-label">Durasi Lock (menit)</label>
                        <input type="number" min="1" name="settings[admin_login_lock_minutes]" class="form-control" value="<?= esc($s2['value']) ?>">
                        <div class="form-text"><?= esc($s2['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Keamanan Mahasiswa</div>
                <div class="card-body">
                    <?php $s = $settings['student_otp_every_n_logins']; ?>
                    <div class="mb-0">
                        <label class="form-label">OTP Setiap N Kali Login</label>
                        <div class="input-group">
                            <input type="number" min="1" name="settings[student_otp_every_n_logins]" class="form-control" value="<?= esc($s['value']) ?>">
                            <span class="input-group-text">login</span>
                        </div>
                        <div class="form-text"><?= esc($s['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-badge-ad me-2"></i>Pengaturan Iklan</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php $s = $settings['default_ad_setting_number']; ?>
                        <div class="col-md-3">
                            <label class="form-label">Setting Number Default</label>
                            <input type="number" min="1" max="9" name="settings[default_ad_setting_number]" class="form-control" value="<?= esc($s['value']) ?>">
                            <div class="form-text"><?= esc($s['description']) ?></div>
                        </div>
                        <?php $s = $settings['daily_ad_max_display']; ?>
                        <div class="col-md-3">
                            <label class="form-label">Maks Tampil / Hari</label>
                            <input type="number" min="1" name="settings[daily_ad_max_display]" class="form-control" value="<?= esc($s['value']) ?>">
                            <div class="form-text"><?= esc($s['description']) ?></div>
                        </div>
                        <?php $s = $settings['ad_lock_duration_seconds']; ?>
                        <div class="col-md-3">
                            <label class="form-label">Lock Modal (detik)</label>
                            <input type="number" min="1" name="settings[ad_lock_duration_seconds]" class="form-control" value="<?= esc($s['value']) ?>">
                            <div class="form-text"><?= esc($s['description']) ?></div>
                        </div>
                        <?php $s = $settings['ad_max_file_size_mb']; ?>
                        <div class="col-md-3">
                            <label class="form-label">Maks File GIF (MB)</label>
                            <input type="number" min="1" name="settings[ad_max_file_size_mb]" class="form-control" value="<?= esc($s['value']) ?>">
                            <div class="form-text"><?= esc($s['description']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-admin"><i class="bi bi-save me-1"></i> Simpan Pengaturan</button>
    </div>
</form>

<?= $this->endSection() ?>
