<?php
// pages/fix_permissions.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();
requireAdmin();

try {
    $db = getDBConnection();
    
    // Dokümanda belirtilen yetki gereksinimleri
    $required_permissions = [
        // Yönetici
        1 => ['all' => true],
        
        // Depo Görevlisi
        2 => [
            'items_view' => true,
            'movements' => true,
            'counts' => true,
            'iade' => true,
            'view_reports' => true
        ],
        
        // Üretim Operatörü
        3 => [
            'items_view' => true,
            'production_movements' => true,
            'uretim_iade' => true
        ],
        
        // Satınalma
        4 => [
            'items_manage' => true,
            'siparis' => true,
            'view_reports' => true
        ]
    ];
    
    foreach ($required_permissions as $role_id => $permissions) {
        $permissions_json = json_encode($permissions);
        
        $stmt = $db->prepare("UPDATE roles SET permissions = ? WHERE id = ?");
        $stmt->execute([$permissions_json, $role_id]);
        
        echo "<p>✅ Rol ID $role_id yetkileri güncellendi</p>";
    }
    
    $_SESSION['success'] = 'Tüm yetkiler dokümana uygun şekilde güncellendi!';
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Yetkiler güncellenirken hata: ' . $e->getMessage();
}

header('Location: role_check.php');
exit();
?>