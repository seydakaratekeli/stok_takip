<?php
// pages/role_check.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();
requireAdmin(); // Sadece yönetici görebilir

try {
    $db = getDBConnection();
    
    // Rolleri ve yetkilerini getir
    $stmt = $db->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kullanıcı sayılarını getir
    $user_counts = [];
    foreach ($roles as $role) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role_id = ? AND is_active = 1");
        $stmt->execute([$role['id']]);
        $user_counts[$role['id']] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
} catch(PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user-shield"></i> Rol Yetki Kontrolü</h1>
                <a href="users.php" class="btn btn-primary">Kullanıcı Yönetimi</a>
            </div>

            <div class="row">
                <?php foreach ($roles as $role): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-<?php echo $role['name'] == 'Yönetici' ? 'danger' : ($role['name'] == 'Depo Görevlisi' ? 'primary' : 'success'); ?> text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt"></i> <?php echo $role['name']; ?>
                                <span class="badge bg-light text-dark float-end"><?php echo $user_counts[$role['id']]; ?> kullanıcı</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $permissions = json_decode($role['permissions'], true);
                            $required_permissions = [];
                            $missing_permissions = [];
                            
                            // Dokümandaki gereksinimlere göre kontrol
                            switch($role['name']) {
                                case 'Yönetici':
                                    $required_permissions = ['all'];
                                    break;
                                case 'Depo Görevlisi':
                                    $required_permissions = ['items_view', 'movements', 'counts', 'iade'];
                                    break;
                                case 'Üretim Operatörü':
                                    $required_permissions = ['items_view', 'production_movements', 'uretim_iade'];
                                    break;
                                case 'Satınalma':
                                    $required_permissions = ['items_manage', 'siparis', 'view_reports'];
                                    break;
                            }
                            
                            // Eksik yetkileri bul
                            if ($role['name'] == 'Yönetici') {
                                $has_all_permissions = true;
                            } else {
                                foreach ($required_permissions as $perm) {
                                    if (!isset($permissions[$perm]) || !$permissions[$perm]) {
                                        $missing_permissions[] = $perm;
                                    }
                                }
                            }
                            ?>
                            
                            <?php if ($role['name'] == 'Yönetici' && isset($permissions['all']) && $permissions['all']): ?>
                                <span class="badge bg-success mb-2">✓ Tüm Yetkiler Mevcut</span>
                            <?php elseif (empty($missing_permissions)): ?>
                                <span class="badge bg-success mb-2">✓ Tüm Gereken Yetkiler Mevcut</span>
                            <?php else: ?>
                                <span class="badge bg-warning mb-2">⚠ Eksik Yetkiler: <?php echo count($missing_permissions); ?></span>
                            <?php endif; ?>
                            
                            <div class="permissions-list">
                                <h6>Mevcut Yetkiler:</h6>
                                <?php 
                                $permission_names = [
                                    'all' => 'Tüm Yetkiler',
                                    'items_view' => 'Malzeme Görüntüleme',
                                    'items_manage' => 'Malzeme Yönetimi',
                                    'movements' => 'Stok Hareketleri',
                                    'production_movements' => 'Üretim Hareketleri',
                                    'counts' => 'Stok Sayımı',
                                    'view_reports' => 'Rapor Görüntüleme',
                                    'iade' => 'İade İşlemleri',
                                    'uretim_iade' => 'Üretim İadesi',
                                    'siparis' => 'Sipariş Yönetimi'
                                ];
                                
                                if (isset($permissions['all']) && $permissions['all']): ?>
                                    <span class="badge bg-success me-1 mb-1">Tüm Yetkiler</span>
                                <?php else: 
                                    foreach ($permission_names as $key => $name): 
                                        if (isset($permissions[$key]) && $permissions[$key]): 
                                            $is_required = in_array($key, $required_permissions);
                                    ?>
                                            <span class="badge bg-<?php echo $is_required ? 'primary' : 'secondary'; ?> me-1 mb-1">
                                                <?php echo $name; ?>
                                            </span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                endif; 
                                ?>
                            </div>
                            
                            <?php if (!empty($missing_permissions)): ?>
                            <div class="missing-permissions mt-2">
                                <h6 class="text-danger">Eksik Yetkiler:</h6>
                                <?php foreach ($missing_permissions as $missing): ?>
                                    <span class="badge bg-danger me-1 mb-1">
                                        ❌ <?php echo $permission_names[$missing] ?? $missing; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <h6>Doküman Gereksinimleri:</h6>
                                <ul class="small">
                                    <?php
                                    $requirements = [
                                        'Yönetici' => ['Tüm yetkiler'],
                                        'Depo Görevlisi' => ['Stok giriş/çıkış', 'İade işlemleri', 'Stok sayımı'],
                                        'Üretim Operatörü' => ['Üretim malzeme sarfiyatı', 'Üretim iadesi'],
                                        'Satınalma' => ['Malzeme kartı yönetimi', 'Sipariş işlemleri', 'Rapor görüntüleme']
                                    ];
                                    
                                    foreach ($requirements[$role['name']] as $req): ?>
                                        <li><?php echo $req; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Yetki Düzeltme Formu -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-tools"></i> Yetkileri Düzelt</h5>
                </div>
                <div class="card-body">
                    <form action="update_permissions.php" method="POST">
                        <div class="row">
                            <?php foreach ($roles as $role): 
                                if ($role['name'] == 'Yönetici') continue; // Yönetici yetkilerini değiştirmeyelim
                            ?>
                            <div class="col-md-6 mb-3">
                                <h6><?php echo $role['name']; ?> Yetkileri</h6>
                                <?php 
                                $current_perms = json_decode($role['permissions'], true);
                                $role_permissions = [
                                    'items_view' => 'Malzeme Görüntüleme',
                                    'items_manage' => 'Malzeme Yönetimi',
                                    'movements' => 'Stok Hareketleri',
                                    'production_movements' => 'Üretim Hareketleri',
                                    'counts' => 'Stok Sayımı',
                                    'view_reports' => 'Rapor Görüntüleme',
                                    'iade' => 'İade İşlemleri',
                                    'uretim_iade' => 'Üretim İadesi',
                                    'siparis' => 'Sipariş Yönetimi'
                                ];
                                
                                foreach ($role_permissions as $key => $name): 
                                    $is_checked = isset($current_perms[$key]) && $current_perms[$key];
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="permissions[<?php echo $role['id']; ?>][<?php echo $key; ?>]"
                                               id="perm_<?php echo $role['id']; ?>_<?php echo $key; ?>"
                                               <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="perm_<?php echo $role['id']; ?>_<?php echo $key; ?>">
                                            <?php echo $name; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Yetkileri Güncelle</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>