<?php
// pages/orders_action.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü - Satınalma ve Yönetici
if (!hasPermission('siparis') && !hasPermission('all')) {
    $_SESSION['error'] = 'Bu işlem için yetkiniz yok!';
    header('Location: orders.php');
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// POST ile yeni sipariş oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_order') {
        try {
            $db = getDBConnection();
            $db->beginTransaction();
            
            // Validasyon
            $order_number = trim($_POST['order_number'] ?? '');
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            $order_date = $_POST['order_date'] ?? '';
            
            if (empty($order_number) || $supplier_id <= 0 || empty($order_date)) {
                throw new Exception('Lütfen zorunlu alanları doldurun!');
            }
            
            // Sipariş numarası benzersiz mi kontrol et
            $stmt = $db->prepare("SELECT id FROM orders WHERE order_number = ?");
            $stmt->execute([$order_number]);
            if ($stmt->fetch()) {
                throw new Exception('Bu sipariş numarası zaten kullanılıyor!');
            }
            
            // Siparişi oluştur (başlangıç durumu: Beklemede)
            $stmt = $db->prepare("
                INSERT INTO orders (order_number, supplier_id, order_date, expected_date, status_id, notes, created_by) 
                VALUES (?, ?, ?, ?, 1, ?, ?)
            ");
            $stmt->execute([
                $order_number,
                $supplier_id,
                $order_date,
                $_POST['expected_date'] ?? null,
                trim($_POST['notes'] ?? ''),
                $_SESSION['user_id']
            ]);
            
            $order_id = $db->lastInsertId();
            $total_amount = 0;
            
            // Sipariş kalemlerini ekle
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item_data) {
                    if (!empty($item_data['item_id']) && !empty($item_data['quantity']) && !empty($item_data['unit_price'])) {
                        $item_id = intval($item_data['item_id']);
                        $quantity = floatval($item_data['quantity']);
                        $unit_price = floatval($item_data['unit_price']);
                        $total_price = $quantity * $unit_price;
                        
                        $stmt = $db->prepare("
                            INSERT INTO order_items (order_id, item_id, quantity, unit_price, total_price, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $order_id,
                            $item_id,
                            $quantity,
                            $unit_price,
                            $total_price,
                            $item_data['notes'] ?? ''
                        ]);
                        
                        $total_amount += $total_price;
                    }
                }
            }
            
            // Toplam tutarı güncelle
            $stmt = $db->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $stmt->execute([$total_amount, $order_id]);
            
            // Sipariş aktivitesi ekle
            $stmt = $db->prepare("
                INSERT INTO order_activities (order_id, activity_type, description, created_by) 
                VALUES (?, 'created', 'Sipariş oluşturuldu', ?)
            ");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            
            $db->commit();
            $_SESSION['success'] = 'Sipariş başarıyla oluşturuldu!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Sipariş oluşturulurken hata: ' . $e->getMessage();
        }
    }
    
    header('Location: orders.php');
    exit();
}
// GET ile sipariş silme
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $db = getDBConnection();
        $order_id = intval($_GET['id']);
        
        // Siparişi oluşturan veya yönetici silebilir
        $stmt = $db->prepare("SELECT created_by FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Sipariş bulunamadı!');
        }
        
        if ($_SESSION['user_id'] != $order['created_by'] && !hasPermission('all')) {
            throw new Exception('Bu siparişi silme yetkiniz yok!');
        }
        
        // Siparişi sil (CASCADE sayesinde kalemler de silinecek)
        $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        
        $_SESSION['success'] = 'Sipariş başarıyla silindi!';
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Sipariş silinirken hata: ' . $e->getMessage();
    }
    
    header('Location: orders.php');
    exit();
}
// GET ile durum güncelleme
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['status'])) {
    try {
        $db = getDBConnection();
        $order_id = intval($_GET['id']);
        $status_id = intval($_GET['status']);
        
        // Sipariş durumunu güncelle
        $stmt = $db->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
        $stmt->execute([$status_id, $order_id]);
        
        // Durum adını al
        $stmt = $db->prepare("SELECT name FROM order_statuses WHERE id = ?");
        $stmt->execute([$status_id]);
        $status_name = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
        
        // Aktivite ekle
        $stmt = $db->prepare("
            INSERT INTO order_activities (order_id, activity_type, description, created_by) 
            VALUES (?, 'status_changed', ?, ?)
        ");
        $stmt->execute([$order_id, "Durum değiştirildi: " . $status_name, $_SESSION['user_id']]);
        
        // Eğer teslim edildiyse, stok girişi yap
        if ($status_id == 4) { // Teslim Edildi durumu
            // Stok hareketi oluştur
            $stmt = $db->prepare("
                INSERT INTO stock_movements (movement_type_id, movement_date, reference_no, description, created_by) 
                VALUES (1, CURDATE(), ?, 'Sipariş teslim stok girişi', ?)
            ");
            $reference_no = 'SIP-TESLIM-' . $order_id;
            $stmt->execute([$reference_no, $_SESSION['user_id']]);
            $movement_id = $db->lastInsertId();
            
            // Sipariş kalemlerini stoka ekle
            $stmt = $db->prepare("
                SELECT oi.item_id, oi.quantity 
                FROM order_items oi 
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($order_items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO stock_movement_lines (stock_movement_id, item_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$movement_id, $item['item_id'], $item['quantity']]);
            }
        }
        
        $_SESSION['success'] = 'Sipariş durumu güncellendi!';
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Durum güncelleme hatası: ' . $e->getMessage();
    }
    
    header('Location: order_details.php?id=' . $order_id);
    exit();
}

header('Location: orders.php');
exit();
?>