<?php
// pages/order_edit.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü - Satınalma ve Yönetici
if (!hasPermission('siparis') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = intval($_GET['id']);

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
        SELECT o.*, s.name as supplier_name, os.name as status_name
        FROM orders o
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN order_statuses os ON o.status_id = os.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Sipariş bulunamadı!');
    }
    
    // Sadece siparişi oluşturan veya yönetici düzenleyebilir
    if ($_SESSION['user_id'] != $order['created_by'] && !hasPermission('all')) {
        die('Bu siparişi düzenleme yetkiniz yok!');
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
    
    // Tedarikçiler
    $stmt = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Malzemeler
    $stmt = $db->query("SELECT * FROM items ORDER BY name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sipariş durumları
    $stmt = $db->query("SELECT * FROM order_statuses ORDER BY id");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_order') {
        try {
            $db->beginTransaction();
            
            // Sipariş bilgilerini güncelle
            $stmt = $db->prepare("
                UPDATE orders 
                SET supplier_id = ?, order_date = ?, expected_date = ?, status_id = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['supplier_id'],
                $_POST['order_date'],
                $_POST['expected_date'] ?: null,
                $_POST['status_id'],
                trim($_POST['notes'] ?? ''),
                $order_id
            ]);
            
            // Mevcut kalemleri sil
            $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            $total_amount = 0;
            
            // Yeni kalemleri ekle
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item_data) {
                    if (!empty($item_data['item_id']) && !empty($item_data['quantity']) && !empty($item_data['unit_price'])) {
                        $item_id = intval($item_data['item_id']);
                        $quantity = floatval($item_data['quantity']);
                        $unit_price = floatval($item_data['unit_price']);
                        $total_price = $quantity * $unit_price;
                        
                        $stmt = $db->prepare("
                            INSERT INTO order_items (order_id, item_id, quantity, unit_price, total_price, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $order_id,
                            $item_id,
                            $quantity,
                            $unit_price,
                            $total_price,
                            $item_data['notes'] ?? ''
                        ]);
                        
                        $total_amount += $total_price;
                    }
                }
            }
            
            // Toplam tutarı güncelle
            $stmt = $db->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $stmt->execute([$total_amount, $order_id]);
            
            // Aktivite ekle
            $stmt = $db->prepare("
                INSERT INTO order_activities (order_id, activity_type, description, created_by) 
                VALUES (?, 'updated', 'Sipariş bilgileri güncellendi', ?)
            ");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            
            $db->commit();
            $_SESSION['success'] = 'Sipariş başarıyla güncellendi!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Sipariş güncellenirken hata: ' . $e->getMessage();
        }
        
        header('Location: order_details.php?id=' . $order_id);
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-edit"></i> Sipariş Düzenle</h1>
                <div>
                    <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> İptal
                    </a>
                </div>
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

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sipariş Bilgilerini Düzenle</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="editOrderForm">
                        <input type="hidden" name="action" value="update_order">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sipariş Numarası</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($order['order_number']); ?>" readonly>
                                    <small class="form-text text-muted">Sipariş numarası değiştirilemez</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sipariş Tarihi *</label>
                                    <input type="date" class="form-control" name="order_date" 
                                           value="<?php echo $order['order_date']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tedarikçi *</label>
                                    <select class="form-select" name="supplier_id" required>
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>" 
                                                <?php echo $order['supplier_id'] == $supplier['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Beklenen Teslim Tarihi</label>
                                    <input type="date" class="form-control" name="expected_date" 
                                           value="<?php echo $order['expected_date'] ?: ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sipariş Durumu *</label>
                                    <select class="form-select" name="status_id" required>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo $status['id']; ?>" 
                                                <?php echo $order['status_id'] == $status['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notlar</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Sipariş notları..."><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <hr>
                        
                        <h5>Sipariş Kalemleri</h5>
                        <div id="orderItems">
                            <?php foreach ($order_items as $index => $item): ?>
                            <div class="order-item row mb-2">
                                <div class="col-md-5">
                                    <select class="form-select" name="items[<?php echo $index; ?>][item_id]" required>
                                        <option value="">Malzeme seçin</option>
                                        <?php foreach ($items as $itm): ?>
                                            <option value="<?php echo $itm['id']; ?>" 
                                                <?php echo $item['item_id'] == $itm['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($itm['code'] . ' - ' . $itm['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control" name="items[<?php echo $index; ?>][quantity]" 
                                           value="<?php echo $item['quantity']; ?>" placeholder="Miktar" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control" name="items[<?php echo $index; ?>][unit_price]" 
                                           value="<?php echo $item['unit_price']; ?>" placeholder="Birim Fiyat" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" value="<?php echo number_format($item['total_price'], 2); ?>" placeholder="Toplam" readonly>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm remove-item" 
                                            <?php echo count($order_items) <= 1 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" class="btn btn-secondary btn-sm mt-2" id="addMoreItems">
                            <i class="fas fa-plus"></i> Kalem Ekle
                        </button>
                        
                        <div class="mt-3">
                            <strong>Toplam Tutar: </strong>
                            <span id="totalAmount"><?php echo number_format($order['total_amount'], 2); ?> TL</span>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Değişiklikleri Kaydet
                            </button>
                            <a href="order_details.php?id=<?php echo $order_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Dinamik sipariş kalemi ekleme
document.getElementById('addMoreItems').addEventListener('click', function() {
    const itemsContainer = document.getElementById('orderItems');
    const itemCount = document.querySelectorAll('.order-item').length;
    
    const newItem = document.createElement('div');
    newItem.className = 'order-item row mb-2';
    newItem.innerHTML = `
        <div class="col-md-5">
            <select class="form-select" name="items[${itemCount}][item_id]" required>
                <option value="">Malzeme seçin</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>">
                        <?php echo htmlspecialchars($item['code'] . ' - ' . $item['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control" name="items[${itemCount}][quantity]" 
                   placeholder="Miktar" step="0.01" min="0.01" required>
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control" name="items[${itemCount}][unit_price]" 
                   placeholder="Birim Fiyat" step="0.01" min="0" required>
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control" placeholder="Toplam" readonly>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm remove-item">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    itemsContainer.appendChild(newItem);
    updateRemoveButtons();
    attachItemEventListeners(newItem);
});

// Toplam tutar hesaplama
function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.order-item').forEach(item => {
        const quantity = parseFloat(item.querySelector('input[name*="quantity"]').value) || 0;
        const unitPrice = parseFloat(item.querySelector('input[name*="unit_price"]').value) || 0;
        const totalPrice = quantity * unitPrice;
        
        item.querySelector('input[placeholder="Toplam"]').value = totalPrice.toFixed(2);
        total += totalPrice;
    });
    
    document.getElementById('totalAmount').textContent = total.toFixed(2) + ' TL';
}

// Event listener'ları ekle
function attachItemEventListeners(container) {
    const quantityInput = container.querySelector('input[name*="quantity"]');
    const priceInput = container.querySelector('input[name*="unit_price"]');
    
    quantityInput.addEventListener('input', calculateTotal);
    priceInput.addEventListener('input', calculateTotal);
    
    const removeBtn = container.querySelector('.remove-item');
    removeBtn.addEventListener('click', function() {
        if (document.querySelectorAll('.order-item').length > 1) {
            container.remove();
            updateRemoveButtons();
            calculateTotal();
        }
    });
}

// Silme butonlarını güncelle
function updateRemoveButtons() {
    const items = document.querySelectorAll('.order-item');
    items.forEach((item, index) => {
        const removeBtn = item.querySelector('.remove-item');
        removeBtn.disabled = items.length <= 1;
    });
}

// İlk yüklemede event listener'ları ekle
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.order-item').forEach(attachItemEventListeners);
    updateRemoveButtons();
    
    // Mevcut kalemlerin toplamlarını hesapla
    calculateTotal();
});

// Form gönderiminden önce validasyon
document.getElementById('editOrderForm').addEventListener('submit', function(e) {
    const items = document.querySelectorAll('.order-item');
    let isValid = true;
    
    items.forEach(item => {
        const itemId = item.querySelector('select[name*="item_id"]').value;
        const quantity = item.querySelector('input[name*="quantity"]').value;
        const unitPrice = item.querySelector('input[name*="unit_price"]').value;
        
        if (!itemId || !quantity || !unitPrice) {
            isValid = false;
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Lütfen tüm kalem bilgilerini eksiksiz doldurun!');
        return false;
    }
    
    if (items.length === 0) {
        e.preventDefault();
        alert('En az bir sipariş kalemi eklemelisiniz!');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>