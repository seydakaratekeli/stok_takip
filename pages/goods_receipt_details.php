<?php
// pages/goods_receipt_details.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

if (!hasPermission('mal_kabul') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['id'])) {
    header('Location: goods_receipt.php');
    exit();
}

$receipt_id = intval($_GET['id']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

try {
    $db = getDBConnection();
    
    // Mal kabul bilgilerini getir
    $stmt = $db->prepare("
        SELECT gr.*, o.order_number, o.order_date, o.expected_date, o.total_amount,
               s.name as supplier_name, s.contact_person, s.phone, s.email,
               u.full_name as created_by_name, inspector.full_name as inspector_name
        FROM goods_receipts gr
        LEFT JOIN orders o ON gr.order_id = o.id
        LEFT JOIN suppliers s ON o.supplier_id = s.id
        LEFT JOIN users u ON gr.created_by = u.id
        LEFT JOIN users inspector ON gr.inspected_by = inspector.id
        WHERE gr.id = ?
    ");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receipt) {
        die('Mal kabul kaydı bulunamadı!');
    }
    
    // Mal kabul kalemlerini getir
    $stmt = $db->prepare("
        SELECT gri.*, oi.quantity as ordered_quantity, oi.unit_price, oi.total_price,
               i.code, i.name, i.description, u.name as uom_name
        FROM goods_receipt_items gri
        LEFT JOIN order_items oi ON gri.order_item_id = oi.id
        LEFT JOIN items i ON oi.item_id = i.id
        LEFT JOIN uoms u ON i.uom_id = u.id
        WHERE gri.goods_receipt_id = ?
        ORDER BY gri.id
    ");
    $stmt->execute([$receipt_id]);
    $receipt_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İstatistikler
    $total_ordered = array_sum(array_column($receipt_items, 'ordered_quantity'));
    $total_received = array_sum(array_column($receipt_items, 'received_quantity'));
    $total_accepted = array_sum(array_column($receipt_items, 'accepted_quantity'));
    $total_rejected = array_sum(array_column($receipt_items, 'rejected_quantity'));
    
} catch(PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}

// Form gönderimi - Kalite kontrol
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_inspection') {
        try {
            $db->beginTransaction();
            
            // Kalemleri güncelle
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item_id => $item_data) {
                    $accepted_qty = floatval($item_data['accepted_quantity'] ?? 0);
                    $rejected_qty = floatval($item_data['rejected_quantity'] ?? 0);
                    $rejection_reason = trim($item_data['rejection_reason'] ?? '');
                    
                    $stmt = $db->prepare("
                        UPDATE goods_receipt_items 
                        SET accepted_quantity = ?, rejected_quantity = ?, rejection_reason = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $accepted_qty,
                        $rejected_qty,
                        $rejection_reason,
                        $item_data['notes'] ?? '',
                        $item_id
                    ]);
                }
            }
            
            // Mal kabul durumunu güncelle
            $new_status = $_POST['final_status'] ?? 'inspecting';
            $inspector_notes = trim($_POST['inspector_notes'] ?? '');
            
            $stmt = $db->prepare("
                UPDATE goods_receipts 
                SET status = ?, inspector_notes = ?, inspected_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $new_status,
                $inspector_notes,
                $_SESSION['user_id'],
                $receipt_id
            ]);
            
            // Eğer onaylandıysa, stok girişi yap
            if ($new_status === 'approved') {
                // Stok hareketi oluştur
                $stmt = $db->prepare("
                    INSERT INTO stock_movements 
                    (movement_type_id, movement_date, reference_no, description, created_by) 
                    VALUES (1, CURDATE(), ?, 'Mal kabul stok girişi', ?)
                ");
                $reference_no = 'MK-' . $receipt_id;
                $stmt->execute([$reference_no, $_SESSION['user_id']]);
                $movement_id = $db->lastInsertId();
                
                // Kabul edilen kalemleri stoka ekle
                foreach ($receipt_items as $item) {
                    $accepted_qty = floatval($_POST['items'][$item['id']]['accepted_quantity'] ?? 0);
                    
                    if ($accepted_qty > 0) {
                        $stmt = $db->prepare("
                            INSERT INTO stock_movement_lines (stock_movement_id, item_id, quantity) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$movement_id, $item['item_id'], $accepted_qty]);
                    }
                }
                
                // Sipariş durumunu güncelle
                $stmt = $db->prepare("UPDATE orders SET status_id = 6 WHERE id = ?"); // Tamamlandı
                $stmt->execute([$receipt['order_id']]);
            }
            
            $db->commit();
            $_SESSION['success'] = 'Kalite kontrol bilgileri güncellendi!';
            header('Location: goods_receipt_details.php?id=' . $receipt_id);
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Kalite kontrol güncelleme hatası: ' . $e->getMessage();
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
                <h1 class="h2"><i class="fas fa-clipboard-check"></i> Mal Kabul Detayları</h1>
                <div>
                    <a href="goods_receipt.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Geri Dön
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

            <!-- Mal Kabul Bilgileri -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Mal Kabul Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Mal Kabul No:</th><td><strong><?php echo $receipt['receipt_number']; ?></strong></td></tr>
                                <tr><th>Durum:</th>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'draft' => 'secondary',
                                            'inspecting' => 'warning', 
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'completed' => 'primary'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $status_badges[$receipt['status']]; ?>">
                                            <?php echo ucfirst($receipt['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr><th>Mal Kabul Tarihi:</th><td><?php echo date('d.m.Y', strtotime($receipt['receipt_date'])); ?></td></tr>
                                <tr><th>Oluşturan:</th><td><?php echo $receipt['created_by_name']; ?></td></tr>
                                <tr><th>Kontrolör:</th><td><?php echo $receipt['inspector_name'] ?: '-'; ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Sipariş No:</th><td><strong><?php echo $receipt['order_number']; ?></strong></td></tr>
                                <tr><th>Tedarikçi:</th><td><?php echo $receipt['supplier_name']; ?></td></tr>
                                <tr><th>Sipariş Tarihi:</th><td><?php echo date('d.m.Y', strtotime($receipt['order_date'])); ?></td></tr>
                                <tr><th>Toplam Tutar:</th><td><strong><?php echo number_format($receipt['total_amount'], 2); ?> TL</strong></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <h4><?php echo number_format($total_ordered, 2); ?></h4>
                            <p>Sipariş Miktarı</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <h4><?php echo number_format($total_received, 2); ?></h4>
                            <p>Teslim Alınan</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?php echo number_format($total_accepted, 2); ?></h4>
                            <p>Kabul Edilen</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?php echo number_format($total_rejected, 2); ?></h4>
                            <p>Reddedilen</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kalite Kontrol Formu -->
            <?php if (in_array($receipt['status'], ['draft', 'inspecting'])): ?>
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-search"></i> Kalite Kontrol</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="inspectionForm">
                        <input type="hidden" name="action" value="update_inspection">
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Malzeme</th>
                                        <th>Sipariş</th>
                                        <th>Teslim Alınan</th>
                                        <th>Kabul Edilen</th>
                                        <th>Reddedilen</th>
                                        <th>Red Sebebi</th>
                                        <th>Notlar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($receipt_items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $item['code']; ?></strong><br>
                                            <small><?php echo $item['name']; ?></small>
                                        </td>
                                        <td><?php echo $item['ordered_quantity']; ?></td>
                                        <td><?php echo $item['received_quantity']; ?></td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" 
                                                   name="items[<?php echo $item['id']; ?>][accepted_quantity]"
                                                   value="<?php echo $item['accepted_quantity'] ?: $item['received_quantity']; ?>"
                                                   min="0" max="<?php echo $item['received_quantity']; ?>" 
                                                   step="0.01" required
                                                   onchange="updateRejectedQuantity(this, <?php echo $item['id']; ?>, <?php echo $item['received_quantity']; ?>)">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" 
                                                   name="items[<?php echo $item['id']; ?>][rejected_quantity]"
                                                   value="<?php echo $item['rejected_quantity'] ?: 0; ?>"
                                                   min="0" max="<?php echo $item['received_quantity']; ?>" 
                                                   step="0.01" readonly
                                                   id="rejected-<?php echo $item['id']; ?>">
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" 
                                                    name="items[<?php echo $item['id']; ?>][rejection_reason]">
                                                <option value="">Sebep seçin</option>
                                                <option value="hasar" <?php echo $item['rejection_reason'] == 'hasar' ? 'selected' : ''; ?>>Hasar Görmüş</option>
                                                <option value="kalite" <?php echo $item['rejection_reason'] == 'kalite' ? 'selected' : ''; ?>>Kalite Düşük</option>
                                                <option value="yanlis" <?php echo $item['rejection_reason'] == 'yanlis' ? 'selected' : ''; ?>>Yanlış Ürün</option>
                                                <option value="eksik" <?php echo $item['rejection_reason'] == 'eksik' ? 'selected' : ''; ?>>Eksik Parça</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" 
                                                   name="items[<?php echo $item['id']; ?>][notes]"
                                                   value="<?php echo $item['notes'] ?? ''; ?>"
                                                   placeholder="Notlar...">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Kontrolör Notları</label>
                                    <textarea class="form-control" name="inspector_notes" rows="3" 
                                              placeholder="Kalite kontrol notları..."><?php echo $receipt['inspector_notes'] ?? ''; ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Sonuç</label>
                                    <select class="form-select" name="final_status" required>
                                        <option value="inspecting" <?php echo $receipt['status'] == 'inspecting' ? 'selected' : ''; ?>>Kontrol Ediliyor</option>
                                        <option value="approved" <?php echo $receipt['status'] == 'approved' ? 'selected' : ''; ?>>Onayla ve Stoka Aktar</option>
                                        <option value="rejected" <?php echo $receipt['status'] == 'rejected' ? 'selected' : ''; ?>>Reddet</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i> Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
                <!-- Tamamlanmış mal kabul için sadece görüntüleme -->
                <div class="card">
                    <div class="card-header bg-<?php echo $receipt['status'] == 'approved' ? 'success' : 'danger'; ?> text-white">
                        <h5 class="card-title mb-0">Kalite Kontrol Sonuçları</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Malzeme</th>
                                        <th>Sipariş</th>
                                        <th>Teslim Alınan</th>
                                        <th>Kabul Edilen</th>
                                        <th>Reddedilen</th>
                                        <th>Red Sebebi</th>
                                        <th>Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($receipt_items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $item['code']; ?></strong><br>
                                            <small><?php echo $item['name']; ?></small>
                                        </td>
                                        <td><?php echo $item['ordered_quantity']; ?></td>
                                        <td><?php echo $item['received_quantity']; ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $item['accepted_quantity']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($item['rejected_quantity'] > 0): ?>
                                                <span class="badge bg-danger"><?php echo $item['rejected_quantity']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $item['rejection_reason'] ?: '-'; ?></td>
                                        <td>
                                            <?php if ($item['accepted_quantity'] == $item['ordered_quantity']): ?>
                                                <span class="badge bg-success">Tam</span>
                                            <?php elseif ($item['accepted_quantity'] > 0): ?>
                                                <span class="badge bg-warning">Kısmi</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Red</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($receipt['inspector_notes']): ?>
                        <div class="mt-3">
                            <strong>Kontrolör Notları:</strong>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($receipt['inspector_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// Reddedilen miktarı otomatik güncelle
function updateRejectedQuantity(input, itemId, receivedQuantity) {
    const acceptedQuantity = parseFloat(input.value) || 0;
    const rejectedQuantity = receivedQuantity - acceptedQuantity;
    
    const rejectedInput = document.getElementById('rejected-' + itemId);
    rejectedInput.value = rejectedQuantity.toFixed(2);
}

// Form validasyonu
document.getElementById('inspectionForm').addEventListener('submit', function(e) {
    let totalAccepted = 0;
    const inputs = document.querySelectorAll('input[name*="accepted_quantity"]');
    
    inputs.forEach(input => {
        totalAccepted += parseFloat(input.value) || 0;
    });
    
    if (totalAccepted === 0) {
        e.preventDefault();
        alert('En az bir malzeme için kabul edilen miktar girmelisiniz!');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>