<?php
// pages/production_orders.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü - Üretim ve Yönetici
if (!hasPermission('production_movements') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Filtreleme parametreleri
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

try {
    $db = getDBConnection();
    
    // Üretim emirlerini getir
    $sql = "
        SELECT po.*, u.full_name as created_by_name,
               COUNT(poi.id) as item_count,
               SUM(poi.required_quantity) as total_required,
               SUM(poi.issued_quantity) as total_issued,
               (SUM(poi.issued_quantity) / SUM(poi.required_quantity) * 100) as completion_rate
        FROM production_orders po
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN production_order_items poi ON po.id = poi.production_order_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status_filter) {
        $sql .= " AND po.status = ?";
        $params[] = $status_filter;
    }
    
    if ($priority_filter) {
        $sql .= " AND po.priority = ?";
        $params[] = $priority_filter;
    }
    
    $sql .= " GROUP BY po.id ORDER BY 
             CASE po.priority 
                 WHEN 'urgent' THEN 1
                 WHEN 'high' THEN 2
                 WHEN 'medium' THEN 3
                 WHEN 'low' THEN 4
             END, po.planned_date ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $production_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h2"><i class="fas fa-industry"></i> Üretim Emirleri</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                    <i class="fas fa-plus"></i> Yeni Üretim Emri
                </button>
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

            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtreler</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <option value="">Tüm Durumlar</option>
                                <option value="planned" <?php echo $status_filter == 'planned' ? 'selected' : ''; ?>>Planlandı</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>Üretimde</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Öncelik</label>
                            <select class="form-select" name="priority">
                                <option value="">Tüm Öncelikler</option>
                                <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>Acil</option>
                                <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>Yüksek</option>
                                <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Orta</option>
                                <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Düşük</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="production_orders.php" class="btn btn-secondary">Sıfırla</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Üretim Emirleri Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> Üretim Emirleri</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($production_orders)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Henüz üretim emri bulunmamaktadır.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                                <i class="fas fa-plus"></i> İlk Üretim Emrini Oluşturun
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Emir No</th>
                                        <th>Ürün</th>
                                        <th>Hedef Miktar</th>
                                        <th>Planlanan Tarih</th>
                                        <th>Öncelik</th>
                                        <th>Durum</th>
                                        <th>Malzeme Durumu</th>
                                        <th>Oluşturan</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($production_orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo $order['order_number']; ?></strong></td>
                                        <td><?php echo $order['product_name']; ?></td>
                                        <td><?php echo $order['target_quantity']; ?> adet</td>
                                        <td><?php echo date('d.m.Y', strtotime($order['planned_date'])); ?></td>
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
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar 
                                                    <?php echo $order['completion_rate'] >= 100 ? 'bg-success' : 
                                                           ($order['completion_rate'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo min($order['completion_rate'], 100); ?>%"
                                                    aria-valuenow="<?php echo $order['completion_rate']; ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100">
                                                    <?php echo round($order['completion_rate'], 1); ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $order['total_issued']; ?> / <?php echo $order['total_required']; ?>
                                            </small>
                                        </td>
                                        <td><?php echo $order['created_by_name']; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="production_order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasPermission('all') || $_SESSION['user_id'] == $order['created_by']): ?>
                                                <a href="production_order_edit.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($order['status'] == 'planned' || $order['status'] == 'in_progress'): ?>
                                                <a href="production_issue.php?order_id=<?php echo $order['id']; ?>" class="btn btn-outline-success">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- İstatistikler -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <h4><?php echo count($production_orders); ?></h4>
                            <p>Toplam Emir</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($production_orders, function($o) { return $o['status'] == 'in_progress'; })); ?></h4>
                            <p>Üretimde</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($production_orders, function($o) { return $o['status'] == 'completed'; })); ?></h4>
                            <p>Tamamlandı</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <h4><?php echo array_sum(array_column($production_orders, 'target_quantity')); ?></h4>
                            <p>Hedef Üretim</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Yeni Üretim Emri Modal -->
<div class="modal fade" id="newOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="production_orders_action.php" method="POST" id="newOrderForm">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Üretim Emri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_order">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Üretim Emri No *</label>
                                <input type="text" class="form-control" name="order_number" 
                                       value="ÜE-<?php echo date('Ymd-His'); ?>" required readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Ürün Adı *</label>
                                <input type="text" class="form-control" name="product_name" 
                                       placeholder="Elektrikli Scooter V2" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Hedef Miktar *</label>
                                <input type="number" class="form-control" name="target_quantity" 
                                       min="1" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Planlanan Tarih *</label>
                                <input type="date" class="form-control" name="planned_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Son Teslim Tarihi</label>
                                <input type="date" class="form-control" name="deadline_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Öncelik *</label>
                                <select class="form-select" name="priority" required>
                                    <option value="medium">Orta</option>
                                    <option value="low">Düşük</option>
                                    <option value="high">Yüksek</option>
                                    <option value="urgent">Acil</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Durum *</label>
                                <select class="form-select" name="status" required>
                                    <option value="planned">Planlandı</option>
                                    <option value="in_progress">Üretimde</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Üretim notları..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Emri Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>