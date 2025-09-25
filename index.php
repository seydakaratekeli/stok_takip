<?php
// index.php - Dashboard
require_once 'config/database.php';
require_once 'includes/auth.php';

checkAuth();

try {
    $db = getDBConnection();
    
    // 📊 Dashboard İstatistikleri
    
    // Toplam malzeme sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM items");
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Aktif kullanıcı sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $active_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Bu ayın hareket sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM stock_movements WHERE MONTH(movement_date) = MONTH(NOW())");
    $monthly_movements = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Anlık stok durumu hesapla
    $stmt = $db->query("
        SELECT 
            i.id, i.code, i.name, i.min_stock_level,
            u.name as uom_name,
            COALESCE(SUM(
                CASE 
                    WHEN mt.multiplier > 0 THEN sml.quantity 
                    WHEN mt.multiplier < 0 THEN -sml.quantity 
                    ELSE 0 
                END
            ), 0) as current_stock
        FROM items i
        LEFT JOIN uoms u ON i.uom_id = u.id
        LEFT JOIN stock_movement_lines sml ON i.id = sml.item_id
        LEFT JOIN stock_movements sm ON sml.stock_movement_id = sm.id
        LEFT JOIN movement_types mt ON sm.movement_type_id = mt.id
        GROUP BY i.id, i.code, i.name, i.min_stock_level, u.name
        ORDER BY i.name
    ");
    $stock_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kritik stok kontrolü
    $critical_items = array_filter($stock_status, function($item) {
        return $item['current_stock'] <= $item['min_stock_level'] && $item['min_stock_level'] > 0;
    });
    
    // Sıfır stok kontrolü
    $zero_stock_items = array_filter($stock_status, function($item) {
        return $item['current_stock'] <= 0;
    });
    
    // Son hareketler
    $stmt = $db->query("
        SELECT sm.*, mt.name as movement_type_name, u.full_name,
               COUNT(sml.id) as item_count
        FROM stock_movements sm
        JOIN movement_types mt ON sm.movement_type_id = mt.id
        JOIN users u ON sm.created_by = u.id
        LEFT JOIN stock_movement_lines sml ON sm.id = sml.stock_movement_id
        GROUP BY sm.id
        ORDER BY sm.movement_date DESC, sm.id DESC
        LIMIT 10
    ");
    $recent_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-tachometer-alt"></i> Elektrikli Scooter Stok Dashboard
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Yenile
                        </button>
                    </div>
                    <span class="text-muted small">
                        Son güncelleme: <?php echo date('d.m.Y H:i'); ?>
                    </span>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- 🚨 Kritik Stok Uyarıları -->
            <?php if (!empty($critical_items) || !empty($zero_stock_items)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-warning border-left-warning">
                        <h5 class="alert-heading">
                            <i class="fas fa-exclamation-triangle"></i> Stok Uyarıları
                        </h5>
                        
                        <?php if (!empty($zero_stock_items)): ?>
                        <div class="mb-2">
                            <strong class="text-danger">Stokta Yok (<?php echo count($zero_stock_items); ?> ürün):</strong>
                            <ul class="mb-2">
                                <?php foreach (array_slice($zero_stock_items, 0, 5) as $item): ?>
                                <li><?php echo $item['code'] . ' - ' . $item['name']; ?></li>
                                <?php endforeach; ?>
                                <?php if (count($zero_stock_items) > 5): ?>
                                <li class="text-muted">... ve <?php echo count($zero_stock_items) - 5; ?> ürün daha</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($critical_items)): ?>
                        <div class="mb-2">
                            <strong class="text-warning">Kritik Seviye (<?php echo count($critical_items); ?> ürün):</strong>
                            <ul class="mb-0">
                                <?php foreach (array_slice($critical_items, 0, 5) as $item): ?>
                                <li>
                                    <?php echo $item['code'] . ' - ' . $item['name']; ?>
                                    <span class="badge bg-warning text-dark">
                                        Stok: <?php echo $item['current_stock']; ?> / Min: <?php echo $item['min_stock_level']; ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                                <?php if (count($critical_items) > 5): ?>
                                <li class="text-muted">... ve <?php echo count($critical_items) - 5; ?> ürün daha</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="pages/reports.php?type=low_stock" class="btn btn-warning btn-sm">
                                <i class="fas fa-list"></i> Detayları Gör
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 📊 Özet Kartları -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Toplam Malzeme
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($total_items); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-boxes fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Normal Stok
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $normal_stock = count($stock_status) - count($critical_items) - count($zero_stock_items);
                                        echo number_format($normal_stock); 
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Kritik Stok
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format(count($critical_items) + count($zero_stock_items)); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Bu Ay Hareket
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($monthly_movements); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ana İçerik -->
            <div class="row">
                <!-- Stok Durumu -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-warehouse"></i> Anlık Stok Durumu
                            </h6>
                            <a href="pages/reports.php?type=stock_report" class="btn btn-primary btn-sm">
                                Detay Rapor
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Kod</th>
                                            <th>Malzeme</th>
                                            <th>Mevcut</th>
                                            <th>Min.</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($stock_status, 0, 15) as $item): ?>
                                        <tr>
                                            <td><strong><?php echo $item['code']; ?></strong></td>
                                            <td><?php echo $item['name']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $item['current_stock'] <= 0 ? 'danger' : 
                                                         ($item['current_stock'] <= $item['min_stock_level'] && $item['min_stock_level'] > 0 ? 'warning' : 'success'); 
                                                ?>">
                                                    <?php echo $item['current_stock']; ?> <?php echo $item['uom_name']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $item['min_stock_level']; ?></td>
                                            <td>
                                                <?php if ($item['current_stock'] <= 0): ?>
                                                    <i class="fas fa-times-circle text-danger" title="Stokta Yok"></i>
                                                <?php elseif ($item['current_stock'] <= $item['min_stock_level'] && $item['min_stock_level'] > 0): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning" title="Kritik Seviye"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-check-circle text-success" title="Normal"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($stock_status) > 15): ?>
                            <div class="text-center mt-2">
                                <a href="pages/reports.php?type=stock_report" class="btn btn-outline-primary btn-sm">
                                    Tümünü Gör (<?php echo count($stock_status); ?> malzeme)
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Son Hareketler -->
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-history"></i> Son Hareketler
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_movements)): ?>
                                <p class="text-muted text-center">Henüz hareket kaydı yok</p>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($recent_movements as $movement): ?>
                                    <div class="timeline-item mb-3">
                                        <div class="timeline-marker bg-primary"></div>
                                        <div class="timeline-content">
                                            <h6 class="timeline-title mb-1">
                                                <?php echo $movement['movement_type_name']; ?>
                                            </h6>
                                            <p class="timeline-text text-muted small mb-1">
                                                <?php echo $movement['item_count']; ?> malzeme - 
                                                <?php echo date('d.m.Y', strtotime($movement['movement_date'])); ?>
                                            </p>
                                            <p class="timeline-text small mb-0">
                                                <?php echo $movement['full_name']; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center">
                                    <a href="pages/movements.php" class="btn btn-outline-primary btn-sm">
                                        Tüm Hareketler
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Hızlı İşlemler -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-bolt"></i> Hızlı İşlemler
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="pages/movements.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-plus"></i> Yeni Stok Hareketi
                                </a>
                                <a href="pages/items.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-boxes"></i> Malzeme Kartları
                                </a>
                                <a href="pages/counts.php" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-clipboard-check"></i> Stok Sayımı
                                </a>
                                <a href="pages/reports.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-chart-bar"></i> Raporlar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-info { border-left: 0.25rem solid #36b9cc !important; }

.timeline {
    position: relative;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
}

.timeline-marker {
    position: absolute;
    left: 0;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.timeline-content {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 10px;
    border: 1px solid #e9ecef;
}

.timeline-title {
    color: #5a5c69;
    font-size: 0.875rem;
}

.timeline-text {
    margin: 0;
    line-height: 1.4;
}
</style>

<?php include 'includes/footer.php'; ?>