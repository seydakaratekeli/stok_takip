<?php
// pages/orders.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü - Satınalma ve Yönetici
if (!hasPermission('siparis') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

// Hata ve başarı mesajlarını kontrol et
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Filtreleme parametreleri
$status_filter = $_GET['status'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';

try {
    $db = getDBConnection();
    
    // Siparişleri getir
    $sql = "
        SELECT o.*, s.name as supplier_name, os.name as status_name, os.color as status_color,
               u.full_name as created_by_name, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN order_statuses os ON o.status_id = os.id
        LEFT JOIN users u ON o.created_by = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status_filter) {
        $sql .= " AND o.status_id = ?";
        $params[] = $status_filter;
    }
    
    if ($supplier_filter) {
        $sql .= " AND o.supplier_id = ?";
        $params[] = $supplier_filter;
    }
    
    $sql .= " GROUP BY o.id ORDER BY o.order_date DESC, o.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sipariş durumları
    $stmt = $db->query("SELECT * FROM order_statuses ORDER BY id");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tedarikçiler
    $stmt = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Malzemeler (yeni sipariş için)
    $stmt = $db->query("SELECT * FROM items ORDER BY name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h2"><i class="fas fa-shopping-cart"></i> Sipariş Yönetimi</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                    <i class="fas fa-plus"></i> Yeni Sipariş
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
                            <label class="form-label">Sipariş Durumu</label>
                            <select class="form-select" name="status">
                                <option value="">Tüm Durumlar</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status['id']; ?>" <?php echo $status_filter == $status['id'] ? 'selected' : ''; ?>>
                                        <?php echo $status['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tedarikçi</label>
                            <select class="form-select" name="supplier">
                                <option value="">Tüm Tedarikçiler</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_filter == $supplier['id'] ? 'selected' : ''; ?>>
                                        <?php echo $supplier['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="orders.php" class="btn btn-secondary">Sıfırla</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sipariş Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> Sipariş Listesi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                            <p>Henüz sipariş kaydı bulunmamaktadır.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newOrderModal">
                                <i class="fas fa-plus"></i> İlk Siparişi Oluşturun
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Sipariş No</th>
                                        <th>Tedarikçi</th>
                                        <th>Sipariş Tarihi</th>
                                        <th>Beklenen Tarih</th>
                                        <th>Durum</th>
                                        <th>Toplam Tutar</th>
                                        <th>Kalem Sayısı</th>
                                        <th>Oluşturan</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($order['supplier_name']); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <?php if ($order['expected_date']): ?>
                                                <?php echo date('d.m.Y', strtotime($order['expected_date'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Belirtilmemiş</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo $order['status_color']; ?>">
                                                <?php echo $order['status_name']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($order['total_amount'], 2); ?> TL</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $order['item_count']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['created_by_name']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasPermission('all') || $_SESSION['user_id'] == $order['created_by']): ?>
                                                <a href="order_edit.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
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
                            <h4><?php echo count($orders); ?></h4>
                            <p>Toplam Sipariş</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($orders, function($o) { return $o['status_name'] == 'Beklemede'; })); ?></h4>
                            <p>Bekleyen</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($orders, function($o) { return $o['status_name'] == 'Teslim Edildi'; })); ?></h4>
                            <p>Teslim Edilen</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <h4><?php echo number_format(array_sum(array_column($orders, 'total_amount')), 2); ?> TL</h4>
                            <p>Toplam Tutar</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Yeni Sipariş Modal -->
<div class="modal fade" id="newOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="orders_action.php" method="POST" id="newOrderForm">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Sipariş Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_order">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sipariş Numarası *</label>
                                <input type="text" class="form-control" name="order_number" 
                                       value="SIP-<?php echo date('Ymd-His'); ?>" required readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sipariş Tarihi *</label>
                                <input type="date" class="form-control" name="order_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
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
                                        <option value="<?php echo $supplier['id']; ?>">
                                            <?php echo htmlspecialchars($supplier['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Beklenen Teslim Tarihi</label>
                                <input type="date" class="form-control" name="expected_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Sipariş notları..."></textarea>
                    </div>
                    
                    <hr>
                    
                    <h6>Sipariş Kalemleri</h6>
                    <div id="orderItems">
                        <div class="order-item row mb-2">
                            <div class="col-md-5">
                                <select class="form-select" name="items[0][item_id]" required>
                                    <option value="">Malzeme seçin</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['code'] . ' - ' . $item['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control" name="items[0][quantity]" 
                                       placeholder="Miktar" step="0.01" min="0.01" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control" name="items[0][unit_price]" 
                                       placeholder="Birim Fiyat" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control" placeholder="Toplam" readonly>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-danger btn-sm remove-item" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="addMoreItems">
                        <i class="fas fa-plus"></i> Kalem Ekle
                    </button>
                    
                    <div class="mt-3">
                        <strong>Toplam Tutar: </strong>
                        <span id="totalAmount">0.00 TL</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Siparişi Oluştur</button>
                </div>
            </form>
        </div>
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
});
</script>

<?php include '../includes/footer.php'; ?>