<?php
// pages/count_details.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

if (!hasPermission('counts') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

if (!isset($_GET['id'])) {
    header('Location: counts.php');
    exit();
}

$count_id = intval($_GET['id']);

try {
    $db = getDBConnection();
    
    // Sayım bilgilerini getir
    $stmt = $db->prepare("
        SELECT ic.*, u.full_name 
        FROM inventory_counts ic
        JOIN users u ON ic.created_by = u.id
        WHERE ic.id = ?
    ");
    $stmt->execute([$count_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$count) {
        die('Sayım bulunamadı!');
    }
    
    // Sayım detaylarını getir
    $stmt = $db->prepare("
        SELECT icl.*, i.code, i.name, i.description, u.name as uom_name
        FROM inventory_count_lines icl
        JOIN items i ON icl.item_id = i.id
        JOIN uoms u ON i.uom_id = u.id
        WHERE icl.inventory_count_id = ?
        ORDER BY ABS(icl.difference) DESC, i.name
    ");
    $stmt->execute([$count_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İstatistikler
    $total_items = count($details);
    $zero_diff = count(array_filter($details, function($d) { return $d['difference'] == 0; }));
    $positive_diff = count(array_filter($details, function($d) { return $d['difference'] > 0; }));
    $negative_diff = count(array_filter($details, function($d) { return $d['difference'] < 0; }));
    $total_diff = array_sum(array_column($details, 'difference'));
    
} catch(PDOException $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-clipboard-check"></i> Sayım Detayları</h1>
                <a href="counts.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Sayım Listesi
                </a>
            </div>

            <!-- Sayım Bilgileri -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Sayım Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><th>Tarih:</th><td><?php echo date('d.m.Y', strtotime($count['count_date'])); ?></td></tr>
                                <tr><th>Açıklama:</th><td><?php echo htmlspecialchars($count['description'] ?: '-'); ?></td></tr>
                                <tr><th>Oluşturan:</th><td><?php echo htmlspecialchars($count['full_name']); ?></td></tr>
                                <tr><th>Oluşturulma:</th><td><?php echo date('d.m.Y H:i', strtotime($count['created_at'])); ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Sayım İstatistikleri</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="text-primary">
                                        <h3><?php echo $total_items; ?></h3>
                                        <small>Toplam Malzeme</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-success">
                                        <h3><?php echo $zero_diff; ?></h3>
                                        <small>Doğru Sayım</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning">
                                        <h3><?php echo $positive_diff + $negative_diff; ?></h3>
                                        <small>Farklı Sayım</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sayım Detayları -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Sayım Sonuçları</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Malzeme</th>
                                    <th>Birim</th>
                                    <th>Sistem Stoku</th>
                                    <th>Sayılan Miktar</th>
                                    <th>Fark</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($details as $detail): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($detail['code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($detail['name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($detail['uom_name']); ?></td>
                                    <td><?php echo $detail['system_quantity']; ?></td>
                                    <td><?php echo $detail['counted_quantity']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $detail['difference'] == 0 ? 'success' : ($detail['difference'] > 0 ? 'info' : 'warning'); ?>">
                                            <?php echo $detail['difference']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($detail['difference'] == 0): ?>
                                            <span class="badge bg-success">Doğru</span>
                                        <?php elseif ($detail['difference'] > 0): ?>
                                            <span class="badge bg-info">Fazla</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Eksik</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Fark Özeti -->
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?php echo $zero_diff; ?></h4>
                            <p>Doğru Sayım</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <h4><?php echo $positive_diff; ?></h4>
                            <p>Fazla Sayım</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?php echo $negative_diff; ?></h4>
                            <p>Eksik Sayım</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>