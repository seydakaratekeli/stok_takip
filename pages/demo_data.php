<?php
// demo_data.php - SADECE GELİŞTİRME ORTAMINDA KULLANIN!

// Doğru path için __DIR__ kullanıyoruz
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Session kontrolü - eğer başlatılmamışsa başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Erişim Engellendi</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="container mt-5">
        <div class="alert alert-danger">
            <h4>❌ Erişim Engellendi</h4>
            <p>Bu işlem için giriş yapmalısınız!</p>
            <a href="../login.php" class="btn btn-primary">Giriş Yap</a>
        </div>
    </body>
    </html>');
}

// Yönetici kontrolü
if (!isset($_SESSION['permissions']['all']) || $_SESSION['permissions']['all'] !== true) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Yetki Yok</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="container mt-5">
        <div class="alert alert-warning">
            <h4>⚠️ Yetkiniz Yok</h4>
            <p>Bu işlem için yönetici yetkisi gereklidir!</p>
            <a href="../index.php" class="btn btn-secondary">Dashboard\'a Dön</a>
        </div>
    </body>
    </html>');
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Demo Veri Yükleme</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .alert { margin: 10px 0; }
        .progress { margin: 20px 0; }
    </style>
</head>
<body class='container'>
    <div class='card shadow'>
        <div class='card-header bg-primary text-white'>
            <h2 class='mb-0'><i class='fas fa-database'></i> Demo Veri Yükleme</h2>
        </div>
        <div class='card-body'>";

try {
    $db = getDBConnection();
    $db->beginTransaction();

    echo "<div class='alert alert-info'>
            <h5><i class='fas fa-cog fa-spin'></i> Demo veriler yükleniyor...</h5>
            <div class='progress'>
                <div class='progress-bar progress-bar-striped progress-bar-animated' style='width: 0%' id='progressBar'>0%</div>
            </div>
          </div>";

    // 1. Malzeme kartları oluştur (Elektrikli scooter parçaları)
    $materials = [
        // Motor ve Batarya Grubu
        ['MOTOR-001', '250W Elektrik Motoru', 'Elektrikli scooter motoru', 1, 5],
        ['BATARYA-001', '36V 10Ah Lityum Batarya', 'Ana güç kaynağı', 1, 3],
        ['BATARYA-002', 'Batarya Şarj Cihazı', '36V batarya şarj cihazı', 1, 2],
        ['KONTROL-001', 'Motor Kontrol Kartı', 'ESC motor kontrol ünitesi', 1, 4],
        
        // Gövde ve Şase Grubu
        ['GOVDE-001', 'Alüminyum Ana Gövde', 'Ana taşıyıcı gövde', 1, 2],
        ['DECK-001', 'Ahşap Deck Tahtası', 'Ayakta durma platformu', 1, 3],
        ['SAP-001', 'Direksiyon Sapı', 'Alüminyum direksiyon kolu', 1, 4],
        ['SAP-002', 'Sap Tutamağı', 'Kauçuk tutamak', 1, 6],
        
        // Tekerlek ve Fren Grubu
        ['TEKER-001', '10" Lastik Tekerlek', 'Ön tekerlek seti', 1, 4],
        ['TEKER-002', '10" Lastik Tekerlek', 'Arka tekerlek seti', 1, 4],
        ['FREN-001', 'Arka Disk Fren Seti', 'Mekanik disk fren', 1, 3],
        ['FREN-002', 'Ön V-Fren Seti', 'V-tipi fren sistemi', 1, 3],
        
        // Elektronik ve Aydınlatma
        ['ELEK-001', 'Ana Elektrik Kablosu', '5 metre kablo', 3, 50],
        ['ELEK-002', 'Bağlantı Konnektörü', 'Waterproof konnektör', 1, 20],
        ['LCD-001', 'LCD Gösterge Paneli', 'Hız ve batarya göstergesi', 1, 3],
        ['LED-001', 'Ön LED Far', '10W LED far seti', 1, 4],
        ['LED-002', 'Arka Stop Lambası', 'LED stop lambası', 1, 4],
        
        // Vida ve Bağlantı Elemanları
        ['VIDA-001', 'M6x20 Vida Seti', '50 adet paket', 1, 10],
        ['VIDA-002', 'M8x30 Vida Seti', '30 adet paket', 1, 8],
        ['SOMUN-001', 'M6 Somun Seti', '100 adet paket', 1, 5],
        ['SOMUN-002', 'M8 Somun Seti', '50 adet paket', 1, 5],
        
        // Ambalaj ve Paketleme
        ['AMB-001', 'Karton Ambalaj Kutusu', 'Scooter paketleme kutusu', 1, 10],
        ['AMB-002', 'Köpük Koruma', 'Koruyucu köpük', 5, 20],
        ['AMB-003', 'Kullanım Kılavuzu', 'Türkçe kılavuz', 1, 15]
    ];

    $stmt = $db->prepare("INSERT INTO items (code, name, description, uom_id, min_stock_level) VALUES (?, ?, ?, ?, ?)");
    
    $added_items = 0;
    $total_items = count($materials);
    
    foreach ($materials as $index => $material) {
        try {
            $stmt->execute($material);
            $added_items++;
            $progress = round(($index + 1) / $total_items * 50); // İlk yarı malzeme ekleme
            echo "<script>document.getElementById('progressBar').style.width = '$progress%'; document.getElementById('progressBar').textContent = '$progress%';</script>";
            ob_flush();
            flush();
            
            echo "<div class='alert alert-success py-1'><i class='fas fa-check'></i> {$material[0]} - {$material[1]}</div>";
        } catch(PDOException $e) {
            echo "<div class='alert alert-warning py-1'><i class='fas fa-info-circle'></i> {$material[0]} zaten mevcut</div>";
        }
    }

    echo "<div class='alert alert-info'><strong>✅ $added_items malzeme kartı eklendi</strong></div>";

    // 2. Demo stok hareketleri oluştur
    $items = $db->query("SELECT id FROM items")->fetchAll(PDO::FETCH_ASSOC);
    $movement_types = $db->query("SELECT id, multiplier FROM movement_types")->fetchAll(PDO::FETCH_ASSOC);
    
    // Hareket tiplerini map'le
    $type_map = [];
    foreach ($movement_types as $type) {
        $type_map[$type['multiplier']] = $type['id'];
    }

    // Demo hareketler oluştur (son 30 gün)
    $movement_count = 0;
    $total_movements = 30;
    
    for ($i = 0; $i < $total_movements; $i++) {
        $movement_date = date('Y-m-d', strtotime("-" . rand(0, 30) . " days"));
        $movement_type_id = array_rand($type_map);
        $movement_type_id = $type_map[$movement_type_id];
        
        // Ana hareket kaydı
        $stmt = $db->prepare("
            INSERT INTO stock_movements (movement_type_id, movement_date, reference_no, description, created_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ref_no = 'REF-' . date('Ymd') . '-' . sprintf('%03d', $i);
        $descriptions = [
            'Tedarikçi teslimatı',
            'Üretim çıkışı',
            'Stok düzeltme',
            'Sayım farkı',
            'Müşteri iadesi',
            'Numune çıkışı'
        ];
        $description = $descriptions[array_rand($descriptions)];
        
        $stmt->execute([
            $movement_type_id,
            $movement_date,
            $ref_no,
            $description,
            $_SESSION['user_id']
        ]);
        
        $movement_id = $db->lastInsertId();
        
        // Hareket detayları (1-3 malzeme)
        $item_count = rand(1, 3);
        $used_items = [];
        
        for ($j = 0; $j < $item_count; $j++) {
            do {
                $item = $items[array_rand($items)];
            } while (in_array($item['id'], $used_items));
            
            $used_items[] = $item['id'];
            $quantity = rand(1, 10) * (($movement_type_id == $type_map[1]) ? 5 : 1);
            
            $stmt = $db->prepare("
                INSERT INTO stock_movement_lines (stock_movement_id, item_id, quantity) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$movement_id, $item['id'], $quantity]);
        }
        
        $movement_count++;
        $progress = 50 + round(($i + 1) / $total_movements * 50); // İkinci yarı hareket ekleme
        echo "<script>document.getElementById('progressBar').style.width = '$progress%'; document.getElementById('progressBar').textContent = '$progress%';</script>";
        ob_flush();
        flush();
        
        if ($i % 5 == 0) {
            echo "<div class='alert alert-info py-1'><i class='fas fa-sync-alt fa-spin'></i> $movement_count. hareket oluşturuldu...</div>";
        }
    }

    echo "<div class='alert alert-info'><strong>✅ $movement_count stok hareketi eklendi</strong></div>";

    $db->commit();
    
    echo "<script>document.getElementById('progressBar').style.width = '100%'; document.getElementById('progressBar').textContent = '100%';</script>";
    
    echo "<div class='alert alert-success mt-3'>
            <h4><i class='fas fa-check-circle'></i> Demo veriler başarıyla yüklendi!</h4>
            <p class='mb-3'>Sistem şimdi test edilmeye hazır. Aşağıdaki butonlardan devam edebilirsiniz.</p>
            <div class='mt-3'>
                <a href='../index.php' class='btn btn-primary'><i class='fas fa-tachometer-alt'></i> Dashboard'a Git</a>
                <a href='items.php' class='btn btn-success'><i class='fas fa-boxes'></i> Malzeme Listesini Gör</a>
                <a href='reports.php' class='btn btn-info'><i class='fas fa-chart-bar'></i> Raporları İncele</a>
            </div>
          </div>";

} catch(PDOException $e) {
    $db->rollBack();
    echo "<div class='alert alert-danger'>
            <h4><i class='fas fa-exclamation-triangle'></i> Hata oluştu!</h4>
            <p><strong>Hata:</strong> " . $e->getMessage() . "</p>
            <p>Veritabanı bağlantısını veya tablo yapısını kontrol edin.</p>
            <a href='test_scenarios.php' class='btn btn-warning'>Sistem Testlerini Çalıştır</a>
          </div>";
}

echo "        </div>
    </div>
    <script src='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js'></script>
</body>
</html>";
?>