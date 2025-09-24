<?php
// install.php - SADECE GELİŞTİRME ORTAMINDA KULLANIN!
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Veritabanını oluştur
    $pdo->exec("CREATE DATABASE IF NOT EXISTS scooter_stok_takip");
    $pdo->exec("USE scooter_stok_takip");
    
    // Tabloları oluştur (önceki SQL kodunu buraya ekle)
    // ... tablo oluşturma SQL'leri ...
    
    // Test kullanıcılarını ekle
    $hashed_password = password_hash('123456', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password, full_name, role_id) VALUES 
        ('admin', '$hashed_password', 'Sistem Yöneticisi', 1)");
    
    echo "Kurulum tamamlandı! Kullanıcı: admin / 123456";
} catch(PDOException $e) {
    echo "Kurulum hatası: " . $e->getMessage();
}
?>