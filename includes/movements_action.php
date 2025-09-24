<?php
// includes/movements_action.php
require_once '../config/database.php';
require_once 'auth.php';

checkAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $db = getDBConnection();

    // 🔹 Silme İşlemi (sadece yönetici)
    if ($action === 'delete') {
        if (!hasPermission('all')) {
            die('Bu işlemi yapmaya yetkiniz yok!');
        }

        $id = $_GET['id'] ?? '';
        if (!empty($id)) {
            $db->beginTransaction();

            // Önce hareket detaylarını sil
            $stmt = $db->prepare("DELETE FROM stock_movement_lines WHERE stock_movement_id = ?");
            $stmt->execute([$id]);

            // Ana hareket kaydını sil
            $stmt = $db->prepare("DELETE FROM stock_movements WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
            $_SESSION['success'] = "Stok hareketi başarıyla silindi!";
        }

        header('Location: ../pages/movements.php');
        exit();
    }

    // 🔹 Güncelleme İşlemi (Depo Görevlisi, Üretim Operatörü, Yönetici)
    if ($action === 'update_movement') {
        if (!hasPermission('movements') && !hasPermission('all')) {
            die('Bu işlemi yapmaya yetkiniz yok!');
        }

        $movement_id   = $_POST['movement_id'];
        $movement_type = $_POST['movement_type_id'];
        $movement_date = $_POST['movement_date'];
        $reference_no  = $_POST['reference_no'];
        $description   = $_POST['description'];

        $item_ids   = $_POST['item_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        $db->beginTransaction();

        // Ana kaydı güncelle
        $stmt = $db->prepare("
            UPDATE stock_movements 
            SET movement_type_id = ?, movement_date = ?, reference_no = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$movement_type, $movement_date, $reference_no, $description, $movement_id]);

        // Eski detayları sil
        $stmt = $db->prepare("DELETE FROM stock_movement_lines WHERE stock_movement_id = ?");
        $stmt->execute([$movement_id]);

        // Yeni detayları ekle
        foreach ($item_ids as $index => $item_id) {
            if (!empty($item_id) && !empty($quantities[$index])) {
                $stmt = $db->prepare("
                    INSERT INTO stock_movement_lines (stock_movement_id, item_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$movement_id, $item_id, $quantities[$index]]);
            }
        }

        $db->commit();
        $_SESSION['success'] = "Stok hareketi başarıyla güncellendi!";
        header("Location: ../pages/movements.php");
        exit();
    }

} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['error'] = "Hata: " . $e->getMessage();
    header('Location: ../pages/movements.php');
    exit();
}
