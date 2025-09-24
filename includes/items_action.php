<?php
// items_action.php

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

// Yönlendirme fonksiyonu
function redirectWithMessage($type, $message) {
    $_SESSION[$type] = $message;
    header('Location: ../pages/items.php');
    exit();
}

// Yetki kontrolü
if (!hasPermission('items_manage') && !hasPermission('all')) {
    redirectWithMessage('error', 'Bu işlem için yetkiniz yok!');
}

try {
    $db = getDBConnection();

    // POST ile gelen işlemler (ekle/düzenle)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            // Validasyon
            $code   = trim($_POST['code'] ?? '');
            $name   = trim($_POST['name'] ?? '');
            $uom_id = intval($_POST['uom_id'] ?? 0);

            if (empty($code) || empty($name) || $uom_id <= 0) {
                redirectWithMessage('error', 'Lütfen zorunlu alanları doldurun!');
            }

            // Benzersizlik kontrolü
            $stmt = $db->prepare("SELECT id FROM items WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                redirectWithMessage('error', 'Bu malzeme kodu zaten kullanılıyor!');
            }

            // Ekleme işlemi
            $stmt = $db->prepare("
                INSERT INTO items (code, name, description, uom_id, min_stock_level, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([
                $code,
                $name,
                trim($_POST['description'] ?? ''),
                $uom_id,
                intval($_POST['min_stock_level'] ?? 0)
            ]);

            if ($result) {
                redirectWithMessage('success', 'Malzeme başarıyla eklendi!');
            } else {
                redirectWithMessage('error', 'Malzeme eklenirken hata oluştu!');
            }

        } elseif ($action === 'edit') {
            $item_id = intval($_POST['item_id'] ?? 0);
            $code    = trim($_POST['code'] ?? '');
            $name    = trim($_POST['name'] ?? '');
            $uom_id  = intval($_POST['uom_id'] ?? 0);

            if ($item_id <= 0) {
                redirectWithMessage('error', 'Geçersiz malzeme ID!');
            }

            // Benzersizlik kontrolü (kendisi hariç)
            $stmt = $db->prepare("SELECT id FROM items WHERE code = ? AND id != ?");
            $stmt->execute([$code, $item_id]);
            if ($stmt->fetch()) {
                redirectWithMessage('error', 'Bu malzeme kodu zaten kullanılıyor!');
            }

            // Güncelleme
            $stmt = $db->prepare("
                UPDATE items 
                SET code = ?, name = ?, description = ?, uom_id = ?, min_stock_level = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $code,
                $name,
                trim($_POST['description'] ?? ''),
                $uom_id,
                intval($_POST['min_stock_level'] ?? 0),
                $item_id
            ]);

            if ($result) {
                redirectWithMessage('success', 'Malzeme başarıyla güncellendi!');
            } else {
                redirectWithMessage('error', 'Malzeme güncellenirken hata oluştu!');
            }
        }

    }

    // GET ile silme işlemi
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $item_id = intval($_GET['id']);

        if ($item_id <= 0) {
            redirectWithMessage('error', 'Geçersiz malzeme ID!');
        }

        // Bağlı stok hareketi var mı kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM stock_movement_lines WHERE item_id = ?");
        $stmt->execute([$item_id]);
        $movement_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($movement_count > 0) {
            redirectWithMessage('error', 'Bu malzemenin stok hareketi bulunduğu için silinemez!');
        }

        // Silme işlemi
        $stmt = $db->prepare("DELETE FROM items WHERE id = ?");
        $result = $stmt->execute([$item_id]);

        if ($result && $stmt->rowCount() > 0) {
            redirectWithMessage('success', 'Malzeme başarıyla silindi!');
        } else {
            redirectWithMessage('error', 'Malzeme silinirken hata oluştu veya malzeme bulunamadı!');
        }
    }

    // Buraya kadar gelirse geçersiz istek demektir
    redirectWithMessage('error', 'Geçersiz istek!');

} catch (PDOException $e) {
    error_log("Items action hatası: " . $e->getMessage());
    redirectWithMessage('error', 'Veritabanı hatası: ' . $e->getMessage());
}
