<?php
// index.php
require_once 'config/database.php';
require_once 'includes/auth.php';
checkAuth();

// Dashboard istatistiklerini getir
try {
    $db = getDBConnection();
    
    // Toplam malzeme sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM items");
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Kritik stoktaki malzemeler
    $stmt = $db->query("
        SELECT COUNT(*) as critical 
        FROM items i 
        LEFT JOIN (
            SELECT item_id, SUM(quantity) as current_stock 
            FROM stock_movement_lines 
            GROUP BY item_id
        ) s ON i.id = s.item_id 
        WHERE (s.current_stock <= i.min_stock_level OR s.current_stock IS NULL) 
        AND i.min_stock_level > 0
    ");
    $critical_items = $stmt->fetch(PDO::FETCH_ASSOC)['critical'];
    
    // Son stok hareketleri
    $stmt = $db->query("
        SELECT sm.*, mt.name as movement_type, u.full_name 
        FROM stock_movements sm 
        JOIN movement_types mt ON sm.movement_type_id = mt.id 
        JOIN users u ON sm.created_by = u.id 
        ORDER BY sm.created_at DESC 
        LIMIT 5
    ");
    $recent_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam stok hareketi sayısı
    $stmt = $db->query("SELECT COUNT(*) as total_movements FROM stock_movements");
    $total_movements = $stmt->fetch(PDO::FETCH_ASSOC)['total_movements'];
    
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-success">Hoş geldiniz, <?php echo $_SESSION['full_name']; ?></span>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- İstatistik Kartları -->
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Toplam Malzeme</h5>
                                    <h2><?php echo $total_items; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-boxes fa-2x"></i>
                                </div>
                            </div>
                            <a href="pages/items.php" class="text-white-50 small">Tümünü görüntüle</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Kritik Stok</h5>
                                    <h2><?php echo $critical_items; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                            </div>
                            <a href="pages/reports.php?filter=critical" class="text-white-50 small">Detayları görüntüle</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Toplam Hareket</h5>
                                    <h2><?php echo $total_movements; ?></h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exchange-alt fa-2x"></i>
                                </div>
                            </div>
                            <a href="pages/movements.php" class="text-white-50 small">Tüm hareketler</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Bugünkü Hareket</h5>
                                    <h2>0</h2>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                            </div>
                            <span class="text-white-50 small">Günlük özet</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Son Hareketler ve Hızlı İşlemler -->
            <div class="row">
                <!-- Son Stok Hareketleri -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history"></i> Son Stok Hareketleri
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_movements)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>Henüz stok hareketi bulunmamaktadır.</p>
                                    <a href="pages/movements.php" class="btn btn-primary btn-sm">İlk Hareketi Oluştur</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Tarih</th>
                                                <th>Tip</th>
                                                <th>Referans</th>
                                                <th>Kullanıcı</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_movements as $movement): ?>
                                            <tr>
                                                <td><?php echo date('d.m.Y', strtotime($movement['movement_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $movement['movement_type'] === 'Depo Girişi' ? 'success' : 'warning'; ?>">
                                                        <?php echo $movement['movement_type']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $movement['reference_no'] ?: '-'; ?></td>
                                                <td><?php echo $movement['full_name']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center">
                                    <a href="pages/movements.php" class="btn btn-outline-primary btn-sm">Tüm Hareketleri Gör</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bolt"></i> Hızlı İşlemler
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if (hasPermission('movements') || hasPermission('all')): ?>
                                <a href="pages/movements.php?action=add" class="btn btn-success btn-sm">
                                    <i class="fas fa-plus-circle"></i> Stok Girişi
                                </a>
                                <a href="pages/movements.php?action=add&type=exit" class="btn btn-warning btn-sm">
                                    <i class="fas fa-minus-circle"></i> Stok Çıkışı
                                </a>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('items_manage') || hasPermission('all')): ?>
                                <a href="pages/items.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-box"></i> Malzeme Ekle
                                </a>
                                <?php endif; ?>
                                
                                <a href="pages/reports.php" class="btn btn-info btn-sm">
                                    <i class="fas fa-chart-pie"></i> Stok Raporu
                                </a>
                            </div>
                            
                            <hr>
                            
                            <h6>Sistem Bilgisi</h6>
                            <ul class="list-unstyled small">
                                <li><i class="fas fa-database"></i> Veritabanı: Çalışıyor</li>
                                <li><i class="fas fa-user-shield"></i> Yetki: <?php echo $_SESSION['role_name']; ?></li>
                                <li><i class="fas fa-clock"></i> Son Giriş: <?php echo date('d.m.Y H:i'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>