<?php
// login.php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Oturum açıksa yönlendir
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = "Kullanıcı adı ve şifre gerekli!";
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT u.*, r.permissions, r.name as role_name 
                                 FROM users u 
                                 JOIN roles r ON u.role_id = r.id 
                                 WHERE u.username = ? AND u.is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Yeni loginUser fonksiyonunu kullan
                loginUser($user);
                redirect('index.php');
            } else {
                $error = "Kullanıcı adı veya şifre hatalı!";
            }
        } catch(PDOException $e) {
            $error = "Sistem hatası: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Scooter Stok Takip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4><i class="fas fa-motorcycle"></i> Scooter Stok Takip</h4>
                        <p class="mb-0">Giriş Yap</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Kullanıcı Adı</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="admin" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       value="123456" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt"></i> Giriş Yap
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="mt-3">
                            <h6><i class="fas fa-users"></i> Test Kullanıcıları:</h6>
                            <div class="small">
                                <strong>admin</strong> / 123456 (Yönetici)<br>
                                <strong>depo</strong> / 123456 (Depo Görevlisi)<br>
                                <strong>uretim</strong> / 123456 (Üretim Operatörü)<br>
                                <strong>satin</strong> / 123456 (Satınalma)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>