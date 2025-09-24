<?php
// pages/production_order_details.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

if (!hasPermission('production_movements') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['id'])) {
    header('Location: production_orders.php');
    exit();
}

$order_id = intval($_GET['id']);

try {
    $db = getDBConnection();
    
    // Üretim emri bilgilerini getir
    $stmt = $db->prepare("
        SELECT po.*, u.full_name as created_by_name
        FROM production_orders po
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Üretim emri bulunamadı!');
    }
    
    // Üretim için gereken malzemeleri getir
    $stmt = $db->prepare("
        SELECT poi.*, i.code, i.name, i.description, u.name as uom_name,
               (SELECT COALESCE(SUM(quantity), 0) FROM stock_movement_lines WHERE item_id = i.id) as current_stock
        FROM production_order_items poi
        LEFT JOIN items i ON poi.item_id = i.id
        LEFT JOIN uoms u ON i.uom_id = u.id
        WHERE poi.production_order_id = ?
        ORDER BY poi.id
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Malzeme çıkışlarını getir
    $stmt = $db->prepare("
        SELECT pi.*, u.full_name as issued_by_name,
               COUNT(pii.id) as item_count,
               SUM(pii.quantity) as total_quantity
        FROM production_issues pi
        LEFT JOIN users u ON pi.issued_by = u.id
        LEFT JOIN production_issue_items pii ON pi.id = pii.production_issue_id
        WHERE pi.production_order_id = ?
        GROUP BY pi.id
        ORDER BY pi.issue_date DESC
    ");
    $stmt->execute([$order_id]);
    $production_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İadeleri getir
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as returned_by_name,
               COUNT(pri.id) as item_count,
               SUM(pri.quantity) as total_quantity
        FROM production_returns pr
        LEFT JOIN users u ON pr.returned_by = u.id
        LEFT JOIN production_return_items pri ON pr.id = pri.production_return_id
        WHERE pr.production_order_id = ?
        GROUP BY pr.id
        ORDER BY pr.return_date DESC
    ");
    $stmt->execute([$order_id]);
    $production_returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İstatistikler
    $total_required = array_sum(array_column($order_items, 'required_quantity'));
    $total_issued = array_sum(array_column($order_items, 'issued_quantity'));
    $completion_rate = $total_required > 0 ? ($total_issued / $total_required * 100) : 0;
    
} catch(PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-industry"></i> Üretim Emri Detayları</h1>
                <div>
                    <a href="production_orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Emir Listesi
                    </a>
                    <?php if ($order['status'] == 'planned' || $order['status'] == 'in_progress'): ?>
                    <a href="production_issue.php?order_id=<?php echo $order_id; ?>" class="btn btn-success">
                        <i class="fas fa-sign-out-alt"></i> Malzeme Çıkışı
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Üretim Emri Bilgileri -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Üretim Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Emir No:</th><td><strong><?php echo $order['order_number']; ?></strong></td></tr>
                                <tr><th>Ürün:</th><td><?php echo $order['product_name']; ?></td></tr>
                                <tr><th>Hedef Miktar:</th><td><?php echo $order['target_quantity']; ?> adet</td></tr>
                                <tr><th>Planlanan Tarih:</th><td><?php echo date('d.m.Y', strtotime($order['planned_date'])); ?></td></tr>
                                <tr><th>Oluşturan:</th><td><?php echo $order['created_by_name']; ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Durum ve İstatistikler</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th>Durum:</th>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'planned' => 'secondary',
                                            'in_progress' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_badges[$order['status']]; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Öncelik:</th>
                                    <td>
                                        <?php 
                                        $priority_badges = [
                                            'urgent' => 'danger',
                                            'high' => 'warning',
                                            'medium' => 'info',
                                            'low' => 'secondary'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $priority_badges[$order['priority']]; ?>">
                                            <?php echo ucfirst($order['priority']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr><th>Malzeme Tamamlanma:</th>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar 
                                                <?php echo $completion_rate >= 100 ? 'bg-success' : 
                                                       ($completion_rate >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                style="width: <?php echo min($completion_rate, 100); ?>%">
                                                <?php echo round($completion_rate, 1); ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $total_issued; ?> / <?php echo $total_required; ?>
                                        </small>
                                    </td>
                                </tr>
                                <tr><th>Çıkış Sayısı:</th><td><?php echo count($production_issues); ?> işlem</td></tr>
                                <tr><th>İade Sayısı:</th><td><?php echo count($production_returns); ?> işlem</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gereken Malzemeler -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Gereken Malzemeler</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Malzeme</th>
                                    <th>Birim</th>
                                    <th>Gereken Miktar</th>
                                    <th>Çıkarılan Miktar</th>
                                    <th>Kalan</th>
                                    <th>Mevcut Stok</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $item['code']; ?></strong><br>
                                        <small><?php echo $item['name']; ?></small>
                                    </td>
                                    <td><?php echo $item['uom_name']; ?></td>
                                    <td><?php echo $item['required_quantity']; ?></td>
                                    <td><?php echo $item['issued_quantity']; ?></td>
                                    <td>
                                        <?php 
                                        $remaining = $item['required_quantity'] - $item['issued_quantity'];
                                        $remaining_class = $remaining <= 0 ? 'success' : ($remaining <= $item['current_stock'] ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?php echo $remaining_class; ?>">
                                            <?php echo $remaining; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['current_stock'] >= $remaining ? 'success' : 'danger'; ?>">
                                            <?php echo $item['current_stock']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['issued_quantity'] >= $item['required_quantity']): ?>
                                            <span class="badge bg-success">Tamamlandı</span>
                                        <?php elseif ($item['issued_quantity'] > 0): ?>
                                            <span class="badge bg-warning">Kısmi</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Bekliyor</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Malzeme Çıkış Geçmişi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Malzeme Çıkış Geçmişi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($production_issues)): ?>
                        <p class="text-muted">Henüz malzeme çıkışı yapılmamıştır.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Çıkış No</th>
                                        <th>Tarih</th>
                                        <th>Kalem Sayısı</th>
                                        <th>Toplam Miktar</th>
                                        <th>Çıkaran</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($production_issues as $issue): ?>
                                    <tr>
                                        <td><strong><?php echo $issue['issue_number']; ?></strong></td>
                                        <td><?php echo date('d.m.Y', strtotime($issue['issue_date'])); ?></td>
                                        <td><span class="badge bg-info"><?php echo $issue['item_count']; ?></span></td>
                                        <td><span class="badge bg-secondary"><?php echo $issue['total_quantity']; ?></span></td>
                                        <td><?php echo $issue['issued_by_name']; ?></td>
                                        <td>
                                            <a href="production_issue_details.php?id=<?php echo $issue['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-eye"></i> Detay
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($production_returns)): ?>
            <!-- İade Geçmişi -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">İade Geçmişi</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>İade No</th>
                                    <th>Tarih</th>
                                    <th>Kalem Sayısı</th>
                                    <th>Toplam Miktar</th>
                                    <th>İade Eden</th>
                                    <th>Sebep</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($production_returns as $return): ?>
                                <tr>
                                    <td><strong><?php echo $return['return_number']; ?></strong></td>
                                    <td><?php echo date('d.m.Y', strtotime($return['return_date'])); ?></td>
                                    <td><span class="badge bg-info"><?php echo $return['item_count']; ?></span></td>
                                    <td><span class="badge bg-secondary"><?php echo $return['total_quantity']; ?></span></td>
                                    <td><?php echo $return['returned_by_name']; ?></td>
                                    <td><?php echo mb_substr($return['reason'], 0, 50) . '...'; ?></td>
                                    <td>
                                        <a href="production_return_details.php?id=<?php echo $return['id']; ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-eye"></i> Detay
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>