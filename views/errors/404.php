<?php
declare(strict_types=1);

use App\Helpers\UrlHelper;

$pageTitle = "404 Not Found | Walkie Talkie";
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="wt-container">
    <div class="container min-vh-100 d-flex flex-column justify-content-center align-items-center py-4 text-center">
        <div class="card wt-radio-chassis border-0 shadow-lg p-5 col-12 col-sm-8 col-md-6 col-lg-5">
            <div class="mb-3">
                <i class="fa-solid fa-satellite-dish fa-4x text-warning"></i>
            </div>
            <h1 class="wt-app-title mb-2">404</h1>
            <h4 class="text-light mb-3">FREQUENCY OUT OF RANGE</h4>
            <p class="text-secondary mb-4">The channel or page you are looking for does not exist on this station.</p>
            <a href="<?= UrlHelper::url() ?>" class="btn wt-btn-primary py-2 px-4 fw-bold text-uppercase">
                <i class="fa-solid fa-house me-2"></i> Return to Station
            </a>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
