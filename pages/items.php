<?php
// pages/items.php
require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

// Yetki kontrolü
if (!hasPermission('items_view') && !hasPermission('all')) {
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

// Malzeme listesini getir
try {
    $db = getDBConnection();
    $stmt = $db->query("
        SELECT i.*, u.name as uom_name 
        FROM items i 
        LEFT JOIN uoms u ON i.uom_id = u.id 
        ORDER BY i.name
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Birimleri getir (form için)
try {
    $stmt = $db->query("SELECT * FROM uoms ORDER BY name");
    $uoms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Birimler yüklenirken hata: " . $e->getMessage();
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-boxes"></i> Malzeme Kartları</h1>
                <?php if (hasPermission('items_manage') || hasPermission('all')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fas fa-plus"></i> Yeni Malzeme
                </button>
                <?php endif; ?>
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

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Kod</th>
                            <th>Malzeme Adı</th>
                            <th>Açıklama</th>
                            <th>Birim</th>
                            <th>Min. Stok</th>
                            <th>Oluşturulma</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Henüz malzeme kaydı bulunmamaktadır.</p>
                                    <?php if (hasPermission('items_manage') || hasPermission('all')): ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                        <i class="fas fa-plus"></i> İlk Malzemeyi Ekleyin
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['description'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($item['uom_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $item['min_stock_level'] > 0 ? 'warning' : 'secondary'; ?>">
                                        <?php echo $item['min_stock_level']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                      <button class="btn btn-outline-primary" 
    onclick="editItem(
        <?php echo (int)$item['id']; ?>,
        <?php echo json_encode($item['code'] ?? ''); ?>,
        <?php echo json_encode($item['name'] ?? ''); ?>,
        <?php echo json_encode($item['description'] ?? ''); ?>,
        <?php echo (int)$item['uom_id']; ?>,
        <?php echo (int)$item['min_stock_level']; ?>
    )">
    <i class="fas fa-edit"></i>
</button>


                                        <?php if(hasPermission('items_manage') || hasPermission('all')): ?>
                                        <button class="btn btn-outline-danger" 
                                            onclick="confirmDelete(
                                                <?php echo $item['id']; ?>,
                                                <?php echo json_encode($item['code'] . ' - ' . $item['name']); ?>
                                            )">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Yeni Malzeme Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addItemForm" action="../includes/items_action.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Malzeme Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Malzeme Kodu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" required maxlength="50" placeholder="MOTOR-001">
                        <div class="form-text">Benzersiz bir kod giriniz</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Malzeme Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required maxlength="255" placeholder="250W Elektrik Motoru">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" rows="2" maxlength="500" placeholder="Malzeme açıklaması..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Birim <span class="text-danger">*</span></label>
                                <select class="form-select" name="uom_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($uoms as $uom): ?>
                                        <option value="<?php echo $uom['id']; ?>"><?php echo htmlspecialchars($uom['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Min. Stok Seviyesi</label>
                                <input type="number" class="form-control" name="min_stock_level" value="0" min="0" max="9999">
                                <div class="form-text">Kritik stok uyarısı için</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Düzenleme Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Malzeme Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editItemForm" action="../includes/items_action.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Malzeme Kodu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" id="edit_code" required maxlength="50">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Malzeme Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_name" required maxlength="255">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2" maxlength="500"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Birim <span class="text-danger">*</span></label>
                                <select class="form-select" name="uom_id" id="edit_uom_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($uoms as $uom): ?>
                                        <option value="<?php echo $uom['id']; ?>"><?php echo htmlspecialchars($uom['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Min. Stok Seviyesi</label>
                                <input type="number" class="form-control" name="min_stock_level" id="edit_min_stock" min="0" max="9999">
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="submit" form="editItemForm" class="btn btn-primary">Değişiklikleri Kaydet</button>
            </div>
                </form>
            </div>
            
        </div>
    </div>
</div>

<script>
// Malzeme düzenleme modalını aç
function editItem(id, code, name, description, uomId, minStock) {
    console.log("Düzenleme verileri:", {id, code, name, uomId, minStock});
    
    document.getElementById('edit_item_id').value = id;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_uom_id').value = uomId;
    document.getElementById('edit_min_stock').value = minStock;
    
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}

// Silme onayı
function confirmDelete(id, itemName) {
    if (confirm('"' + itemName + '" malzemesini silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!')) {
        window.location.href = '/includes/items_action.php?action=delete&id=' + id;
    }
}

// Form validasyonu
document.addEventListener('DOMContentLoaded', function() {
    const addForm = document.getElementById('addItemForm');
    const editForm = document.getElementById('editItemForm');
    
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const code = this.elements['code'].value.trim();
            const name = this.elements['name'].value.trim();
            const uomId = this.elements['uom_id'].value;
            
            if (!code || !name || !uomId) {
                e.preventDefault();
                alert('Lütfen zorunlu alanları doldurun!');
                return false;
            }
        });
    }
    
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const code = this.elements['code'].value.trim();
            const name = this.elements['name'].value.trim();
            const uomId = this.elements['uom_id'].value;
            
            if (!code || !name || !uomId) {
                e.preventDefault();
                alert('Lütfen zorunlu alanları doldurun!');
                return false;
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
