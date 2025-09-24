<?php
// pages/production_issue_details.php
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

$issue_id = intval($_GET['id']);

try {
    $db = getDBConnection();
    
    // Çıkış bilgilerini getir
    $stmt = $db->prepare("
        SELECT pi.*, po.order_number, po.product_name, po.target_quantity,
               u.full_name as issued_by_name
        FROM production_issues pi
        LEFT JOIN production_orders po ON pi.production_order_id = po.id
        LEFT JOIN users u ON pi.issued_by = u.id
        WHERE pi.id = ?
    ");
    $stmt->execute([$issue_id]);
    $issue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$issue) {
        die('Çıkış kaydı bulunamadı!');
    }
    
    // Çıkış kalemlerini getir
    $stmt = $db->prepare("
        SELECT pii.*, poi.required_quantity, poi.issued_quantity,
               i.code, i.name, i.description, u.name as uom_name
        FROM production_issue_items pii
        LEFT JOIN production_order_items poi ON pii.production_order_item_id = poi.id
        LEFT JOIN items i ON poi.item_id = i.id
        LEFT JOIN uoms u ON i.uom_id = u.id
        WHERE pii.production_issue_id = ?
        ORDER BY pii.id
    ");
    $stmt->execute([$issue_id]);
    $issue_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam miktar
    $total_quantity = array_sum(array_column($issue_items, 'quantity'));
    
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
                <h1 class="h2"><i class="fas fa-sign-out-alt"></i> Malzeme Çıkış Detayları</h1>
                <div>
                    <a href="production_order_details.php?id=<?php echo $issue['production_order_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Emir Detayları
                    </a>
                </div>
            </div>

            <!-- Çıkış Bilgileri -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Çıkış Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Çıkış No:</th><td><strong><?php echo $issue['issue_number']; ?></strong></td></tr>
                                <tr><th>Çıkış Tarihi:</th><td><?php echo date('d.m.Y', strtotime($issue['issue_date'])); ?></td></tr>
                                <tr><th>Toplam Miktar:</th><td><strong><?php echo $total_quantity; ?></strong></td></tr>
                                <tr><th>Çıkaran:</th><td><?php echo $issue['issued_by_name']; ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Üretim Emri Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Emir No:</th><td><strong><?php echo $issue['order_number']; ?></strong></td></tr>
                                <tr><th>Ürün:</th><td><?php echo $issue['product_name']; ?></td></tr>
                                <tr><th>Hedef Miktar:</th><td><?php echo $issue['target_quantity']; ?> adet</td></tr>
                                <tr><th>Kalem Sayısı:</th><td><?php echo count($issue_items); ?> malzeme</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Çıkış Kalemleri -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Çıkarılan Malzemeler</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Malzeme</th>
                                    <th>Birim</th>
                                    <th>Gereken Miktar</th>
                                    <th>Önce Çıkarılan</th>
                                    <th>Bu Çıkış</th>
                                    <th>Toplam Çıkarılan</th>
                                    <th>Kalan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issue_items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $item['code']; ?></strong><br>
                                        <small><?php echo $item['name']; ?></small>
                                    </td>
                                    <td><?php echo $item['uom_name']; ?></td>
                                    <td><?php echo $item['required_quantity']; ?></td>
                                    <td><?php echo $item['issued_quantity'] - $item['quantity']; ?></td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $item['quantity']; ?></span>
                                    </td>
                                    <td><?php echo $item['issued_quantity']; ?></td>
                                    <td>
                                        <?php 
                                        $remaining = $item['required_quantity'] - $item['issued_quantity'];
                                        $remaining_class = $remaining <= 0 ? 'success' : 'warning';
                                        ?>
                                        <span class="badge bg-<?php echo $remaining_class; ?>">
                                            <?php echo $remaining; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td colspan="4" class="text-end"><strong>Toplam:</strong></td>
                                    <td><strong><?php echo $total_quantity; ?></strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($issue['notes']): ?>
            <!-- Notlar -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Çıkış Notları</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br(htmlspecialchars($issue['notes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- İade Butonu -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">İşlemler</h5>
                </div>
                <div class="card-body">
                    <a href="production_return.php?issue_id=<?php echo $issue_id; ?>" class="btn btn-warning">
                        <i class="fas fa-undo"></i> Malzeme İadesi Yap
                    </a>
                    <button onclick="window.print()" class="btn btn-info">
                        <i class="fas fa-print"></i> Yazdır
                    </button>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/header.php'; ?>