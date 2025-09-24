<?php
// pages/counts.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü
if (!hasPermission('counts') && !hasPermission('all')) {
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

try {
    $db = getDBConnection();
    
    // Sayım kayıtlarını getir
    $stmt = $db->query("
        SELECT ic.*, u.full_name, 
               COUNT(icl.id) as item_count,
               SUM(ABS(icl.difference)) as total_difference
        FROM inventory_counts ic
        LEFT JOIN users u ON ic.created_by = u.id
        LEFT JOIN inventory_count_lines icl ON ic.id = icl.inventory_count_id
        GROUP BY ic.id
        ORDER BY ic.count_date DESC, ic.created_at DESC
    ");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sayım için malzeme listesi
    $stmt = $db->query("
        SELECT i.*, u.name as uom_name,
               COALESCE(SUM(sml.quantity), 0) as system_stock
        FROM items i
        LEFT JOIN uoms u ON i.uom_id = u.id
        LEFT JOIN stock_movement_lines sml ON i.id = sml.item_id
        GROUP BY i.id
        ORDER BY i.name
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Yeni sayım oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_count') {
    try {
        $db->beginTransaction();
        
        // Sayım kaydı oluştur
        $stmt = $db->prepare("
            INSERT INTO inventory_counts (count_date, description, created_by) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $_POST['count_date'] ?? date('Y-m-d'),
            $_POST['description'] ?? '',
            $_SESSION['user_id']
        ]);
        
        $count_id = $db->lastInsertId();
        
        // Sayım detaylarını ekle
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item_id => $item_data) {
                if (!empty($item_data['counted_quantity'])) {
                    $item_id = intval($item_id);
                    $counted_quantity = floatval($item_data['counted_quantity']);
                    
                    // Sistem stok miktarını bul
                    $stmt = $db->prepare("
                        SELECT COALESCE(SUM(quantity), 0) as system_quantity 
                        FROM stock_movement_lines 
                        WHERE item_id = ?
                    ");
                    $stmt->execute([$item_id]);
                    $system_quantity = $stmt->fetch(PDO::FETCH_ASSOC)['system_quantity'];
                    
                    $difference = $counted_quantity - $system_quantity;
                    
                    // Sayım detayını ekle
                    $stmt = $db->prepare("
                        INSERT INTO inventory_count_lines 
                        (inventory_count_id, item_id, counted_quantity, system_quantity, difference) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $count_id,
                        $item_id,
                        $counted_quantity,
                        $system_quantity,
                        $difference
                    ]);
                    
                    // Eğer fark varsa, otomatik düzeltme hareketi oluştur (opsiyonel)
                    if ($difference != 0) {
                        $movement_type = $difference > 0 ? 1 : 2; // 1: Giriş, 2: Çıkış
                        
                        $stmt = $db->prepare("
                            INSERT INTO stock_movements 
                            (movement_type_id, movement_date, reference_no, description, created_by) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $movement_type,
                            $_POST['count_date'] ?? date('Y-m-d'),
                            'SAYIM-' . $count_id,
                            'Sayım fark düzeltme - ' . ($_POST['description'] ?? ''),
                            $_SESSION['user_id']
                        ]);
                        
                        $movement_id = $db->lastInsertId();
                        
                        // Hareket detayı
                        $stmt = $db->prepare("
                            INSERT INTO stock_movement_lines 
                            (stock_movement_id, item_id, quantity) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([
                            $movement_id,
                            $item_id,
                            abs($difference)
                        ]);
                    }
                }
            }
        }
        
        $db->commit();
        $_SESSION['success'] = 'Sayım başarıyla oluşturuldu!';
        header('Location: counts.php');
        exit();
        
    } catch(PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Sayım oluşturulurken hata: ' . $e->getMessage();
        header('Location: counts.php');
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
                <h1 class="h2"><i class="fas fa-clipboard-check"></i> Stok Sayımı</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCountModal">
                    <i class="fas fa-plus"></i> Yeni Sayım
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

            <!-- Sayım Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-history"></i> Sayım Geçmişi</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($counts)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                            <p>Henüz sayım kaydı bulunmamaktadır.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCountModal">
                                <i class="fas fa-plus"></i> İlk Sayımı Oluşturun
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Açıklama</th>
                                        <th>Malzeme Sayısı</th>
                                        <th>Toplam Fark</th>
                                        <th>Oluşturan</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($counts as $count): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y', strtotime($count['count_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($count['description'] ?: '-'); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $count['item_count']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($count['total_difference'] > 0): ?>
                                                <span class="badge bg-<?php echo $count['total_difference'] == 0 ? 'success' : 'warning'; ?>">
                                                    <?php echo $count['total_difference']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($count['full_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $count['total_difference'] == 0 ? 'success' : 'warning'; ?>">
                                                <?php echo $count['total_difference'] == 0 ? 'Tamamlandı' : 'Fark Var'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="count_details.php?id=<?php echo $count['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Detay
                                                </a>
                                                <?php if (hasPermission('all')): ?>
                                                <button class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $count['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
        </main>
    </div>
</div>

<!-- Yeni Sayım Modal -->
<div class="modal fade" id="newCountModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="countForm">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Stok Sayımı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_count">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Sayım Tarihi *</label>
                            <input type="date" class="form-control" name="count_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Açıklama</label>
                            <input type="text" class="form-control" name="description" placeholder="Sayım açıklaması...">
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Sayım Yapılacak Malzemeler</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Malzeme Kodu</th>
                                    <th>Malzeme Adı</th>
                                    <th>Birim</th>
                                    <th>Sistem Stoku</th>
                                    <th>Sayılan Miktar</th>
                                    <th>Fark</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['uom_name']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $item['system_stock']; ?></span>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" 
                                               name="items[<?php echo $item['id']; ?>][counted_quantity]" 
                                               value="<?php echo $item['system_stock']; ?>" 
                                               step="0.01" min="0" placeholder="0">
                                    </td>
                                    <td>
                                        <span class="difference badge bg-success">0</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Sayımı Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fark hesaplama
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[name*="counted_quantity"]');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const systemStock = parseFloat(row.querySelector('.badge').textContent) || 0;
            const counted = parseFloat(this.value) || 0;
            const difference = counted - systemStock;
            
            const diffSpan = row.querySelector('.difference');
            diffSpan.textContent = difference;
            diffSpan.className = 'difference badge ' + 
                (difference === 0 ? 'bg-success' : 
                 difference > 0 ? 'bg-info' : 'bg-warning');
        });
    });
});

// Silme onayı
function confirmDelete(countId) {
    if (confirm('Bu sayım kaydını silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!')) {
        window.location.href = 'counts_action.php?action=delete&id=' + countId;
    }
}
</script>

<?php include '../includes/footer.php'; ?>