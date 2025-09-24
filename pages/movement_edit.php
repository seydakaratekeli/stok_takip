<?php
// pages/movement_edit.php
require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

// Yetki kontrolü (Depo Görevlisi, Üretim Operatörü ve Yönetici erişebilmeli)
if (!hasPermission('movements') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['id'])) {
    die('Geçersiz istek!');
}

$movement_id = (int) $_GET['id'];

try {
    $db = getDBConnection();

    // Hareket başlığı
    $stmt = $db->prepare("
        SELECT * FROM stock_movements WHERE id = ?
    ");
    $stmt->execute([$movement_id]);
    $movement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$movement) {
        die('Hareket bulunamadı!');
    }

    // Hareket detayları
    $stmt = $db->prepare("
        SELECT sml.*, i.code, i.name 
        FROM stock_movement_lines sml
        JOIN items i ON sml.item_id = i.id
        WHERE sml.stock_movement_id = ?
    ");
    $stmt->execute([$movement_id]);
    $movement_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hareket tipleri
    $stmt = $db->query("SELECT * FROM movement_types");
    $movement_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Malzeme listesi
    $stmt = $db->query("SELECT * FROM items ORDER BY name");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Hata: " . $e->getMessage());
}
?>

<?php include '../includes/header.php'; ?>

<div class="container mt-4">
    <h2><i class="fas fa-edit"></i> Stok Hareketi Düzenle</h2>
    <form action="../includes/movements_action.php" method="POST">
        <input type="hidden" name="action" value="update_movement">
        <input type="hidden" name="movement_id" value="<?php echo $movement['id']; ?>">

        <div class="row">
            <div class="col-md-4">
                <label class="form-label">Hareket Tipi</label>
                <select name="movement_type_id" class="form-select" required>
                    <?php foreach ($movement_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" 
                            <?php if ($movement['movement_type_id'] == $type['id']) echo 'selected'; ?>>
                            <?php echo $type['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tarih</label>
                <input type="date" name="movement_date" class="form-control"
                       value="<?php echo date('Y-m-d', strtotime($movement['movement_date'])); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Referans No</label>
                <input type="text" name="reference_no" class="form-control"
                       value="<?php echo htmlspecialchars($movement['reference_no']); ?>">
            </div>
        </div>

        <div class="mb-3 mt-3">
            <label class="form-label">Açıklama</label>
            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($movement['description']); ?></textarea>
        </div>

        <hr>
        <h5>Malzemeler</h5>
        <div id="movementItems">
            <?php foreach ($movement_details as $detail): ?>
            <div class="row mb-2 movement-item">
                <div class="col-md-6">
                    <select name="item_id[]" class="form-select">
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>"
                                <?php if ($detail['item_id'] == $item['id']) echo 'selected'; ?>>
                                <?php echo $item['code'] . " - " . $item['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="number" name="quantity[]" class="form-control" step="0.01" min="0.01"
                           value="<?php echo $detail['quantity']; ?>">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-item">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-secondary btn-sm mt-2" id="addMoreItems">
            <i class="fas fa-plus"></i> Malzeme Ekle
        </button>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">Kaydet</button>
            <a href="movements.php" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>

<script>
document.getElementById('addMoreItems').addEventListener('click', function() {
    const itemsContainer = document.getElementById('movementItems');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 movement-item';
    newRow.innerHTML = `
        <div class="col-md-6">
            <select name="item_id[]" class="form-select">
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>">
                        <?php echo $item['code'] . " - " . $item['name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <input type="number" name="quantity[]" class="form-control" step="0.01" min="0.01">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger btn-sm remove-item">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    itemsContainer.appendChild(newRow);
    enableRemoveButtons();
});

function enableRemoveButtons() {
    document.querySelectorAll('.remove-item').forEach(button => {
        button.addEventListener('click', function() {
            if (document.querySelectorAll('.movement-item').length > 1) {
                this.closest('.movement-item').remove();
            }
        });
    });
}
enableRemoveButtons();
</script>

<?php include '../includes/footer.php'; ?>
