<?php
// pages/movements.php
require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

// Yetki kontrolü
if (!hasPermission('movements') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

// Hareket tiplerini getir
try {
    $db = getDBConnection();
    
    // Hareket tipleri
    $stmt = $db->query("SELECT * FROM movement_types");
    $movement_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Malzemeler
    $stmt = $db->query("SELECT * FROM items ORDER BY name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stok hareketlerini getir
    $stmt = $db->query("
        SELECT sm.*, mt.name as movement_type_name, mt.multiplier, u.full_name 
        FROM stock_movements sm 
        JOIN movement_types mt ON sm.movement_type_id = mt.id 
        JOIN users u ON sm.created_by = u.id 
        ORDER BY sm.movement_date DESC, sm.id DESC
    ");
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hareket detaylarını getir
    $movement_details = [];
    foreach ($movements as $movement) {
        $stmt = $db->prepare("
            SELECT sml.*, i.code, i.name 
            FROM stock_movement_lines sml 
            JOIN items i ON sml.item_id = i.id 
            WHERE sml.stock_movement_id = ?
        ");
        $stmt->execute([$movement['id']]);
        $movement_details[$movement['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_movement') {
        try {
            $db->beginTransaction();
            
            // Ana hareket kaydı
            $stmt = $db->prepare("
                INSERT INTO stock_movements (movement_type_id, movement_date, reference_no, description, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['movement_type_id'],
                $_POST['movement_date'],
                $_POST['reference_no'],
                $_POST['description'],
                $_SESSION['user_id']
            ]);
            
            $movement_id = $db->lastInsertId();
            
            // Hareket detayları
            $item_ids = $_POST['item_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            
            foreach ($item_ids as $index => $item_id) {
                if (!empty($item_id) && !empty($quantities[$index])) {
                    $stmt = $db->prepare("
                        INSERT INTO stock_movement_lines (stock_movement_id, item_id, quantity) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$movement_id, $item_id, $quantities[$index]]);
                }
            }
            
            $db->commit();
            $_SESSION['success'] = 'Stok hareketi başarıyla kaydedildi!';
            header('Location: movements.php');
            exit();
            
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "Hata: " . $e->getMessage();
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
                <h1 class="h2"><i class="fas fa-exchange-alt"></i> Stok Hareketleri</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMovementModal">
                    <i class="fas fa-plus"></i> Yeni Hareket
                </button>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Stok Hareketleri Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Stok Hareket Geçmişi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($movements)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Henüz stok hareketi bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Hareket Tipi</th>
                                        <th>Referans No</th>
                                        <th>Malzemeler</th>
                                        <th>Toplam Miktar</th>
                                        <th>Kullanıcı</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movements as $movement): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y', strtotime($movement['movement_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $movement['multiplier'] > 0 ? 'success' : ($movement['multiplier'] < 0 ? 'danger' : 'warning'); ?>">
                                                <?php echo $movement['movement_type_name']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $movement['reference_no'] ?: '-'; ?></td>
                                        <td>
                                            <small>
                                                <?php 
                                                $detail_count = count($movement_details[$movement['id']] ?? []);
                                                echo $detail_count . ' malzeme';
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            $total_quantity = 0;
                                            if (isset($movement_details[$movement['id']])) {
                                                foreach ($movement_details[$movement['id']] as $detail) {
                                                    $total_quantity += $detail['quantity'];
                                                }
                                            }
                                            echo $total_quantity;
                                            ?>
                                        </td>
                                        <td><?php echo $movement['full_name']; ?></td>
                                        <td>
    <button class="btn btn-sm btn-outline-info" 
            onclick="viewMovement(<?php echo $movement['id']; ?>)">
        <i class="fas fa-eye"></i>
    </button>
    <a href="movement_edit.php?id=<?php echo $movement['id']; ?>" 
       class="btn btn-sm btn-outline-warning">
        <i class="fas fa-edit"></i>
    </a>
    <?php if (hasPermission('all')): ?>
    <button class="btn btn-sm btn-outline-danger" 
            onclick="deleteMovement(<?php echo $movement['id']; ?>)">
        <i class="fas fa-trash"></i>
    </button>
    <?php endif; ?>
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

<!-- Yeni Hareket Modal -->
<div class="modal fade" id="addMovementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Stok Hareketi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_movement">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Hareket Tipi *</label>
                                <select class="form-select" name="movement_type_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($movement_types as $type): ?>
                                        <option value="<?php echo $type['id']; ?>">
                                            <?php echo $type['name']; ?> (<?php echo $type['multiplier'] > 0 ? '+' : ''; echo $type['multiplier']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tarih *</label>
                                <input type="date" class="form-control" name="movement_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Referans No</label>
                                <input type="text" class="form-control" name="reference_no" 
                                       placeholder="Sipariş no, iş emri no, vb.">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" rows="2" 
                                  placeholder="Hareket açıklaması..."></textarea>
                    </div>
                    
                    <hr>
                    
                    <h6>Malzemeler</h6>
                    <div id="movementItems">
                        <div class="row movement-item">
                            <div class="col-md-6">
                                <select class="form-select" name="item_id[]">
                                    <option value="">Malzeme seçin</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo $item['code'] . ' - ' . $item['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" class="form-control" name="quantity[]" 
                                       placeholder="Miktar" step="0.01" min="0.01">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger btn-sm remove-item" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="addMoreItems">
                        <i class="fas fa-plus"></i> Malzeme Ekle
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hareket Detay Modal -->
<div class="modal fade" id="viewMovementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hareket Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="movementDetails">
                <!-- Detaylar buraya gelecek -->
            </div>
        </div>
    </div>
</div>

<script>
// Dinamik malzeme satırı ekleme
document.getElementById('addMoreItems').addEventListener('click', function() {
    const itemsContainer = document.getElementById('movementItems');
    const newRow = document.createElement('div');
    newRow.className = 'row movement-item mt-2';
    newRow.innerHTML = `
        <div class="col-md-6">
            <select class="form-select" name="item_id[]">
                <option value="">Malzeme seçin</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>">
                        <?php echo $item['code'] . ' - ' . $item['name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <input type="number" class="form-control" name="quantity[]" 
                   placeholder="Miktar" step="0.01" min="0.01">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger btn-sm remove-item">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    itemsContainer.appendChild(newRow);
    
    // Silme butonlarını etkinleştir
    enableRemoveButtons();
});

// Silme butonlarını etkinleştir
function enableRemoveButtons() {
    document.querySelectorAll('.remove-item').forEach(button => {
        button.addEventListener('click', function() {
            if (document.querySelectorAll('.movement-item').length > 1) {
                this.closest('.movement-item').remove();
            }
        });
        button.disabled = document.querySelectorAll('.movement-item').length <= 1;
    });
}

// İlk yüklemede silme butonlarını etkinleştir
enableRemoveButtons();

// Hareket detaylarını görüntüle
function viewMovement(movementId) {
    fetch('movement_details.php?id=' + movementId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('movementDetails').innerHTML = data;
            new bootstrap.Modal(document.getElementById('viewMovementModal')).show();
        });
}

// Hareket silme
function deleteMovement(movementId) {
    if (confirm('Bu hareketi silmek istediğinizden emin misiniz?')) {
        window.location.href = '../includes/movements_action.php?action=delete&id=' + movementId;
    }
}


</script>

<?php include '../includes/footer.php'; ?>