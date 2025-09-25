<?php
// pages/returns.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü - Depo, Üretim ve Yönetici
if (!hasPermission('iade') && !hasPermission('uretim_iade') && !hasPermission('all')) {
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
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

try {
    $db = getDBConnection();
    
    // İadeleri getir
    $sql = "
        SELECT r.*, rr.name as reason_name, 
               u1.full_name as created_by_name, u2.full_name as approved_by_name,
               COUNT(ri.id) as item_count
        FROM returns r
        LEFT JOIN return_reasons rr ON r.reason_id = rr.id
        LEFT JOIN users u1 ON r.created_by = u1.id
        LEFT JOIN users u2 ON r.approved_by = u2.id
        LEFT JOIN return_items ri ON r.id = ri.return_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Yetki filtresi - kullanıcı sadece kendi iadelerini veya onayladıklarını görsün
    if (!hasPermission('all')) {
        if (hasPermission('iade')) {
            $sql .= " AND (r.return_type = 'supplier' OR r.created_by = ?)";
            $params[] = $_SESSION['user_id'];
        } elseif (hasPermission('uretim_iade')) {
            $sql .= " AND (r.return_type = 'production' OR r.created_by = ?)";
            $params[] = $_SESSION['user_id'];
        }
    }
    
    if ($type_filter) {
        $sql .= " AND r.return_type = ?";
        $params[] = $type_filter;
    }
    
    if ($status_filter) {
        $sql .= " AND r.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " GROUP BY r.id ORDER BY r.return_date DESC, r.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İade sebeplerini getir
    $stmt = $db->query("SELECT * FROM return_reasons WHERE is_active = 1 ORDER BY type, name");
    $reasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Malzemeleri getir
    $stmt = $db->query("SELECT * FROM items ORDER BY name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son siparişleri getir (tedarikçi iadesi için)
    $stmt = $db->query("
        SELECT o.id, o.order_number, s.name as supplier_name 
        FROM orders o 
        LEFT JOIN suppliers s ON o.supplier_id = s.id 
        WHERE o.status_id IN (3,4) 
        ORDER BY o.order_date DESC 
        LIMIT 10
    ");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son stok hareketlerini getir (üretim iadesi için)
    $stmt = $db->query("
        SELECT sm.id, sm.reference_no, sm.movement_date, mt.name as movement_type
        FROM stock_movements sm
        LEFT JOIN movement_types mt ON sm.movement_type_id = mt.id
        WHERE mt.multiplier < 0  -- Çıkış hareketleri
        ORDER BY sm.movement_date DESC 
        LIMIT 10
    ");
    $recent_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <h1 class="h2"><i class="fas fa-undo-alt"></i> İade Yönetimi</h1>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReturnModal">
                        <i class="fas fa-plus"></i> Yeni İade
                    </button>
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

            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtreler</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">İade Türü</label>
                            <select class="form-select" name="type">
                                <option value="">Tüm Türler</option>
                                <option value="supplier" <?php echo $type_filter == 'supplier' ? 'selected' : ''; ?>>Tedarikçi İadesi</option>
                                <option value="production" <?php echo $type_filter == 'production' ? 'selected' : ''; ?>>Üretim İadesi</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status">
                                <option value="">Tüm Durumlar</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Onaylandı</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Reddedildi</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="returns.php" class="btn btn-secondary">Sıfırla</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- İade Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> İade Listesi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($returns)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-undo-alt fa-3x mb-3"></i>
                            <p>Henüz iade kaydı bulunmamaktadır.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReturnModal">
                                <i class="fas fa-plus"></i> İlk İadeyi Oluşturun
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>İade No</th>
                                        <th>Tür</th>
                                        <th>İade Tarihi</th>
                                        <th>Sebep</th>
                                        <th>Durum</th>
                                        <th>Kalem Sayısı</th>
                                        <th>Toplam Miktar</th>
                                        <th>Oluşturan</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($returns as $return): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($return['return_number']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $return['return_type'] == 'supplier' ? 'primary' : 'info'; ?>">
                                                <?php echo $return['return_type'] == 'supplier' ? 'Tedarikçi' : 'Üretim'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($return['return_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($return['reason_name']); ?></td>
                                        <td>
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
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo $return['item_count']; ?></span></td>
                                        <td><strong><?php echo $return['total_quantity']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($return['created_by_name']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="return_details.php?id=<?php echo $return['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasPermission('all') || $return['created_by'] == $_SESSION['user_id']): ?>
                                                <a href="return_edit.php?id=<?php echo $return['id']; ?>" class="btn btn-outline-warning">
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
                            <h4><?php echo count($returns); ?></h4>
                            <p>Toplam İade</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($returns, function($r) { return $r['status'] == 'pending'; })); ?></h4>
                            <p>Bekleyen</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($returns, function($r) { return $r['return_type'] == 'supplier'; })); ?></h4>
                            <p>Tedarikçi İadesi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($returns, function($r) { return $r['return_type'] == 'production'; })); ?></h4>
                            <p>Üretim İadesi</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Yeni İade Modal -->
<div class="modal fade" id="newReturnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="returns_action.php" method="POST" id="newReturnForm">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni İade Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_return">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">İade Numarası *</label>
                                <input type="text" class="form-control" name="return_number" 
                                       value="IADE-<?php echo date('Ymd-His'); ?>" required readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">İade Tarihi *</label>
                                <input type="date" class="form-control" name="return_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">İade Türü *</label>
                                <select class="form-select" name="return_type" id="returnType" required>
                                    <option value="">Seçiniz</option>
                                    <option value="supplier">Tedarikçi İadesi</option>
                                    <option value="production">Üretim İadesi</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">İade Sebebi *</label>
                                <select class="form-select" name="reason_id" id="reasonSelect" required>
                                    <option value="">Önce iade türü seçin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tedarikçi İadesi Alanları -->
                    <div id="supplierFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">İlgili Sipariş</label>
                            <select class="form-select" name="related_order_id">
                                <option value="">Seçiniz (opsiyonel)</option>
                                <?php foreach ($recent_orders as $order): ?>
                                    <option value="<?php echo $order['id']; ?>">
                                        <?php echo htmlspecialchars($order['order_number'] . ' - ' . $order['supplier_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Üretim İadesi Alanları -->
                    <div id="productionFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">İlgili Stok Hareketi</label>
                            <select class="form-select" name="related_movement_id">
                                <option value="">Seçiniz (opsiyonel)</option>
                                <?php foreach ($recent_movements as $movement): ?>
                                    <option value="<?php echo $movement['id']; ?>">
                                        <?php echo htmlspecialchars($movement['reference_no'] . ' - ' . $movement['movement_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="İade açıklaması..."></textarea>
                    </div>
                    
                    <hr>
                    
                    <h6>İade Kalemleri</h6>
                    <div id="returnItems">
                        <div class="return-item row mb-2">
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
                    <button type="submit" class="btn btn-primary">İade Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// İade türüne göre sebepleri yükle
document.getElementById('returnType').addEventListener('change', function() {
    const type = this.value;
    const reasonSelect = document.getElementById('reasonSelect');
    const supplierFields = document.getElementById('supplierFields');
    const productionFields = document.getElementById('productionFields');
    
    // Alanları göster/gizle
    supplierFields.style.display = type === 'supplier' ? 'block' : 'none';
    productionFields.style.display = type === 'production' ? 'block' : 'none';
    
    // Sebepleri yükle
    if (type) {
        fetch('get_reasons.php?type=' + type)
            .then(response => response.json())
            .then(reasons => {
                reasonSelect.innerHTML = '<option value="">Seçiniz</option>';
                reasons.forEach(reason => {
                    reasonSelect.innerHTML += `<option value="${reason.id}">${reason.name}</option>`;
                });
            });
    } else {
        reasonSelect.innerHTML = '<option value="">Önce iade türü seçin</option>';
    }
});

// Dinamik iade kalemi ekleme
document.getElementById('addMoreItems').addEventListener('click', function() {
    const itemsContainer = document.getElementById('returnItems');
    const itemCount = document.querySelectorAll('.return-item').length;
    
    const newItem = document.createElement('div');
    newItem.className = 'return-item row mb-2';
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
    document.querySelectorAll('.return-item').forEach(item => {
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
        if (document.querySelectorAll('.return-item').length > 1) {
            container.remove();
            updateRemoveButtons();
            calculateTotal();
        }
    });
}

// Silme butonlarını güncelle
function updateRemoveButtons() {
    const items = document.querySelectorAll('.return-item');
    items.forEach((item, index) => {
        const removeBtn = item.querySelector('.remove-item');
        removeBtn.disabled = items.length <= 1;
    });
}

// İlk yüklemede event listener'ları ekle
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.return-item').forEach(attachItemEventListeners);
    updateRemoveButtons();
});
</script>

<?php include '../includes/footer.php'; ?>