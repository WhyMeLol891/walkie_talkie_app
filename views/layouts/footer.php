<?php
declare(strict_types=1);

use App\Helpers\UrlHelper;
?>
    <!-- PWA Install Toast / Prompt Banner -->
    <div id="pwaInstallBanner" class="wt-pwa-prompt d-none" role="alert" aria-live="polite">
        <div class="d-flex align-items-center justify-content-between p-3">
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid fa-walkie-talkie fs-3 text-warning"></i>
                <div>
                    <div class="fw-bold text-light">Install Walkie-Talkie App</div>
                    <small class="text-secondary">Fast access from your home screen or desktop</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" id="btnInstallPwa" class="btn btn-sm btn-warning fw-semibold px-3">Install</button>
                <button type="button" id="btnDismissPwa" class="btn btn-sm btn-outline-secondary" aria-label="Dismiss">&times;</button>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PWA Service Worker Handler -->
    <script src="<?= UrlHelper::asset('js/pwa.js') ?>?v=1.0.1"></script>
</body>
</html>
