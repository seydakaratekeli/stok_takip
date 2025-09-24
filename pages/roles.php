<?php
// pages/roles.php
require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

if (!hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

try {
    $db = getDBConnection();
    
    // Rolleri getir
    $stmt = $db->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user-shield"></i> Rol ve İzin Yönetimi</h1>
            </div>

            <div class="row">
                <?php foreach ($roles as $role): ?>
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt"></i> <?php echo $role['name']; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $permissions = json_decode($role['permissions'], true);
                            if ($permissions && isset($permissions['all']) && $permissions['all'] === true): 
                            ?>
                                <span class="badge bg-success">Tüm Yetkiler</span>
                            <?php else: ?>
                                <div class="permissions-list">
                                    <?php 
                                    $permission_names = [
                                        'items_view' => 'Malzeme Görüntüleme',
                                        'items_manage' => 'Malzeme Yönetimi',
                                        'movements' => 'Stok Hareketleri',
                                        'production_movements' => 'Üretim Hareketleri',
                                        'counts' => 'Stok Sayımı',
                                        'view_reports' => 'Rapor Görüntüleme'
                                    ];
                                    
                                    foreach ($permission_names as $key => $name): 
                                        if (isset($permissions[$key]) && $permissions[$key] === true): 
                                    ?>
                                        <span class="badge bg-primary me-1 mb-1"><?php echo $name; ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 small text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Bu rolü kullanan kullanıcı sayısı: 
                                <?php
                                $stmt = $db->prepare("SELECT COUNT(*) as user_count FROM users WHERE role_id = ? AND is_active = 1");
                                $stmt->execute([$role['id']]);
                                $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'];
                                echo $user_count;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-question-circle"></i> Rol Açıklamaları</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Yönetici</h6>
                            <ul class="small">
                                <li>Tüm sistem yetkileri</li>
                                <li>Kullanıcı yönetimi</li>
                                <li>Tüm rapor ve işlemler</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Depo Görevlisi</h6>
                            <ul class="small">
                                <li>Stok giriş/çıkış işlemleri</li>
                                <li>Malzeme görüntüleme</li>
                                <li>Stok sayımı</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Üretim Operatörü</h6>
                            <ul class="small">
                                <li>Üretim için malzeme çıkışı</li>
                                <li>Malzeme görüntüleme</li>
                                <li>Üretim iade işlemleri</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Satınalma</h6>
                            <ul class="small">
                                <li>Malzeme kartı yönetimi</li>
                                <li>Rapor görüntüleme</li>
                                <li>Stok takibi</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>