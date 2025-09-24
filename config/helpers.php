<?php
// config/helpers.php

// Base URL fonksiyonu
function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    
    $url = $protocol . '://' . $host . $base;
    
    if ($path) {
        $url .= ltrim($path, '/');
    }
    
    return $url;
}

// Link oluşturma fonksiyonu
function site_url($path = '') {
    return base_url($path);
}

// Asset linkleri için
function asset_url($path = '') {
    return base_url('assets/' . $path);
}
?>