<?php
// pages/reports.php
require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

// Yetki kontrolü
if (!hasPermission('view_reports') && !hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

// Filtre parametreleri
$filter = $_GET['filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Ayın ilk günü
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Bugün

try {
    $db = getDBConnection();
    
    // Anlık Stok Raporu
    $stmt = $db->prepare("
        SELECT 
            i.id,
            i.code,
            i.name,
            i.description,
            u.name as uom_name,
            i.min_stock_level,
            COALESCE(SUM(sml.quantity), 0) as current_stock,
            CASE 
                WHEN i.min_stock_level > 0 AND COALESCE(SUM(sml.quantity), 0) <= i.min_stock_level THEN 'CRITICAL'
                WHEN i.min_stock_level > 0 AND COALESCE(SUM(sml.quantity), 0) <= (i.min_stock_level * 1.5) THEN 'LOW'
                ELSE 'NORMAL'
            END as stock_status
        FROM items i
        LEFT JOIN uoms u ON i.uom_id = u.id
        LEFT JOIN stock_movement_lines sml ON i.id = sml.item_id
        LEFT JOIN stock_movements sm ON sml.stock_movement_id = sm.id
        GROUP BY i.id, i.code, i.name, i.description, u.name, i.min_stock_level
        ORDER BY i.name
    ");
    $stmt->execute();
    $stock_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hareket Dökümü Raporu
    $stmt = $db->prepare("
        SELECT 
            sm.movement_date,
            mt.name as movement_type,
            mt.multiplier,
            sm.reference_no,
            sm.description,
            u.full_name,
            i.code,
            i.name,
            sml.quantity,
            uom.name as uom_name
        FROM stock_movement_lines sml
        JOIN stock_movements sm ON sml.stock_movement_id = sm.id
        JOIN movement_types mt ON sm.movement_type_id = mt.id
        JOIN items i ON sml.item_id = i.id
        JOIN uoms uom ON i.uom_id = uom.id
        JOIN users u ON sm.created_by = u.id
        WHERE sm.movement_date BETWEEN ? AND ?
        ORDER BY sm.movement_date DESC, sm.id DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $movement_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kritik Stok Raporu (min stok seviyesi altındakiler)
    $critical_stock = array_filter($stock_report, function($item) {
        return $item['stock_status'] === 'CRITICAL';
    });
    
    // İstatistikler
    $total_items = count($stock_report);
    $critical_count = count($critical_stock);
    $low_count = count(array_filter($stock_report, function($item) {
        return $item['stock_status'] === 'LOW';
    }));
    $normal_count = count(array_filter($stock_report, function($item) {
        return $item['stock_status'] === 'NORMAL';
    }));
    
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
                <h1 class="h2"><i class="fas fa-chart-bar"></i> Raporlar</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-outline-primary me-2" onclick="window.print()">
                        <i class="fas fa-print"></i> Yazdır
                    </button>
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel'e Aktar
                    </button>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtreler</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Rapor Türü</label>
                            <select class="form-select" name="filter" onchange="this.form.submit()">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Tüm Raporlar</option>
                                <option value="stock" <?php echo $filter === 'stock' ? 'selected' : ''; ?>>Stok Durumu</option>
                                <option value="movements" <?php echo $filter === 'movements' ? 'selected' : ''; ?>>Hareket Dökümü</option>
                                <option value="critical" <?php echo $filter === 'critical' ? 'selected' : ''; ?>>Kritik Stok</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Toplam Malzeme</h6>
                                    <h3><?php echo $total_items; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-boxes fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Normal Stok</h6>
                                    <h3><?php echo $normal_count; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Düşük Stok</h6>
                                    <h3><?php echo $low_count; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6>Kritik Stok</h6>
                                    <h3><?php echo $critical_count; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rapor İçeriği -->
            <?php if ($filter === 'all' || $filter === 'stock'): ?>
            <!-- Stok Durumu Raporu -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-boxes"></i> Stok Durumu Raporu
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="stockTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Malzeme Kodu</th>
                                    <th>Malzeme Adı</th>
                                    <th>Birim</th>
                                    <th>Mevcut Stok</th>
                                    <th>Min. Stok</th>
                                    <th>Durum</th>
                                    <th>Fark</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock_report as $item): ?>
                                <tr class="<?php echo $item['stock_status'] === 'CRITICAL' ? 'table-danger' : ($item['stock_status'] === 'LOW' ? 'table-warning' : ''); ?>">
                                    <td><strong><?php echo $item['code']; ?></strong></td>
                                    <td><?php echo $item['name']; ?></td>
                                    <td><?php echo $item['uom_name']; ?></td>
                                    <td><?php echo $item['current_stock']; ?></td>
                                    <td><?php echo $item['min_stock_level']; ?></td>
                                    <td>
                                        <?php if ($item['stock_status'] === 'CRITICAL'): ?>
                                            <span class="badge bg-danger">KRİTİK</span>
                                        <?php elseif ($item['stock_status'] === 'LOW'): ?>
                                            <span class="badge bg-warning">DÜŞÜK</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">NORMAL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $difference = $item['current_stock'] - $item['min_stock_level'];
                                        if ($item['min_stock_level'] > 0) {
                                            echo $difference >= 0 ? 
                                                '<span class="text-success">+'.$difference.'</span>' : 
                                                '<span class="text-danger">'.$difference.'</span>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($filter === 'all' || $filter === 'movements'): ?>
            <!-- Hareket Dökümü Raporu -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exchange-alt"></i> Hareket Dökümü (<?php echo date('d.m.Y', strtotime($start_date)); ?> - <?php echo date('d.m.Y', strtotime($end_date)); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="movementTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Hareket Tipi</th>
                                    <th>Malzeme</th>
                                    <th>Miktar</th>
                                    <th>Referans</th>
                                    <th>Kullanıcı</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movement_report as $movement): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($movement['movement_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $movement['multiplier'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $movement['movement_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <strong><?php echo $movement['code']; ?></strong><br>
                                            <?php echo $movement['name']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo $movement['quantity']; ?> <?php echo $movement['uom_name']; ?>
                                    </td>
                                    <td><?php echo $movement['reference_no'] ?: '-'; ?></td>
                                    <td><?php echo $movement['full_name']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($filter === 'all' || $filter === 'critical'): ?>
            <!-- Kritik Stok Raporu -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Kritik Stok Uyarıları
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($critical_stock)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5>Kritik stok bulunmamaktadır</h5>
                            <p>Tüm malzemeler minimum stok seviyelerinin üzerindedir.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Malzeme Kodu</th>
                                        <th>Malzeme Adı</th>
                                        <th>Birim</th>
                                        <th>Mevcut Stok</th>
                                        <th>Min. Stok</th>
                                        <th>Eksik Miktar</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($critical_stock as $item): ?>
                                    <tr class="table-danger">
                                        <td><strong><?php echo $item['code']; ?></strong></td>
                                        <td><?php echo $item['name']; ?></td>
                                        <td><?php echo $item['uom_name']; ?></td>
                                        <td><?php echo $item['current_stock']; ?></td>
                                        <td><?php echo $item['min_stock_level']; ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo $item['min_stock_level'] - $item['current_stock']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="movements.php?action=add" class="btn btn-sm btn-success">
                                                <i class="fas fa-plus"></i> Stok Ekle
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// Excel'e aktarma fonksiyonu
function exportToExcel() {
    let table = '';
    let filename = 'Stok_Raporu_<?php echo date("Y-m-d"); ?>.xls';
    
    if (document.getElementById('stockTable')) {
        table = document.getElementById('stockTable').outerHTML;
    } else if (document.getElementById('movementTable')) {
        table = document.getElementById('movementTable').outerHTML;
    } else {
        table = document.querySelector('table').outerHTML;
    }
    
    let blob = new Blob([table], {type: 'application/vnd.ms-excel'});
    let url = URL.createObjectURL(blob);
    let a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// Sayfa yüklendiğinde tabloları sıralanabilir yap
document.addEventListener('DOMContentLoaded', function() {
    // Basit sıralama fonksiyonu (ileride DataTables eklenebilir)
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.title = 'Sıralamak için tıklayın';
            header.addEventListener('click', () => {
                sortTable(table, index);
            });
        });
    });
});

function sortTable(table, columnIndex) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        
        // Sayısal değerleri kontrol et
        const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
        const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return aNum - bNum;
        }
        
        return aText.localeCompare(bText, 'tr');
    });
    
    // Sıralanmış satırları tekrar ekle
    rows.forEach(row => tbody.appendChild(row));
}
</script>

<?php include '../includes/footer.php'; ?>