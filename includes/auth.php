<?php
// includes/auth.php

// Session'ı sadece başlatılmamışsa başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Önce database.php'yi require et (redirect fonksiyonu için)
require_once __DIR__ . '/../config/database.php';

// Oturum kontrolü
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Kullanıcı bilgilerini getir
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u 
                             JOIN roles r ON u.role_id = r.id 
                             WHERE u.id = ? AND u.is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return null;
    }
}

// Basit şifre doğrulama (plain text)
function verifyPassword($password, $storedPassword) {
    return $password === $storedPassword;
}

// Şifre hashleme
function hashPassword($password) {
    return $password; // Plain text - basit tutuyoruz
}

// Yetki kontrolü (ARTIK SADECE BURADA)
function hasPermission($permission) {
    if (!isset($_SESSION['permissions'])) return false;
    
    // Yönetici her şeyi yapabilir
    if (isset($_SESSION['permissions']['all']) && $_SESSION['permissions']['all'] === true) {
        return true;
    }
    
    return isset($_SESSION['permissions'][$permission]) && $_SESSION['permissions'][$permission] === true;
}

// Auth kontrolü (sayfalarda kullanılacak)
function checkAuth() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

// Kullanıcıyı logine yönlendir
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Yönetici yetkisi gerektiren sayfalarda kullanılacak
function requireAdmin() {
    requireLogin();
    
    if (!hasPermission('all')) {
        die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Yetki Yok</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="container mt-5">
            <div class="alert alert-danger">
                <h4>⛔ Yetkiniz Yok</h4>
                <p>Bu sayfaya erişim için yönetici yetkisi gereklidir.</p>
                <a href="index.php" class="btn btn-primary">Dashboard\'a Dön</a>
            </div>
        </body>
        </html>');
    }
}

// Belirli bir yetki gerektiren sayfalarda kullanılacak
function requirePermission($permission) {
    requireLogin();
    
    if (!hasPermission($permission) && !hasPermission('all')) {
        die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Yetki Yok</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="container mt-5">
            <div class="alert alert-warning">
                <h4>⚠️ Yetkiniz Yok</h4>
                <p>Bu sayfaya erişim için gerekli yetkiniz bulunmamaktadır.</p>
                <a href="index.php" class="btn btn-secondary">Dashboard\'a Dön</a>
            </div>
        </body>
        </html>');
    }
}

// Kullanıcı oturumunu başlat (login işleminde kullanılacak)
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['permissions'] = json_decode($user['permissions'], true);
    $_SESSION['login_time'] = time();
}

// Kullanıcı oturumunu sonlandır
function logoutUser() {
    $_SESSION = array();
    
    // Cookie'yi de sil
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}
?>