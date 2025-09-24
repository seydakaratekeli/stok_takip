<?php
// pages/goods_receipt.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü - Depo ve Yönetici
if (!hasPermission('mal_kabul') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

try {
    $db = getDBConnection();
    
    // Mal Kabul bekleyen siparişler (Teslim Edildi durumundakiler)
    $stmt = $db->query("
        SELECT o.*, s.name as supplier_name, s.contact_person, 
               COUNT(oi.id) as item_count, SUM(oi.quantity) as total_quantity
        FROM orders o
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status_id = 4 -- Teslim Edildi
        AND NOT EXISTS (
            SELECT 1 FROM goods_receipts gr 
            WHERE gr.order_id = o.id AND gr.status IN ('completed', 'approved')
        )
        GROUP BY o.id
        ORDER BY o.expected_date ASC, o.order_date ASC
    ");
    $pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devam eden mal kabul kayıtları
    $stmt = $db->query("
        SELECT gr.*, o.order_number, s.name as supplier_name, 
               u.full_name as inspector_name, COUNT(gri.id) as item_count
        FROM goods_receipts gr
        LEFT JOIN orders o ON gr.order_id = o.id
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN users u ON gr.inspected_by = u.id
        LEFT JOIN goods_receipt_items gri ON gr.id = gri.goods_receipt_id
        WHERE gr.status IN ('draft', 'inspecting')
        GROUP BY gr.id
        ORDER BY gr.receipt_date DESC
    ");
    $active_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tamamlanmış mal kabul kayıtları
    $stmt = $db->query("
        SELECT gr.*, o.order_number, s.name as supplier_name, 
               u.full_name as inspector_name, COUNT(gri.id) as item_count
        FROM goods_receipts gr
        LEFT JOIN orders o ON gr.order_id = o.id
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN users u ON gr.inspected_by = u.id
        LEFT JOIN goods_receipt_items gri ON gr.id = gri.goods_receipt_id
        WHERE gr.status IN ('approved', 'rejected', 'completed')
        GROUP BY gr.id
        ORDER BY gr.receipt_date DESC
        LIMIT 50
    ");
    $completed_receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h2"><i class="fas fa-clipboard-check"></i> Mal Kabul İşlemleri</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Mal Kabul Bekleyen Siparişler -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock"></i> Mal Kabul Bekleyen Siparişler
                        <span class="badge bg-light text-dark float-end"><?php echo count($pending_orders); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_orders)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <p>Mal kabul bekleyen sipariş bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Sipariş No</th>
                                        <th>Tedarikçi</th>
                                        <th>Sipariş Tarihi</th>
                                        <th>Kalem Sayısı</th>
                                        <th>Toplam Miktar</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo $order['order_number']; ?></strong></td>
                                        <td><?php echo $order['supplier_name']; ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($order['order_date'])); ?></td>
                                        <td><span class="badge bg-info"><?php echo $order['item_count']; ?></span></td>
                                        <td><span class="badge bg-secondary"><?php echo $order['total_quantity']; ?></span></td>
                                        <td>
                                            <a href="goods_receipt_create.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-play"></i> Mal Kabul Başlat
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

            <!-- Devam Eden Mal Kabul İşlemleri -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks"></i> Devam Eden Mal Kabul İşlemleri
                        <span class="badge bg-light text-dark float-end"><?php echo count($active_receipts); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($active_receipts)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Devam eden mal kabul işlemi bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Mal Kabul No</th>
                                        <th>Sipariş No</th>
                                        <th>Tedarikçi</th>
                                        <th>Kontrol Tarihi</th>
                                        <th>Durum</th>
                                        <th>Kontrolör</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_receipts as $receipt): ?>
                                    <tr>
                                        <td><strong><?php echo $receipt['receipt_number']; ?></strong></td>
                                        <td><?php echo $receipt['order_number']; ?></td>
                                        <td><?php echo $receipt['supplier_name']; ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($receipt['receipt_date'])); ?></td>
                                        <td>
                                            <?php 
                                            $status_badges = [
                                                'draft' => 'secondary',
                                                'inspecting' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'completed' => 'primary'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_badges[$receipt['status']]; ?>">
                                                <?php echo ucfirst($receipt['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $receipt['inspector_name'] ?: '-'; ?></td>
                                        <td>
                                            <a href="goods_receipt_details.php?id=<?php echo $receipt['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit"></i> Devam Et
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

            <!-- Tamamlanan Mal Kabul İşlemleri -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i> Tamamlanan Mal Kabul İşlemleri
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($completed_receipts)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-history fa-2x mb-2"></i>
                            <p>Henüz tamamlanmış mal kabul işlemi bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Mal Kabul No</th>
                                        <th>Sipariş No</th>
                                        <th>Tedarikçi</th>
                                        <th>Kontrol Tarihi</th>
                                        <th>Durum</th>
                                        <th>Kontrolör</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_receipts as $receipt): ?>
                                    <tr>
                                        <td><strong><?php echo $receipt['receipt_number']; ?></strong></td>
                                        <td><?php echo $receipt['order_number']; ?></td>
                                        <td><?php echo $receipt['supplier_name']; ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($receipt['receipt_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_badges[$receipt['status']]; ?>">
                                                <?php echo ucfirst($receipt['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $receipt['inspector_name']; ?></td>
                                        <td>
                                            <a href="goods_receipt_details.php?id=<?php echo $receipt['id']; ?>" class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-eye"></i> Görüntüle
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
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>