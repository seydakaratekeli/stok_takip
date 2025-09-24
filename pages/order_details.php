<?php
// pages/order_details.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

if (!hasPermission('siparis') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = intval($_GET['id']);

try {
    $db = getDBConnection();
    
    // Sipariş bilgilerini getir
    $stmt = $db->prepare("
        SELECT o.*, s.name as supplier_name, s.contact_person, s.phone, s.email,
               os.name as status_name, os.color as status_color, os.id as status_id,
               u.full_name as created_by_name
        FROM orders o
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN order_statuses os ON o.status_id = os.id
        LEFT JOIN users u ON o.created_by = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Sipariş bulunamadı!');
    }
    
    // Sipariş kalemlerini getir
    $stmt = $db->prepare("
        SELECT oi.*, i.code, i.name, i.description, u.name as uom_name
        FROM order_items oi
        LEFT JOIN items i ON oi.item_id = i.id
        LEFT JOIN uoms u ON i.uom_id = u.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sipariş aktivitelerini getir
    $stmt = $db->prepare("
        SELECT oa.*, u.full_name as created_by_name
        FROM order_activities oa
        LEFT JOIN users u ON oa.created_by = u.id
        WHERE oa.order_id = ?
        ORDER BY oa.created_at DESC
    ");
    $stmt->execute([$order_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sipariş durumları
    $stmt = $db->query("SELECT * FROM order_statuses ORDER BY id");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h2"><i class="fas fa-shopping-cart"></i> Sipariş Detayları</h1>
                <div>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Sipariş Listesi
                    </a>
                    <?php if (hasPermission('all') || $_SESSION['user_id'] == $order['created_by']): ?>
                    <a href="order_edit.php?id=<?php echo $order_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Düzenle
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('all') || $_SESSION['user_id'] == $order['created_by']): ?>
<button class="btn btn-outline-danger" 
        onclick="confirmDelete(<?php echo $order_id; ?>, '<?php echo $order['order_number']; ?>')">
    <i class="fas fa-trash"></i> Sil
</button>
<?php endif; ?>

<script>
function confirmDelete(orderId, orderNumber) {
    if (confirm('"' + orderNumber + '" siparişini silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!')) {
        window.location.href = 'orders_action.php?action=delete&id=' + orderId;
    }
}
</script>
                </div>
            </div>

            <!-- Sipariş Bilgileri -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Sipariş No:</th><td><strong><?php echo $order['order_number']; ?></strong></td></tr>
                                <tr><th>Durum:</th><td><span class="badge" style="background-color: <?php echo $order['status_color']; ?>"><?php echo $order['status_name']; ?></span></td></tr>
                                <tr><th>Sipariş Tarihi:</th><td><?php echo date('d.m.Y', strtotime($order['order_date'])); ?></td></tr>
                                <tr><th>Beklenen Teslim:</th><td><?php echo $order['expected_date'] ? date('d.m.Y', strtotime($order['expected_date'])) : '<span class="text-muted">Belirtilmemiş</span>'; ?></td></tr>
                                <tr><th>Toplam Tutar:</th><td><strong><?php echo number_format($order['total_amount'], 2); ?> TL</strong></td></tr>
                                <tr><th>Oluşturan:</th><td><?php echo $order['created_by_name']; ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Tedarikçi Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Firma:</th><td><strong><?php echo $order['supplier_name']; ?></strong></td></tr>
                                <tr><th>İlgili Kişi:</th><td><?php echo $order['contact_person'] ?: '-'; ?></td></tr>
                                <tr><th>Telefon:</th><td><?php echo $order['phone'] ?: '-'; ?></td></tr>
                                <tr><th>E-posta:</th><td><?php echo $order['email'] ?: '-'; ?></td></tr>
                            </table>
                            
                            <?php if ($order['notes']): ?>
                            <div class="mt-3">
                                <strong>Notlar:</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sipariş Kalemleri -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sipariş Kalemleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Malzeme Kodu</th>
                                    <th>Malzeme Adı</th>
                                    <th>Birim</th>
                                    <th>Miktar</th>
                                    <th>Birim Fiyat</th>
                                    <th>Toplam Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><strong><?php echo $item['code']; ?></strong></td>
                                    <td><?php echo $item['name']; ?></td>
                                    <td><?php echo $item['uom_name']; ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo number_format($item['unit_price'], 2); ?> TL</td>
                                    <td><strong><?php echo number_format($item['total_price'], 2); ?> TL</strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <td colspan="5" class="text-end"><strong>Genel Toplam:</strong></td>
                                    <td><strong><?php echo number_format($order['total_amount'], 2); ?> TL</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Durum Yönetimi -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sipariş Durumunu Güncelle</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($statuses as $status): ?>
                        <div class="col-md-2 mb-2">
                            <a href="orders_action.php?action=update_status&id=<?php echo $order_id; ?>&status=<?php echo $status['id']; ?>" 
                               class="btn btn-sm w-100 <?php echo $order['status_id'] == $status['id'] ? 'active' : 'outline-'; ?>" 
                               style="background-color: <?php echo $status['color']; ?>; border-color: <?php echo $status['color']; ?>; color: white;"
                               onclick="return confirm('Sipariş durumunu \"<?php echo $status['name']; ?>\" olarak güncellemek istediğinizden emin misiniz?')">
                                <?php echo $status['name']; ?>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Aktivite Geçmişi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Aktivite Geçmişi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <p class="text-muted">Henüz aktivite bulunmamaktadır.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activities as $activity): ?>
                            <div class="timeline-item mb-3">
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo $activity['description']; ?></strong>
                                        <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                    <small class="text-muted"><?php echo $activity['created_by_name']; ?> tarafından</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>