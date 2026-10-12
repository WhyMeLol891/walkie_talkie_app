<?php
declare(strict_types=1);

use App\Helpers\UrlHelper;

$pageTitle = "500 Server Error | Walkie Talkie";
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="wt-container">
    <div class="container min-vh-100 d-flex flex-column justify-content-center align-items-center py-4 text-center">
        <div class="card wt-radio-chassis border-0 shadow-lg p-5 col-12 col-sm-8 col-md-6 col-lg-5">
            <div class="mb-3">
                <i class="fa-solid fa-triangle-exclamation fa-4x text-danger"></i>
            </div>
            <h1 class="wt-app-title text-danger mb-2">500</h1>
            <h4 class="text-light mb-3">INTERNAL SYSTEM ERROR</h4>
            <p class="text-secondary mb-4">Radio transceiver encountered an unexpected interruption. Our technicians have logged the incident.</p>
            <a href="<?= UrlHelper::url() ?>" class="btn wt-btn-primary py-2 px-4 fw-bold text-uppercase">
                <i class="fa-solid fa-rotate-right me-2"></i> Try Again
            </a>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
