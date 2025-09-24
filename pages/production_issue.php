<?php
// pages/production_issue.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

if (!hasPermission('production_movements') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['order_id'])) {
    header('Location: production_orders.php');
    exit();
}

$order_id = intval($_GET['order_id']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

try {
    $db = getDBConnection();
    
    // Üretim emri bilgilerini getir
    $stmt = $db->prepare("
        SELECT po.*, u.full_name as created_by_name
        FROM production_orders po
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ? AND po.status IN ('planned', 'in_progress')
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Üretim emri bulunamadı veya malzeme çıkışı için uygun değil!');
    }
    
    // Üretim için gereken malzemeleri getir (kalan miktarlarla)
    $stmt = $db->prepare("
        SELECT poi.*, i.code, i.name, i.description, u.name as uom_name,
               (SELECT COALESCE(SUM(quantity), 0) FROM stock_movement_lines WHERE item_id = i.id) as current_stock,
               (poi.required_quantity - poi.issued_quantity) as remaining_quantity
        FROM production_order_items poi
        LEFT JOIN items i ON poi.item_id = i.id
        LEFT JOIN uoms u ON i.uom_id = u.id
        WHERE poi.production_order_id = ?
        HAVING remaining_quantity > 0
        ORDER BY poi.id
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($order_items)) {
        $error = 'Bu üretim emri için çıkarılacak malzeme kalmamıştır!';
    }
    
} catch(PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_issue') {
        try {
            $db->beginTransaction();
            
            // Malzeme çıkış kaydı oluştur
            $issue_number = 'ÇK-' . date('Ymd') . '-' . sprintf('%04d', $order_id);
            $stmt = $db->prepare("
                INSERT INTO production_issues (production_order_id, issue_number, issue_date, issued_by, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $issue_number,
                $_POST['issue_date'] ?? date('Y-m-d'),
                $_SESSION['user_id'],
                trim($_POST['notes'] ?? '')
            ]);
            
            $issue_id = $db->lastInsertId();
            $has_valid_issue = false;
            
            // Çıkış kalemlerini ekle
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $order_item_id => $item_data) {
                    $issue_quantity = floatval($item_data['issue_quantity'] ?? 0);
                    
                    if ($issue_quantity > 0) {
                        // Kalan miktarı kontrol et
                        $remaining_qty = 0;
                        foreach ($order_items as $item) {
                            if ($item['id'] == $order_item_id) {
                                $remaining_qty = $item['remaining_quantity'];
                                break;
                            }
                        }
                        
                        if ($issue_quantity <= $remaining_qty) {
                            $stmt = $db->prepare("
                                INSERT INTO production_issue_items 
                                (production_issue_id, production_order_item_id, quantity, notes) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $issue_id,
                                $order_item_id,
                                $issue_quantity,
                                $item_data['notes'] ?? ''
                            ]);
                            
                            // Üretim emri kalemindeki çıkarılan miktarı güncelle
                            $stmt = $db->prepare("
                                UPDATE production_order_items 
                                SET issued_quantity = issued_quantity + ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$issue_quantity, $order_item_id]);
                            
                            // Stok hareketi oluştur (çıkış)
                            $stmt = $db->prepare("
                                INSERT INTO stock_movements 
                                (movement_type_id, movement_date, reference_no, description, created_by) 
                                VALUES (2, ?, ?, 'Üretim malzeme çıkışı', ?)
                            ");
                            $reference_no = 'ÜRETIM-' . $issue_number;
                            $stmt->execute([
                                $_POST['issue_date'] ?? date('Y-m-d'),
                                $reference_no,
                                $_SESSION['user_id']
                            ]);
                            $movement_id = $db->lastInsertId();
                            
                            // Stok hareket detayı
                            $item_id = 0;
                            foreach ($order_items as $item) {
                                if ($item['id'] == $order_item_id) {
                                    $item_id = $item['item_id'];
                                    break;
                                }
                            }
                            
                            $stmt = $db->prepare("
                                INSERT INTO stock_movement_lines 
                                (stock_movement_id, item_id, quantity) 
                                VALUES (?, ?, ?)
                            ");
                            $stmt->execute([$movement_id, $item_id, -$issue_quantity]); // Eksi işaretli (çıkış)
                            
                            $has_valid_issue = true;
                        }
                    }
                }
            }
            
            if (!$has_valid_issue) {
                throw new Exception('Geçerli bir çıkış miktarı girilmedi!');
            }
            
            // Üretim emri durumunu güncelle (eğer ilk çıkışsa)
            if ($order['status'] == 'planned') {
                $stmt = $db->prepare("UPDATE production_orders SET status = 'in_progress' WHERE id = ?");
                $stmt->execute([$order_id]);
            }
            
            // Tüm malzemeler tamamlandı mı kontrol et
            $stmt = $db->prepare("
                SELECT COUNT(*) as pending_count 
                FROM production_order_items 
                WHERE production_order_id = ? AND issued_quantity < required_quantity
            ");
            $stmt->execute([$order_id]);
            $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
            
            if ($pending_count == 0) {
                $stmt = $db->prepare("UPDATE production_orders SET status = 'completed' WHERE id = ?");
                $stmt->execute([$order_id]);
            }
            
            $db->commit();
            $_SESSION['success'] = 'Malzeme çıkışı başarıyla kaydedildi!';
            header('Location: production_order_details.php?id=' . $order_id);
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Malzeme çıkışı kaydedilirken hata: ' . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-sign-out-alt"></i> Üretim Malzeme Çıkışı</h1>
                <a href="production_order_details.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Emir Detayları
                </a>
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

            <!-- Üretim Emri Bilgileri -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Üretim Emri Bilgileri</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>Emir No:</th><td><strong><?php echo $order['order_number']; ?></strong></td></tr>
                                <tr><th>Ürün:</th><td><?php echo $order['product_name']; ?></td></tr>
                                <tr><th>Hedef Miktar:</th><td><?php echo $order['target_quantity']; ?> adet</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>Durum:</th>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'planned' => 'secondary',
                                            'in_progress' => 'primary',
                                            'completed' => 'success'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_badges[$order['status']]; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr><th>Planlanan Tarih:</th><td><?php echo date('d.m.Y', strtotime($order['planned_date'])); ?></td></tr>
                                <tr><th>Öncelik:</th>
                                    <td>
                                        <?php 
                                        $priority_badges = [
                                            'urgent' => 'danger',
                                            'high' => 'warning',
                                            'medium' => 'info'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $priority_badges[$order['priority']]; ?>">
                                            <?php echo ucfirst($order['priority']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($order_items)): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Çıkarılacak Malzeme Yok</h5>
                    <p>Bu üretim emri için çıkarılacak malzeme kalmamıştır. Tüm malzemeler çıkarılmış olabilir.</p>
                    <a href="production_order_details.php?id=<?php echo $order_id; ?>" class="btn btn-primary">
                        Emir Detaylarına Dön
                    </a>
                </div>
            <?php else: ?>
                <!-- Malzeme Çıkış Formu -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Malzeme Çıkış Formu</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="issueForm">
                            <input type="hidden" name="action" value="create_issue">
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Çıkış Tarihi *</label>
                                        <input type="date" class="form-control" name="issue_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Çıkış Numarası</label>
                                        <input type="text" class="form-control" 
                                               value="ÇK-<?php echo date('Ymd') . '-' . sprintf('%04d', $order_id); ?>" 
                                               readonly>
                                        <small class="form-text text-muted">Otomatik oluşturulacak</small>
                                    </div>
                                </div>
                            </div>

                            <h6>Çıkarılacak Malzemeler</h6>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Malzeme</th>
                                            <th>Birim</th>
                                            <th>Gereken</th>
                                            <th>Çıkarılan</th>
                                            <th>Kalan</th>
                                            <th>Mevcut Stok</th>
                                            <th>Çıkış Miktarı</th>
                                            <th>Notlar</th>
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
                                                <span class="badge bg-<?php echo $item['remaining_quantity'] > 0 ? 'warning' : 'success'; ?>">
                                                    <?php echo $item['remaining_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $item['current_stock'] >= $item['remaining_quantity'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $item['current_stock']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm" 
                                                       name="items[<?php echo $item['id']; ?>][issue_quantity]"
                                                       value="0"
                                                       min="0" 
                                                       max="<?php echo min($item['remaining_quantity'], $item['current_stock']); ?>" 
                                                       step="0.01"
                                                       onchange="validateQuantity(this, <?php echo $item['remaining_quantity']; ?>, <?php echo $item['current_stock']; ?>)">
                                                <small class="text-muted">Max: <?php echo min($item['remaining_quantity'], $item['current_stock']); ?></small>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                       name="items[<?php echo $item['id']; ?>][notes]"
                                                       placeholder="Not...">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Genel Notlar</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Çıkış notları..."></textarea>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Malzeme Çıkışını Onayla
                                </button>
                                <a href="production_order_details.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">İptal</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// Miktar validasyonu
function validateQuantity(input, remainingQuantity, currentStock) {
    const maxQuantity = Math.min(remainingQuantity, currentStock);
    const enteredQuantity = parseFloat(input.value) || 0;
    
    if (enteredQuantity > maxQuantity) {
        alert('Girilen miktar maksimum değeri aşıyor! Maksimum: ' + maxQuantity);
        input.value = maxQuantity;
    }
    
    if (enteredQuantity < 0) {
        input.value = 0;
    }
}

// Form validasyonu
document.getElementById('issueForm').addEventListener('submit', function(e) {
    let hasIssue = false;
    const inputs = document.querySelectorAll('input[name*="issue_quantity"]');
    
    inputs.forEach(input => {
        if (parseFloat(input.value) > 0) {
            hasIssue = true;
        }
    });
    
    if (!hasIssue) {
        e.preventDefault();
        alert('En az bir malzeme için çıkış miktarı girmelisiniz!');
        return false;
    }
    
    // Stok yeterliliği kontrolü
    let insufficientStock = false;
    inputs.forEach(input => {
        const quantity = parseFloat(input.value) || 0;
        const maxQuantity = parseFloat(input.max);
        
        if (quantity > 0 && quantity > maxQuantity) {
            insufficientStock = true;
        }
    });
    
    if (insufficientStock) {
        e.preventDefault();
        alert('Bazı malzemeler için yeterli stok bulunmamaktadır! Lütfen miktarları kontrol edin.');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/header.php'; ?>