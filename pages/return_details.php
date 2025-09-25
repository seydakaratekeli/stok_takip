<?php
// pages/return_details.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

if (!hasPermission('iade') && !hasPermission('uretim_iade') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['id'])) {
    header('Location: returns.php');
    exit();
}

$return_id = intval($_GET['id']);

try {
    $db = getDBConnection();
    
    // İade bilgilerini getir
    $stmt = $db->prepare("
        SELECT r.*, rr.name as reason_name, rr.description as reason_desc,
               u1.full_name as created_by_name, u2.full_name as approved_by_name,
               o.order_number, s.name as supplier_name,
               sm.reference_no as movement_ref
        FROM returns r
        LEFT JOIN return_reasons rr ON r.reason_id = rr.id
        LEFT JOIN users u1 ON r.created_by = u1.id
        LEFT JOIN users u2 ON r.approved_by = u2.id
        LEFT JOIN orders o ON r.related_order_id = o.id
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN stock_movements sm ON r.related_movement_id = sm.id
        WHERE r.id = ?
    ");
    $stmt->execute([$return_id]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) {
        die('İade bulunamadı!');
    }
    
    // İade kalemlerini getir
    $stmt = $db->prepare("
        SELECT ri.*, i.code, i.name, i.description, u.name as uom_name
        FROM return_items ri
        LEFT JOIN items i ON ri.item_id = i.id
        LEFT JOIN uoms u ON i.uom_id = u.id
        WHERE ri.return_id = ?
        ORDER BY ri.id
    ");
    $stmt->execute([$return_id]);
    $return_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İade aktivitelerini getir
    $stmt = $db->prepare("
        SELECT ra.*, u.full_name as created_by_name
        FROM return_activities ra
        LEFT JOIN users u ON ra.created_by = u.id
        WHERE ra.return_id = ?
        ORDER BY ra.created_at DESC
    ");
    $stmt->execute([$return_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h2"><i class="fas fa-undo-alt"></i> İade Detayları</h1>
                <div>
                    <a href="returns.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> İade Listesi
                    </a>
                    <?php if (hasPermission('all') || $return['created_by'] == $_SESSION['user_id']): ?>
                    <a href="return_edit.php?id=<?php echo $return_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Düzenle
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- İade Bilgileri -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">İade Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>İade No:</th><td><strong><?php echo $return['return_number']; ?></strong></td></tr>
                                <tr><th>Tür:</th><td>
                                    <span class="badge bg-<?php echo $return['return_type'] == 'supplier' ? 'primary' : 'info'; ?>">
                                        <?php echo $return['return_type'] == 'supplier' ? 'Tedarikçi İadesi' : 'Üretim İadesi'; ?>
                                    </span>
                                </td></tr>
                                <tr><th>Durum:</th><td>
                                    <?php
                                    $status_badge = [
                                        'pending' => ['bg-warning', 'Beklemede'],
                                        'approved' => ['bg-info', 'Onaylandı'],
                                        'completed' => ['bg-success', 'Tamamlandı'],
                                        'rejected' => ['bg-danger', 'Reddedildi']
                                    ];
                                    $status = $status_badge[$return['status']];
                                    ?>
                                    <span class="badge <?php echo $status[0]; ?>"><?php echo $status[1]; ?></span>
                                </td></tr>
                                <tr><th>İade Tarihi:</th><td><?php echo date('d.m.Y', strtotime($return['return_date'])); ?></td></tr>
                                <tr><th>Toplam Miktar:</th><td><strong><?php echo $return['total_quantity']; ?></strong></td></tr>
                                <tr><th>Oluşturan:</th><td><?php echo $return['created_by_name']; ?></td></tr>
                                <?php if ($return['approved_by_name']): ?>
                                <tr><th>Onaylayan:</th><td><?php echo $return['approved_by_name']; ?></td></tr>
                                <tr><th>Onay Tarihi:</th><td><?php echo date('d.m.Y H:i', strtotime($return['approved_at'])); ?></td></tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">İade Detayları</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>İade Sebebi:</th><td><strong><?php echo $return['reason_name']; ?></strong></td></tr>
                                <tr><th>Sebep Açıklama:</th><td><?php echo $return['reason_desc'] ?: '-'; ?></td></tr>
                                
                                <?php if ($return['return_type'] == 'supplier' && $return['order_number']): ?>
                                <tr><th>İlgili Sipariş:</th><td><?php echo $return['order_number']; ?></td></tr>
                                <tr><th>Tedarikçi:</th><td><?php echo $return['supplier_name']; ?></td></tr>
                                <?php elseif ($return['return_type'] == 'production' && $return['movement_ref']): ?>
                                <tr><th>İlgili Hareket:</th><td><?php echo $return['movement_ref']; ?></td></tr>
                                <?php endif; ?>
                            </table>
                            
                            <?php if ($return['description']): ?>
                            <div class="mt-3">
                                <strong>Açıklama:</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($return['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İade Kalemleri -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">İade Kalemleri</h5>
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
                                <?php foreach ($return_items as $item): ?>
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
                        </table>
                    </div>
                </div>
            </div>

            <!-- Durum Yönetimi -->
            <?php if ($return['status'] == 'pending' && (hasPermission('all') || $return['created_by'] == $_SESSION['user_id'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">İade Durumunu Yönet</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="returns_action.php?action=update_status&id=<?php echo $return_id; ?>&status=approved" 
                               class="btn btn-success w-100"
                               onclick="return confirm('Bu iadeyi onaylamak istediğinizden emin misiniz? Stok hareketi oluşturulacak.')">
                                <i class="fas fa-check"></i> Onayla
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="returns_action.php?action=update_status&id=<?php echo $return_id; ?>&status=rejected" 
                               class="btn btn-danger w-100"
                               onclick="return confirm('Bu iadeyi reddetmek istediğinizden emin misiniz?')">
                                <i class="fas fa-times"></i> Reddet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif ($return['status'] == 'approved' && hasPermission('all')): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">İadeyi Tamamla</h5>
                </div>
                <div class="card-body">
                    <a href="returns_action.php?action=update_status&id=<?php echo $return_id; ?>&status=completed" 
                       class="btn btn-primary"
                       onclick="return confirm('Bu iadeyi tamamlamak istediğinizden emin misiniz?')">
                        <i class="fas fa-flag-checkered"></i> Tamamla
                    </a>
                </div>
            </div>
            <?php endif; ?>

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