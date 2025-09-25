<!-- includes/sidebar.php -->
<nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">

        <ul class="nav flex-column">

            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/stok_takip/index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>" href="/stok_takip/pages/items.php">
                    <i class="fas fa-boxes"></i> Malzeme Kartları
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'movements.php' ? 'active' : ''; ?>" href="/stok_takip/pages/movements.php">
                    <i class="fas fa-exchange-alt"></i> Stok Hareketleri
                </a>
            </li>

            <!-- ÜRETİM MODÜLLERİ -->
            <?php if (hasPermission('production_movements') || hasPermission('all')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'production_orders.php' ? 'active' : ''; ?>" href="/stok_takip/pages/production_orders.php">
                    <i class="fas fa-industry"></i> Üretim Emirleri
                </a>
            </li>
            <?php endif; ?>
              <!-- Mal Kabul -->
            <?php if (hasPermission('mal_kabul') || hasPermission('all')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'goods_receipt.php' ? 'active' : ''; ?>"  href="/stok_takip/pages/goods_receipt.php">
                    <i class="fas fa-clipboard-check"></i> Mal Kabul
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'counts.php' ? 'active' : ''; ?>" href="/stok_takip/pages/counts.php">
        <i class="fas fa-clipboard-check"></i> Stok Sayımı
    </a>
</li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="/stok_takip/pages/reports.php">
                    <i class="fas fa-chart-bar"></i> Raporlar
                </a>
            </li>
            <?php if (hasPermission('siparis') || hasPermission('all')): ?>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" href="/stok_takip/pages/orders.php">
                    <i class="fas fa-shopping-cart"></i> Sipariş Yönetimi
    </a>
</li>
<?php endif; ?>

            <?php if (hasPermission('all')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="/stok_takip/pages/users.php">
                    <i class="fas fa-users"></i> Kullanıcı Yönetimi
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('all')): ?>
<li class="nav-item">
    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'role_check.php' ? 'active' : ''; ?>" href="/stok_takip/pages/role_check.php">
        <i class="fas fa-user-shield"></i> Yetki Kontrolü
    </a>
</li>
<?php endif; ?>
        </ul>
        
        <hr>
        
        <!-- Alt Menü -->
        <ul class="nav flex-column small">
            <li class="nav-item">
                <span class="nav-link text-muted">
                    <i class="fas fa-info-circle"></i> Sistem: Scooter Stok
                </span>
            </li>
            <li class="nav-item">
                <span class="nav-link text-muted">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                </span>
            </li>
        </ul>
    </div>
</nav>