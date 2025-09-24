<?php
// pages/movement_details.php
require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

if (!isset($_GET['id'])) {
    die('Geçersiz istek!');
}

try {
    $db = getDBConnection();
    $movement_id = $_GET['id'];
    
    // Hareket bilgileri
    $stmt = $db->prepare("
        SELECT sm.*, mt.name as movement_type_name, u.full_name 
        FROM stock_movements sm 
        JOIN movement_types mt ON sm.movement_type_id = mt.id 
        JOIN users u ON sm.created_by = u.id 
        WHERE sm.id = ?
    ");
    $stmt->execute([$movement_id]);
    $movement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$movement) {
        die('Hareket bulunamadı!');
    }
    
    // Hareket detayları
    $stmt = $db->prepare("
        SELECT sml.*, i.code, i.name, i.description 
        FROM stock_movement_lines sml 
        JOIN items i ON sml.item_id = i.id 
        WHERE sml.stock_movement_id = ?
    ");
    $stmt->execute([$movement_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die('Hata: ' . $e->getMessage());
}
?>

<div class="row">
    <div class="col-md-6">
        <h6>Hareket Bilgileri</h6>
        <table class="table table-sm">
            <tr><th>Tip:</th><td><?php echo $movement['movement_type_name']; ?></td></tr>
            <tr><th>Tarih:</th><td><?php echo date('d.m.Y', strtotime($movement['movement_date'])); ?></td></tr>
            <tr><th>Referans:</th><td><?php echo $movement['reference_no'] ?: '-'; ?></td></tr>
            <tr><th>Açıklama:</th><td><?php echo $movement['description'] ?: '-'; ?></td></tr>
            <tr><th>Kullanıcı:</th><td><?php echo $movement['full_name']; ?></td></tr>
        </table>
    </div>
</div>

<h6>Malzeme Listesi</h6>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Malzeme Kodu</th>
                <th>Malzeme Adı</th>
                <th>Miktar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $detail): ?>
            <tr>
                <td><?php echo $detail['code']; ?></td>
                <td><?php echo $detail['name']; ?></td>
                <td><?php echo $detail['quantity']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>