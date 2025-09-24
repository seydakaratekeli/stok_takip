<?php
// test_panel.php - Hızlı Test Paneli
require_once 'config/database.php';
require_once 'includes/auth.php';

checkAuth();
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-vial"></i> Sistem Test Paneli</h1>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-database fa-3x mb-3"></i>
                            <h4>Veritabanı Testi</h4>
                            <a href="pages/test_scenarios.php" class="btn btn-light">Test Et</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <i class="fas fa-boxes fa-3x mb-3"></i>
                            <h4>Demo Veri Yükle</h4>
                            <a href="pages/demo_data.php" class="btn btn-light">Veri Yükle</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h4>Hızlı Kontrol</h4>
                            <a href="pages/items.php" class="btn btn-light">Sistemi Aç</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5>Hızlı Test Adımları</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>1. Demo Veri Yükleme</h6>
                            <ol>
                                <li>"Demo Veri Yükle" butonuna tıkla</li>
                                <li>Verilerin yüklenmesini bekle</li>
                                <li>Dashboard'a dön ve istatistikleri kontrol et</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h6>2. Temel İşlem Testleri</h6>
                            <ol>
                                <li>Malzeme kartları sayfasına git</li>
                                <li>Yeni malzeme eklemeyi dene</li>
                                <li>Stok hareketi oluştur</li>
                                <li>Raporları görüntüle</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>3. Kullanıcı Testleri</h6>
                            <ul>
                                <li><strong>admin/123456</strong> - Tüm yetkiler</li>
                                <li><strong>depo/123456</strong> - Stok hareketleri</li>
                                <li><strong>uretim/123456</strong> - Üretim işlemleri</li>
                                <li><strong>satin/123456</strong> - Malzeme yönetimi</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>4. Beklenen Sonuçlar</h6>
                            <ul>
                                <li>✅ Dashboard'da veriler görünmeli</li>
                                <li>✅ Kritik stok uyarıları çalışmalı</li>
                                <li>✅ Raporlar doğru veri göstermeli</li>
                                <li>✅ Yetki kontrolleri çalışmalı</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>