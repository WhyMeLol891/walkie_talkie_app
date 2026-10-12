<?php
declare(strict_types=1);

use App\Helpers\UrlHelper;
use App\Helpers\SecurityHelper;

$pageTitle = "#" . strtoupper($user['channel']) . " | Walkie Talkie";
require __DIR__ . '/layouts/header.php';
?>

<div class="wt-container wt-channel-wrapper">
    <div class="container py-2 py-md-4 min-vh-100 d-flex flex-column justify-content-between">
        
        <!-- Main Tactical Radio Handset Box -->
        <div class="row justify-content-center flex-grow-1">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5 d-flex flex-column">
                
                <div class="card wt-radio-chassis wt-handset border-0 shadow-2xl flex-grow-1 d-flex flex-column">
                    
                    <!-- Top Radio Antenna & Bezel -->
                    <div class="wt-radio-top-bar d-flex justify-content-between align-items-center px-4 pt-3 pb-2">
                        <div class="wt-antenna-mount">
                            <span class="wt-antenna-rod"></span>
                            <span class="wt-antenna-tip"></span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span id="statusBarLed" class="wt-led-dot wt-led-amber pulse" title="Connection Status"></span>
                            <span id="connectionBadge" class="wt-badge-status wt-status-connecting">CONNECTING</span>
                        </div>
                    </div>

                    <!-- Radio Main Body -->
                    <div class="card-body p-3 p-sm-4 d-flex flex-column justify-content-between flex-grow-1">

                        <!-- Digital LCD Header -->
                        <div class="wt-lcd-screen p-3 rounded-3 mb-3 shadow-inner">
                            <div class="d-flex justify-content-between align-items-center border-bottom border-dark border-opacity-50 pb-2 mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-walkie-talkie text-success"></i>
                                    <span class="wt-lcd-brand"><?= SecurityHelper::e($appName) ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="wt-lcd-dim" id="roomUserCount">1 USER</span>
                                    <span class="wt-lcd-separator">|</span>
                                    <span class="wt-lcd-dim" id="micStatusText">MIC OFF</span>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-baseline">
                                <div>
                                    <div class="wt-lcd-label text-uppercase">ACTIVE CHANNEL</div>
                                    <div class="wt-lcd-channel text-uppercase" id="channelNameDisplay">
                                        #<?= SecurityHelper::e($user['channel']) ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="wt-lcd-label text-uppercase">OPERATOR</div>
                                    <div class="wt-lcd-callsign fw-bold" id="callsignDisplay">
                                        <?= SecurityHelper::e($user['nickname']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Live Speaker Banner & Timer Bar -->
                        <div class="wt-speaker-banner mb-3 text-center p-3 rounded-3" id="speakerBanner">
                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1" id="speakerStatusContainer">
                                <span class="wt-speaker-icon-wrapper" id="speakerIcon">
                                    <i class="fa-solid fa-radio text-secondary"></i>
                                </span>
                                <span class="wt-speaker-text fw-bold" id="speakerText">Waiting for a speaker</span>
                            </div>
                            <!-- 30s Countdown Bar -->
                            <div class="progress wt-floor-progress d-none" id="floorProgressBarContainer" style="height: 4px;">
                                <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" id="floorProgressBar" role="progressbar" style="width: 100%;"></div>
                            </div>
                            <small class="wt-floor-countdown text-warning small d-none" id="floorCountdownText">30s remaining</small>
                        </div>

                        <!-- Audio Level VU Meter / Canvas Visualizer -->
                        <div class="wt-visualizer-container mb-3 text-center">
                            <div class="d-flex justify-content-between align-items-center px-1 mb-1">
                                <span class="small wt-lcd-sub">VU METER</span>
                                <span class="small wt-lcd-sub" id="vuLevelDisplay">-42 dB</span>
                            </div>
                            <canvas id="audioVisualizerCanvas" class="wt-vu-canvas w-100 rounded-2" height="60"></canvas>
                        </div>

                        <!-- Big Push-To-Talk Circular Button Section -->
                        <div class="wt-ptt-section my-auto text-center py-2">
                            <div class="wt-ptt-outer-ring mx-auto" id="pttRing">
                                <button type="button" 
                                        id="pttButton" 
                                        class="wt-ptt-btn" 
                                        aria-label="Push to Talk" 
                                        aria-pressed="false"
                                        role="button"
                                        tabindex="0">
                                    <div class="wt-ptt-inner-content">
                                        <div class="wt-ptt-icon-box mb-1">
                                            <i class="fa-solid fa-microphone fa-2x" id="pttIcon"></i>
                                        </div>
                                        <div class="wt-ptt-action-label" id="pttLabel">HOLD TO TALK</div>
                                        <div class="wt-ptt-hint small">SPACEBAR</div>
                                    </div>
                                </button>
                            </div>
                            <div class="wt-ptt-feedback-hint mt-2 small text-secondary" id="pttHintText">
                                Press & hold button or Spacebar to transmit
                            </div>
                        </div>

                        <!-- Microphone Permission Alert Banner (Hidden by default) -->
                        <div id="micAlertBanner" class="alert alert-warning alert-dismissible fade show d-none py-2 px-3 small rounded-3 mt-2" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <span id="micAlertText">Microphone permission is required to talk. You can still listen.</span>
                            <button type="button" class="btn-close btn-close-white p-2" id="btnCloseMicAlert" aria-label="Close"></button>
                        </div>

                        <!-- Participant List Drawer / Card -->
                        <div class="wt-participants-panel mt-3 p-3 rounded-3">
                            <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary border-opacity-25 pb-2">
                                <span class="wt-panel-title text-uppercase">
                                    <i class="fa-solid fa-users me-1 text-info"></i> On This Channel
                                </span>
                                <span class="badge wt-badge-count" id="participantCountBadge">1 / <?= (int) $maxPeers ?></span>
                            </div>

                            <ul class="list-unstyled mb-0 wt-participant-list" id="participantList">
                                <!-- Self User Item (Initial) -->
                                <li class="wt-participant-item d-flex align-items-center justify-content-between p-2 rounded-2 mb-1" id="peer-<?= SecurityHelper::e($user['peer_id']) ?>">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="wt-status-dot wt-dot-online"></span>
                                        <span class="fw-semibold text-light"><?= SecurityHelper::e($user['nickname']) ?></span>
                                        <span class="badge bg-secondary text-uppercase wt-badge-you">YOU</span>
                                    </div>
                                    <span class="wt-peer-state text-success small" id="selfStateBadge">READY</span>
                                </li>
                            </ul>
                        </div>

                    </div>

                    <!-- Bottom Controls & Hardware Deck -->
                    <div class="wt-bottom-deck p-3 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <!-- Roger Beep / Sound Effects Toggle -->
                            <button type="button" class="btn btn-sm btn-outline-secondary wt-deck-btn" id="btnToggleAudioFx" title="Roger Beep Sound Effects">
                                <i class="fa-solid fa-volume-high" id="audioFxIcon"></i>
                            </button>
                            <!-- Audio Output Mute Toggle -->
                            <button type="button" class="btn btn-sm btn-outline-secondary wt-deck-btn" id="btnToggleMute" title="Mute Incoming Audio">
                                <i class="fa-solid fa-bell" id="muteIcon"></i>
                            </button>
                        </div>

                        <!-- Leave Channel Button -->
                        <form action="<?= UrlHelper::url('leave') ?>" method="POST" id="leaveForm" class="m-0">
                            <input type="hidden" name="_csrf_token" value="<?= SecurityHelper::e($csrfToken) ?>">
                            <button type="submit" class="btn btn-sm wt-btn-leave px-3 fw-semibold text-uppercase d-flex align-items-center gap-2" id="btnLeaveChannel">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                <span>Leave Channel</span>
                            </button>
                        </form>
                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

<!-- Embedded Session Configuration (Passes sanitized secure parameters to client JS) -->
<script>
window.WALKIE_CONFIG = {
    peerId: <?= json_encode($user['peer_id']) ?>,
    nickname: <?= json_encode($user['nickname']) ?>,
    channel: <?= json_encode($user['channel']) ?>,
    token: <?= json_encode($user['token']) ?>,
    signalUrl: <?= json_encode($signalUrl) ?>,
    leaveUrl: <?= json_encode($leaveUrl) ?>,
    homeUrl: <?= json_encode($homeUrl) ?>,
    floorTimeoutMs: <?= (int) $floorTimeoutMs ?>,
    maxPeers: <?= (int) $maxPeers ?>,
    iceServers: <?= json_encode($iceServers) ?>
};
</script>

<!-- Dedicated Modular Scripts -->
<script src="<?= UrlHelper::asset('js/audio-level.js') ?>?v=1.0.1"></script>
<script src="<?= UrlHelper::asset('js/webrtc.js') ?>?v=1.0.1"></script>
<script src="<?= UrlHelper::asset('js/signaling.js') ?>?v=1.0.1"></script>
<script src="<?= UrlHelper::asset('js/ptt.js') ?>?v=1.0.1"></script>
<script src="<?= UrlHelper::asset('js/app.js') ?>?v=1.0.1"></script>

<?php require __DIR__ . '/layouts/footer.php'; ?>
