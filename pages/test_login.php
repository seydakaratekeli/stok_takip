<?php
// test_login.php
require_once 'config/database.php';

try {
    $db = getDBConnection();
    
    // Kullanıcıları kontrol et
    $stmt = $db->query("SELECT username, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Kullanıcı Listesi:</h3>";
    foreach ($users as $user) {
        echo "Kullanıcı: " . $user['username'] . " | Şifre: " . $user['password'] . "<br>";
    }
    
    echo "<h3>Test Giriş:</h3>";
    echo "admin/123456 karşılaştırması: " . ('123456' === '123456' ? 'DOĞRU' : 'YANLIŞ');
    
} catch(PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?>