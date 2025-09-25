<?php
// includes/counts_action.php
require_once '../config/database.php';
require_once 'auth.php';

checkAuth();

// Sadece yönetici sayım silebilir
if (!hasPermission('all')) {
    die('Bu işlemi yapmaya yetkiniz yok!');
}

$action = $_GET['action'] ?? '';

try {
    $db = getDBConnection();

    if ($action === 'delete') {
        $count_id = $_GET['id'] ?? '';
        
        if (!empty($count_id)) {
            $db->beginTransaction();

            // Önce sayım detaylarını sil
            $stmt = $db->prepare("DELETE FROM inventory_count_lines WHERE inventory_count_id = ?");
            $stmt->execute([$count_id]);

            // Ana sayım kaydını sil
            $stmt = $db->prepare("DELETE FROM inventory_counts WHERE id = ?");
            $stmt->execute([$count_id]);

            $db->commit();
            $_SESSION['success'] = "Sayım kaydı başarıyla silindi!";
        }
    }

    header('Location: ../pages/counts.php');
    exit();

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error'] = "Hata: " . $e->getMessage();
    header('Location: ../pages/counts.php');
    exit();
}