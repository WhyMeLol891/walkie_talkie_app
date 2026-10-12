<?php
declare(strict_types=1);

use App\Helpers\UrlHelper;
use App\Helpers\SecurityHelper;

$pageTitle = $pageTitle ?? ($appName ?? 'Walkie Talkie');
$manifestUrl = UrlHelper::url('manifest.webmanifest');
$cssUrl = UrlHelper::asset('css/app.css') . '?v=1.0.1';
$faviconSvg = UrlHelper::asset('icons/favicon.svg');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= SecurityHelper::e($pageTitle) ?></title>

    <!-- PWA Settings -->
    <link rel="manifest" href="<?= $manifestUrl ?>">
    <meta name="theme-color" content="#0d1117">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WalkieTalkie">
    <link rel="icon" type="image/svg+xml" href="<?= $faviconSvg ?>">
    <link rel="apple-touch-icon" href="<?= UrlHelper::asset('icons/icon-192.png') ?>">

    <!-- Google Fonts & CDN Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;600;700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Custom Walkie-Talkie Stylesheet -->
    <link rel="stylesheet" href="<?= $cssUrl ?>">
</head>
<body class="wt-body">
    <div class="wt-noise-overlay"></div>
