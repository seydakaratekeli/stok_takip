<?php
// pages/returns_action.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../config/helpers.php';

checkAuth();

// Yetki kontrolü
if (!hasPermission('iade') && !hasPermission('uretim_iade') && !hasPermission('all')) {
    $_SESSION['error'] = 'Bu işlem için yetkiniz yok!';
    header('Location: returns.php');
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// POST ile yeni iade oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_return') {
        try {
            $db = getDBConnection();
            $db->beginTransaction();
            
            // Validasyon
            $return_number = trim($_POST['return_number'] ?? '');
            $return_type = $_POST['return_type'] ?? '';
            $reason_id = intval($_POST['reason_id'] ?? 0);
            $return_date = $_POST['return_date'] ?? '';
            
            if (empty($return_number) || empty($return_type) || $reason_id <= 0 || empty($return_date)) {
                throw new Exception('Lütfen zorunlu alanları doldurun!');
            }
            
            // İade numarası benzersiz mi kontrol et
            $stmt = $db->prepare("SELECT id FROM returns WHERE return_number = ?");
            $stmt->execute([$return_number]);
            if ($stmt->fetch()) {
                throw new Exception('Bu iade numarası zaten kullanılıyor!');
            }
            
            // İadeyi oluştur
            $stmt = $db->prepare("
                INSERT INTO returns (return_number, return_type, related_order_id, related_movement_id, 
                                   reason_id, return_date, description, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $related_order_id = !empty($_POST['related_order_id']) ? intval($_POST['related_order_id']) : null;
            $related_movement_id = !empty($_POST['related_movement_id']) ? intval($_POST['related_movement_id']) : null;
            
            $stmt->execute([
                $return_number,
                $return_type,
                $related_order_id,
                $related_movement_id,
                $reason_id,
                $return_date,
                trim($_POST['description'] ?? ''),
                $_SESSION['user_id']
            ]);
            
            $return_id = $db->lastInsertId();
            $total_quantity = 0;
            
            // İade kalemlerini ekle
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item_data) {
                    if (!empty($item_data['item_id']) && !empty($item_data['quantity']) && !empty($item_data['unit_price'])) {
                        $item_id = intval($item_data['item_id']);
                        $quantity = floatval($item_data['quantity']);
                        $unit_price = floatval($item_data['unit_price']);
                        $total_price = $quantity * $unit_price;
                        
                        $stmt = $db->prepare("
                            INSERT INTO return_items (return_id, item_id, quantity, unit_price, total_price, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $return_id,
                            $item_id,
                            $quantity,
                            $unit_price,
                            $total_price,
                            $item_data['notes'] ?? ''
                        ]);
                        
                        $total_quantity += $quantity;
                    }
                }
            }
            
            // Toplam miktarı güncelle
            $stmt = $db->prepare("UPDATE returns SET total_quantity = ? WHERE id = ?");
            $stmt->execute([$total_quantity, $return_id]);
            
            // İade aktivitesi ekle
            $stmt = $db->prepare("
                INSERT INTO return_activities (return_id, activity_type, description, created_by) 
                VALUES (?, 'created', 'İade oluşturuldu', ?)
            ");
            $stmt->execute([$return_id, $_SESSION['user_id']]);
            
            $db->commit();
            $_SESSION['success'] = 'İade başarıyla oluşturuldu!';
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'İade oluşturulurken hata: ' . $e->getMessage();
        }
    }
    
    header('Location: returns.php');
    exit();
}

// GET ile durum güncelleme
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['status'])) {
    try {
        $db = getDBConnection();
        $return_id = intval($_GET['id']);
        $status = $_GET['status'];
        $user_id = $_SESSION['user_id'];
        
        // İade bilgilerini getir
        $stmt = $db->prepare("SELECT * FROM returns WHERE id = ?");
        $stmt->execute([$return_id]);
        $return = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$return) {
            throw new Exception('İade bulunamadı!');
        }
        
        // Yetki kontrolü - sadece yönetici veya ilgili kullanıcı onaylayabilir
        if (!hasPermission('all') && $return['created_by'] != $user_id) {
            throw new Exception('Bu iadeyi güncelleme yetkiniz yok!');
        }
        
        if ($status === 'approved') {
            // Onaylama işlemi
            $stmt = $db->prepare("UPDATE returns SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $return_id]);
            
            // Stok hareketi oluştur (iade türüne göre)
            if ($return['return_type'] === 'production') {
                // Üretim iadesi → Depo girişi
                $movement_type = 1; // Giriş
                $description = 'Üretim iadesi stok girişi';
            } else {
                // Tedarikçi iadesi → Depo çıkışı
                $movement_type = 2; // Çıkış
                $description = 'Tedarikçi iadesi stok çıkışı';
            }
            
            $stmt = $db->prepare("
                INSERT INTO stock_movements (movement_type_id, movement_date, reference_no, description, created_by) 
                VALUES (?, CURDATE(), ?, ?, ?)
            ");
            $reference_no = 'IADE-' . $return_id;
            $stmt->execute([$movement_type, $reference_no, $description, $user_id]);
            $movement_id = $db->lastInsertId();
            
            // İade kalemlerini stok hareketine ekle
            $stmt = $db->prepare("
                SELECT ri.item_id, ri.quantity 
                FROM return_items ri 
                WHERE ri.return_id = ?
            ");
            $stmt->execute([$return_id]);
            $return_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($return_items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO stock_movement_lines (stock_movement_id, item_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$movement_id, $item['item_id'], $item['quantity']]);
            }
            
            $activity_desc = 'İade onaylandı ve stok hareketi oluşturuldu';
            
        } elseif ($status === 'completed') {
            // Tamamlama işlemi (sadece onaylanmış iadeler için)
            if ($return['status'] !== 'approved') {
                throw new Exception('Sadece onaylanmış iadeler tamamlanabilir!');
            }
            
            $stmt = $db->prepare("UPDATE returns SET status = 'completed' WHERE id = ?");
            $stmt->execute([$return_id]);
            
            $activity_desc = 'İade tamamlandı';
            
        } elseif ($status === 'rejected') {
            // Reddetme işlemi
            $stmt = $db->prepare("UPDATE returns SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$return_id]);
            
            $activity_desc = 'İade reddedildi';
        }
        
        // Aktivite ekle
        $stmt = $db->prepare("
            INSERT INTO return_activities (return_id, activity_type, description, created_by) 
            VALUES (?, 'status_changed', ?, ?)
        ");
        $stmt->execute([$return_id, $activity_desc, $user_id]);
        
        $_SESSION['success'] = 'İade durumu güncellendi!';
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'İade güncelleme hatası: ' . $e->getMessage();
    }
    
    header('Location: return_details.php?id=' . $return_id);
    exit();
}

header('Location: returns.php');
exit();
?>