<?php
// pages/update_permissions.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDBConnection();
        
        foreach ($_POST['permissions'] as $role_id => $permissions) {
            // Yönetici rolünü atla
            if ($role_id == 1) continue;
            
            $permissions_json = json_encode($permissions);
            
            $stmt = $db->prepare("UPDATE roles SET permissions = ? WHERE id = ?");
            $stmt->execute([$permissions_json, $role_id]);
        }
        
        $_SESSION['success'] = 'Yetkiler başarıyla güncellendi!';
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Yetkiler güncellenirken hata: ' . $e->getMessage();
    }
    
    header('Location: role_check.php');
    exit();
}
?>