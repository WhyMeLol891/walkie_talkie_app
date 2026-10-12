<?php
declare(strict_types=1);

use App\Helpers\UrlHelper;
use App\Helpers\SecurityHelper;

$pageTitle = "Join Channel | " . ($appName ?? 'Walkie Talkie');
require __DIR__ . '/layouts/header.php';
?>

<div class="wt-container">
    <div class="container min-vh-100 d-flex flex-column justify-content-center py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5">
                
                <!-- Walkie-Talkie Tactical Shell -->
                <div class="card wt-radio-chassis border-0 shadow-lg">
                    
                    <!-- Radio Antenna & Top Dial Deco -->
                    <div class="wt-radio-top-bar d-flex justify-content-between align-items-center px-4 pt-3">
                        <div class="wt-antenna-mount">
                            <span class="wt-antenna-rod"></span>
                            <span class="wt-antenna-tip"></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="wt-led-dot wt-led-green" title="System Ready"></span>
                            <span class="wt-digital-band text-uppercase">VHF // 144.250 MHz</span>
                        </div>
                    </div>

                    <div class="card-body p-4 p-sm-5 pt-3">
                        
                        <!-- Header Branding -->
                        <div class="text-center mb-4">
                            <div class="wt-logo-badge mb-3">
                                <i class="fa-solid fa-walkie-talkie fa-3x wt-logo-icon"></i>
                            </div>
                            <h1 class="wt-app-title mb-1"><?= SecurityHelper::e($appName ?? 'WALKIE TALKIE') ?></h1>
                            <div class="wt-subtitle text-uppercase">
                                <span>Push to Talk</span>
                                <span class="mx-2">•</span>
                                <span>Instant Voice Comm</span>
                            </div>
                        </div>

                        <!-- Flash Alerts -->
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 border-0 rounded-3 mb-3" role="alert">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <div><?= SecurityHelper::e($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 border-0 rounded-3 mb-3" role="alert">
                                <i class="fa-solid fa-circle-check"></i>
                                <div><?= SecurityHelper::e($success) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Join Form -->
                        <form action="<?= UrlHelper::url('join') ?>" method="POST" id="joinForm" novalidate>
                            <input type="hidden" name="_csrf_token" value="<?= SecurityHelper::e($csrfToken) ?>">

                            <!-- Nickname Field -->
                            <div class="mb-3">
                                <label for="nickname" class="form-label wt-input-label">
                                    <i class="fa-solid fa-user me-1 text-warning"></i> Callsign / Nickname
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text wt-input-group-text">
                                        <i class="fa-solid fa-id-badge"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control wt-form-input" 
                                           id="nickname" 
                                           name="nickname" 
                                           maxlength="24" 
                                           required 
                                           placeholder="e.g. Alex, Student01" 
                                           value="<?= SecurityHelper::e($prefillNickname) ?>"
                                           autocomplete="nickname"
                                           autofocus>
                                </div>
                                <div class="form-text text-secondary small">Max 24 characters. Shown to other operators.</div>
                            </div>

                            <!-- Channel Field -->
                            <div class="mb-4">
                                <label for="channel" class="form-label wt-input-label">
                                    <i class="fa-solid fa-tower-broadcast me-1 text-info"></i> Channel Frequency
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text wt-input-group-text">
                                        <i class="fa-solid fa-hashtag"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control wt-form-input text-uppercase" 
                                           id="channel" 
                                           name="channel" 
                                           maxlength="64" 
                                           required 
                                           placeholder="e.g. security, team-alpha" 
                                           value="<?= SecurityHelper::e($prefillChannel ?: 'general') ?>"
                                           autocomplete="off">
                                </div>
                                <div class="form-text text-secondary small">
                                    Enter the same channel name as your team members to communicate together.
                                </div>

                                <!-- Preset Channel Quick Chips -->
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <button type="button" class="btn btn-sm wt-chip-btn" data-channel="general">#general</button>
                                    <button type="button" class="btn btn-sm wt-chip-btn" data-channel="team-a">#team-a</button>
                                    <button type="button" class="btn btn-sm wt-chip-btn" data-channel="security">#security</button>
                                    <button type="button" class="btn btn-sm wt-chip-btn" data-channel="ops">#ops</button>
                                </div>
                            </div>

                            <!-- Join Button -->
                            <button type="submit" class="btn wt-btn-primary w-100 py-3 fw-bold text-uppercase d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-solid fa-headset fa-lg"></i>
                                <span>Join Channel</span>
                            </button>
                        </form>

                        <!-- System Specs & QR Code Section -->
                        <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 text-center">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="small text-secondary">
                                    <i class="fa-solid fa-users me-1"></i> Limit: <?= (int) $maxPeers ?> Peers / Room
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-secondary wt-qr-toggle-btn" id="btnToggleQr">
                                    <i class="fa-solid fa-qrcode me-1"></i> Phone QR
                                </button>
                            </div>

                            <!-- Expandable QR Code Box -->
                            <div id="qrCodeCard" class="wt-qr-box d-none p-3 rounded-3 mb-2">
                                <div class="fw-semibold text-light mb-1">Open on your phone</div>
                                <div class="small text-secondary mb-2">Scan this code on the same Wi-Fi network to connect mobile browsers</div>
                                <div class="d-flex justify-content-center my-2">
                                    <canvas id="qrCanvas" class="wt-qr-canvas p-2 bg-white rounded-2 shadow-sm"></canvas>
                                </div>
                                <div class="mt-2">
                                    <code class="small text-warning wt-url-text" id="appUrlDisplay"><?= SecurityHelper::e($appUrl) ?></code>
                                </div>
                            </div>

                            <p class="wt-footer-note mb-0 text-secondary">
                                WebRTC Audio Mesh • Audio-Only • No Registration Needed
                            </p>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Standalone Pure JS QR Code Generator Library (Zero Dependencies, Offline LAN Capable) -->
<script src="<?= UrlHelper::asset('js/qrcode.min.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick Channel Chips
    document.querySelectorAll('.wt-chip-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const channelInput = document.getElementById('channel');
            channelInput.value = btn.getAttribute('data-channel');
            channelInput.focus();
        });
    });

    // QR Code Generator
    const btnToggleQr = document.getElementById('btnToggleQr');
    const qrCard = document.getElementById('qrCodeCard');
    const qrCanvas = document.getElementById('qrCanvas');
    const appUrl = window.location.href;
    let qrRendered = false;

    if (btnToggleQr && qrCard) {
        btnToggleQr.addEventListener('click', () => {
            const isHidden = qrCard.classList.contains('d-none');
            if (isHidden) {
                qrCard.classList.remove('d-none');
                btnToggleQr.classList.add('active');
                if (!qrRendered && window.QRCodeLib) {
                    window.QRCodeLib.render(appUrl, qrCanvas, { width: 160, height: 160 });
                    qrRendered = true;
                }
            } else {
                qrCard.classList.add('d-none');
                btnToggleQr.classList.remove('active');
            }
        });
    }
});
</script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
