<?php
// pages/goods_receipt_create.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

if (!hasPermission('mal_kabul') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['order_id'])) {
    header('Location: goods_receipt.php');
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
    
    // Sipariş bilgilerini getir
    $stmt = $db->prepare("
        SELECT o.*, s.name as supplier_name, s.contact_person, s.phone, s.email
        FROM orders o
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        WHERE o.id = ? AND o.status_id = 4
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Sipariş bulunamadı veya mal kabul için uygun değil!');
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
    
    // Bu sipariş için zaten mal kabul kaydı var mı kontrol et
    $stmt = $db->prepare("
        SELECT id FROM goods_receipts 
        WHERE order_id = ? AND status IN ('draft', 'inspecting')
    ");
    $stmt->execute([$order_id]);
    $existing_receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_receipt) {
        header('Location: goods_receipt_details.php?id=' . $existing_receipt['id']);
        exit();
    }
    
} catch(PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_receipt') {
        try {
            $db->beginTransaction();
            
            // Mal kabul kaydı oluştur
            $receipt_number = 'MK-' . date('Ymd') . '-' . sprintf('%04d', $order_id);
            $stmt = $db->prepare("
                INSERT INTO goods_receipts (order_id, receipt_number, receipt_date, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $receipt_number,
                $_POST['receipt_date'] ?? date('Y-m-d'),
                $_SESSION['user_id']
            ]);
            
            $receipt_id = $db->lastInsertId();
            
            // Mal kabul kalemlerini ekle
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $order_item_id => $item_data) {
                    $received_qty = floatval($item_data['received_quantity'] ?? 0);
                    
                    if ($received_qty > 0) {
                        $stmt = $db->prepare("
                            INSERT INTO goods_receipt_items 
                            (goods_receipt_id, order_item_id, expected_quantity, received_quantity) 
                            VALUES (?, ?, ?, ?)
                        ");
                        
                        // Sipariş kalemindeki miktarı bul
                        $expected_qty = 0;
                        foreach ($order_items as $item) {
                            if ($item['id'] == $order_item_id) {
                                $expected_qty = $item['quantity'];
                                break;
                            }
                        }
                        
                        $stmt->execute([
                            $receipt_id,
                            $order_item_id,
                            $expected_qty,
                            $received_qty
                        ]);
                    }
                }
            }
            
            // Sipariş durumunu güncelle
            $stmt = $db->prepare("UPDATE orders SET status_id = 5 WHERE id = ?"); // Mal Kabul Kontrolü
            $stmt->execute([$order_id]);
            
            $db->commit();
            $_SESSION['success'] = 'Mal kabul kaydı başarıyla oluşturuldu!';
            header('Location: goods_receipt_details.php?id=' . $receipt_id);
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Mal kabul kaydı oluşturulurken hata: ' . $e->getMessage();
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
                <h1 class="h2"><i class="fas fa-clipboard-check"></i> Yeni Mal Kabul Kaydı</h1>
                <a href="goods_receipt.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Geri Dön
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

            <!-- Sipariş Bilgileri -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>Sipariş No:</th><td><strong><?php echo $order['order_number']; ?></strong></td></tr>
                                <tr><th>Tedarikçi:</th><td><?php echo $order['supplier_name']; ?></td></tr>
                                <tr><th>İlgili Kişi:</th><td><?php echo $order['contact_person'] ?: '-'; ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr><th>Sipariş Tarihi:</th><td><?php echo date('d.m.Y', strtotime($order['order_date'])); ?></td></tr>
                                <tr><th>Beklenen Teslim:</th><td><?php echo $order['expected_date'] ? date('d.m.Y', strtotime($order['expected_date'])) : '-'; ?></td></tr>
                                <tr><th>Toplam Tutar:</th><td><strong><?php echo number_format($order['total_amount'], 2); ?> TL</strong></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mal Kabul Formu -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Mal Kabul Kaydı Oluştur</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="receiptForm">
                        <input type="hidden" name="action" value="create_receipt">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Mal Kabul Tarihi *</label>
                                    <input type="date" class="form-control" name="receipt_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Mal Kabul Numarası</label>
                                    <input type="text" class="form-control" 
                                           value="MK-<?php echo date('Ymd') . '-' . sprintf('%04d', $order_id); ?>" 
                                           readonly>
                                    <small class="form-text text-muted">Otomatik oluşturulacak</small>
                                </div>
                            </div>
                        </div>

                        <h6>Teslim Alınan Malzemeler</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Malzeme</th>
                                        <th>Birim</th>
                                        <th>Sipariş Miktarı</th>
                                        <th>Teslim Alınan Miktar</th>
                                        <th>Fark</th>
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
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $item['quantity']; ?></span>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" 
                                                   name="items[<?php echo $item['id']; ?>][received_quantity]" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="0" max="<?php echo $item['quantity'] * 1.1; ?>" 
                                                   step="0.01" required
                                                   onchange="calculateDifference(this, <?php echo $item['quantity']; ?>)">
                                        </td>
                                        <td>
                                            <span id="diff-<?php echo $item['id']; ?>" class="badge bg-success">0</span>
                                        </td>
                                        <td>
                                            <span id="status-<?php echo $item['id']; ?>" class="badge bg-success">Tam</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Mal Kabul Kaydını Oluştur
                            </button>
                            <a href="goods_receipt.php" class="btn btn-secondary">İptal</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Fark hesaplama ve durum güncelleme
function calculateDifference(input, expectedQuantity) {
    const receivedQuantity = parseFloat(input.value) || 0;
    const difference = receivedQuantity - expectedQuantity;
    const itemId = input.name.match(/\[(\d+)\]/)[1];
    
    const diffElement = document.getElementById('diff-' + itemId);
    const statusElement = document.getElementById('status-' + itemId);
    
    // Farkı güncelle
    diffElement.textContent = difference.toFixed(2);
    
    // Durumu güncelle
    if (difference === 0) {
        diffElement.className = 'badge bg-success';
        statusElement.className = 'badge bg-success';
        statusElement.textContent = 'Tam';
    } else if (difference > 0) {
        diffElement.className = 'badge bg-info';
        statusElement.className = 'badge bg-info';
        statusElement.textContent = 'Fazla';
    } else {
        diffElement.className = 'badge bg-warning';
        statusElement.className = 'badge bg-warning';
        statusElement.textContent = 'Eksik';
    }
}

// Form validasyonu
document.getElementById('receiptForm').addEventListener('submit', function(e) {
    let hasReceipt = false;
    const inputs = document.querySelectorAll('input[name*="received_quantity"]');
    
    inputs.forEach(input => {
        if (parseFloat(input.value) > 0) {
            hasReceipt = true;
        }
    });
    
    if (!hasReceipt) {
        e.preventDefault();
        alert('En az bir malzeme için teslim alınan miktar girmelisiniz!');
        return false;
    }
    
    return true;
});

// İlk yüklemede farkları hesapla
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[name*="received_quantity"]');
    inputs.forEach(input => {
        const expectedQuantity = parseFloat(input.max / 1.1); // max değerinden expected'i hesapla
        calculateDifference(input, expectedQuantity);
    });
});
</script>

<?php include '../includes/footer.php'; ?>