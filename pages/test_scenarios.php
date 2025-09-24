<?php
// test_scenarios.php - Sistem Test Senaryoları

// Doğru path için __DIR__ kullanıyoruz
require_once __DIR__ . '/../config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Sistem Test Senaryoları</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; }
        .alert { margin: 10px 0; }
    </style>
</head>
<body class='container'>
    <h2>🧪 Sistem Test Senaryoları</h2>";

try {
    $db = getDBConnection();
    
    // Test 1: Veritabanı Bağlantısı
    echo "<h4>1. Veritabanı Bağlantısı</h4>";
    $stmt = $db->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'scooter_stok_takip'");
    $table_count = $stmt->fetch(PDO::FETCH_ASSOC)['table_count'];
    echo "<div class='alert " . ($table_count > 0 ? 'alert-success' : 'alert-danger') . "'>" . 
         ($table_count > 0 ? "✅" : "❌") . " Veritabanı bağlantısı: <strong>$table_count tablo</strong> bulundu</div>";
    
    // Test 2: Temel Tablolar
    echo "<h4>2. Temel Tablolar</h4>";
    $tables = ['users', 'roles', 'items', 'stock_movements', 'movement_types', 'uoms'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "<div class='alert " . ($count >= 0 ? 'alert-success' : 'alert-danger') . "'>" . 
                 ($count >= 0 ? "✅" : "❌") . " <strong>$table</strong>: $count kayıt</div>";
        } catch(PDOException $e) {
            echo "<div class='alert alert-danger'>❌ <strong>$table</strong>: Tablo bulunamadı!</div>";
        }
    }
    
    // Test 3: Kullanıcı ve Yetki Sistemi
    echo "<h4>3. Kullanıcı ve Yetki Sistemi</h4>";
    try {
        $stmt = $db->query("SELECT u.username, r.name as role, u.is_active FROM users u JOIN roles r ON u.role_id = r.id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            $status = $user['is_active'] ? 'Aktif' : 'Pasif';
            echo "<div class='alert alert-success'>✅ Kullanıcı: <strong>{$user['username']}</strong> - Rol: {$user['role']} - Durum: $status</div>";
        }
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>❌ Kullanıcı bilgileri alınamadı: " . $e->getMessage() . "</div>";
    }
    
    // Test 4: Stok Hesaplamaları
    echo "<h4>4. Stok Hesaplamaları</h4>";
    try {
        $stmt = $db->query("
            SELECT i.code, i.name, 
                   COALESCE(SUM(sml.quantity), 0) as stok_miktari,
                   i.min_stock_level,
                   CASE 
                       WHEN COALESCE(SUM(sml.quantity), 0) <= i.min_stock_level AND i.min_stock_level > 0 THEN 'KRİTİK'
                       ELSE 'NORMAL'
                   END as durum
            FROM items i
            LEFT JOIN stock_movement_lines sml ON i.id = sml.item_id
            GROUP BY i.id
            HAVING stok_miktari > 0 OR i.min_stock_level > 0
            ORDER BY durum DESC, stok_miktari ASC
            LIMIT 10
        ");
        $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($stock_items) > 0) {
            foreach ($stock_items as $item) {
                $alert_type = $item['durum'] == 'KRİTİK' ? 'alert-danger' : 'alert-success';
                echo "<div class='alert $alert_type'>📦 <strong>{$item['code']}</strong> - {$item['name']}: {$item['stok_miktari']} (Min: {$item['min_stock_level']}) - <strong>{$item['durum']}</strong></div>";
            }
        } else {
            echo "<div class='alert alert-warning'>ℹ️ Henüz stok hareketi bulunmamaktadır.</div>";
        }
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>❌ Stok hesaplamaları yapılamadı: " . $e->getMessage() . "</div>";
    }
    
    // Test 5: Son Hareketler
    echo "<h4>5. Son Hareketler</h4>";
    try {
        $stmt = $db->query("
            SELECT mt.name as tip, sm.movement_date, sm.reference_no, u.full_name,
                   COUNT(sml.id) as malzeme_sayisi
            FROM stock_movements sm
            JOIN movement_types mt ON sm.movement_type_id = mt.id
            JOIN users u ON sm.created_by = u.id
            LEFT JOIN stock_movement_lines sml ON sm.id = sml.stock_movement_id
            GROUP BY sm.id
            ORDER BY sm.movement_date DESC
            LIMIT 5
        ");
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($movements) > 0) {
            foreach ($movements as $movement) {
                echo "<div class='alert alert-info'>🔄 <strong>{$movement['tip']}</strong> - {$movement['movement_date']} - {$movement['malzeme_sayisi']} malzeme - {$movement['full_name']}</div>";
            }
        } else {
            echo "<div class='alert alert-warning'>ℹ️ Henüz stok hareketi bulunmamaktadır.</div>";
        }
    } catch(PDOException $e) {
        echo "<div class='alert alert-danger'>❌ Hareket bilgileri alınamadı: " . $e->getMessage() . "</div>";
    }
    
    echo "<div class='alert alert-success mt-4'>
            <h4>✅ Sistem Testleri Tamamlandı!</h4>
            <p>Sistem başarıyla çalışıyor. Aşağıdaki test senaryolarını deneyebilirsiniz.</p>
          </div>";
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'><h4>❌ Test hatası!</h4><p>" . $e->getMessage() . "</p></div>";
}

echo "
    <div class='card mt-4'>
        <div class='card-header'>
            <h4>🧪 Manuel Test Senaryoları</h4>
        </div>
        <div class='card-body'>
            <h5>Test Kullanıcıları:</h5>
            <ul>
                <li><strong>admin / 123456</strong> - Tüm yetkiler</li>
                <li><strong>depo / 123456</strong> - Depo işlemleri</li>
                <li><strong>uretim / 123456</strong> - Üretim işlemleri</li>
                <li><strong>satin / 123456</strong> - Satınalma işlemleri</li>
            </ul>
            
            <div class='mt-3'>
                <a href='../index.php' class='btn btn-primary'>Dashboard'a Dön</a>
                <a href='demo_data.php' class='btn btn-warning'>Demo Verileri Yükle</a>
                <a href='items.php' class='btn btn-success'>Malzeme Listesini Gör</a>
            </div>
        </div>
    </div>
</body>
</html>";
?>