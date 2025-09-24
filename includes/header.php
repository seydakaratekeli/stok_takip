<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elektrikli Scooter Stok Takip Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-motorcycle"></i> Scooter Stok Takip
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?> (<?php echo $_SESSION['role_name']; ?>)
                </span>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
            </div>
        </div>
    </nav>
    <?php endif; ?>