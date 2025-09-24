<?php
// pages/users.php
require_once '../config/database.php';
require_once '../includes/auth.php';

checkAuth();

// Sadece yönetici erişebilir
if (!hasPermission('all')) {
    die('Bu sayfaya erişim yetkiniz yok!');
}

// Kullanıcı listesini getir
try {
    $db = getDBConnection();
    
    // Kullanıcıları getir
    $stmt = $db->query("
        SELECT u.*, r.name as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        ORDER BY u.is_active DESC, u.full_name
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Rolleri getir
    $stmt = $db->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_user') {
            // Yeni kullanıcı ekle
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, role_id, is_active) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['username'],
                $_POST['password'], // Plain text - basit tutuyoruz
                $_POST['full_name'],
                $_POST['role_id'],
                isset($_POST['is_active']) ? 1 : 0
            ]);
            
            $_SESSION['success'] = 'Kullanıcı başarıyla eklendi!';
            
        } elseif ($action === 'edit_user') {
            // Kullanıcı düzenle
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, role_id = ?, is_active = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['username'],
                $_POST['full_name'],
                $_POST['role_id'],
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['user_id']
            ]);
            
            // Şifre değiştirildiyse güncelle
            if (!empty($_POST['password'])) {
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$_POST['password'], $_POST['user_id']]);
            }
            
            $_SESSION['success'] = 'Kullanıcı başarıyla güncellendi!';
        }
        
        header('Location: users.php');
        exit();
        
    } catch(PDOException $e) {
        $error = "Hata: " . $e->getMessage();
    }
}

// Kullanıcı silme
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        // Kendi hesabını silmeyi engelle
        if ($_GET['id'] != $_SESSION['user_id']) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $_SESSION['success'] = 'Kullanıcı silindi!';
        } else {
            $error = 'Kendi kullanıcı hesabınızı silemezsiniz!';
        }
    } catch(PDOException $e) {
        $error = "Hata: " . $e->getMessage();
    }
    
    header('Location: users.php');
    exit();
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-users"></i> Kullanıcı Yönetimi</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus"></i> Yeni Kullanıcı
                </button>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Kullanıcı Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Kullanıcı Listesi</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kullanıcı Adı</th>
                                    <th>Ad Soyad</th>
                                    <th>Rol</th>
                                    <th>Durum</th>
                                    <th>Oluşturulma</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-info">Siz</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $user['role_name']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="editUser(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>', '<?php echo $user['full_name']; ?>', <?php echo $user['role_id']; ?>, <?php echo $user['is_active']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- İstatistik Kartları -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <h4><?php echo count($users); ?></h4>
                            <p>Toplam Kullanıcı</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($users, function($u) { return $u['is_active']; })); ?></h4>
                            <p>Aktif Kullanıcı</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <h4><?php echo count(array_filter($users, function($u) { return !$u['is_active']; })); ?></h4>
                            <p>Pasif Kullanıcı</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <h4><?php echo count($roles); ?></h4>
                            <p>Toplam Rol</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Yeni Kullanıcı Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Kullanıcı Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı *</label>
                        <input type="text" class="form-control" name="username" required 
                               placeholder="Kullanıcı adı girin">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Şifre *</label>
                        <input type="password" class="form-control" name="password" required 
                               placeholder="Şifre girin" value="123456">
                        <small class="form-text text-muted">Varsayılan şifre: 123456</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad *</label>
                        <input type="text" class="form-control" name="full_name" required 
                               placeholder="Ad ve soyad girin">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select class="form-select" name="role_id" required>
                            <option value="">Rol seçin</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Kullanıcı aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kullanıcı Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kullanıcı Düzenle Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Kullanıcı Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı *</label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" name="password" id="edit_password" 
                               placeholder="Değiştirmek istemiyorsanız boş bırakın">
                        <small class="form-text text-muted">Şifreyi değiştirmek istemiyorsanız boş bırakın</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ad Soyad *</label>
                        <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rol *</label>
                        <select class="form-select" name="role_id" id="edit_role_id" required>
                            <option value="">Rol seçin</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Kullanıcı aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Kullanıcı düzenleme modalını aç
function editUser(id, username, fullName, roleId, isActive) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_full_name').value = fullName;
    document.getElementById('edit_role_id').value = roleId;
    document.getElementById('edit_is_active').checked = Boolean(isActive);
    document.getElementById('edit_password').value = '';
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

// Kullanıcı silme onayı
function deleteUser(id, username) {
    if (confirm('"' + username + '" kullanıcısını silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!')) {
        window.location.href = 'users.php?action=delete&id=' + id;
    }
}

// Form kontrolleri
document.addEventListener('DOMContentLoaded', function() {
    // Kullanıcı adı benzersizlik kontrolü (basit)
    const usernameInputs = document.querySelectorAll('input[name="username"]');
    usernameInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const username = this.value.trim();
            if (username.length > 0) {
                // Burada AJAX ile kullanıcı adı kontrolü yapılabilir
                console.log('Kullanıcı adı kontrol ediliyor:', username);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>