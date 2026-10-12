<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for Walkie-Talkie System
 * Validates Tokens, Room State, Concurrency, Floor Control, and Signaling
 */

require_once __DIR__ . '/../app/Core/App.php';

use App\Core\App;
use App\Core\Env;
use App\Services\TokenService;
use App\Services\RoomService;
use App\Services\SignalingService;
use App\Helpers\SecurityHelper;

App::boot();

echo "=== STARTING AUTOMATED TEST SUITE ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $condition, string $testName): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] {$testName}\n";
        $passCount++;
    } else {
        echo "[FAIL] {$testName}\n";
        $failCount++;
    }
}

// -----------------------------------------------------------------------------
// Test 1: Nickname & Channel Sanitization
// -----------------------------------------------------------------------------
$cleanNick = SecurityHelper::sanitizeNickname("  <script>alert(1)</script>Super_Pilot 01  ");
assertCondition($cleanNick === "Super_Pilot 01", "Nickname Sanitization (strips tags, trims spaces)");

$cleanChannel = SecurityHelper::sanitizeChannel("  Team Alpha #101 / Secret! ");
assertCondition($cleanChannel === "team-alpha-101-secret", "Channel Sanitization (slugified lowercase)");

// -----------------------------------------------------------------------------
// Test 2: Token Service Signing & Verification
// -----------------------------------------------------------------------------
$tokenService = new TokenService();
$token = $tokenService->generatePeerToken('peer_alpha', 'Alpha', 'general', 3600);
$verified = $tokenService->verifyPeerToken($token);

assertCondition(
    is_array($verified) && $verified['peer_id'] === 'peer_alpha' && $verified['nickname'] === 'Alpha' && $verified['channel'] === 'general',
    "Token Generation & HMAC-SHA256 Verification"
);

$invalidToken = $token . 'tampered';
$tamperedResult = $tokenService->verifyPeerToken($invalidToken);
assertCondition($tamperedResult === null, "Tampered Token Rejected");

// -----------------------------------------------------------------------------
// Test 3: User A joins channel
// -----------------------------------------------------------------------------
$roomService = new RoomService();
$signalingService = new SignalingService();
$testChannel = 'test-room-' . bin2hex(random_bytes(3));

$tokenA = $tokenService->generatePeerToken('peer_a', 'Alice', $testChannel, 3600);
$helloA = $signalingService->handle([
    'type' => 'hello',
    'token' => $tokenA
]);

assertCondition(
    $helloA['status'] === 'ok' && count($helloA['peers']) === 1 && $helloA['peers'][0]['nickname'] === 'Alice',
    "User A joins room (1 User Online)"
);

// -----------------------------------------------------------------------------
// Test 4: User B joins channel
// -----------------------------------------------------------------------------
$tokenB = $tokenService->generatePeerToken('peer_b', 'Bob', $testChannel, 3600);
$helloB = $signalingService->handle([
    'type' => 'hello',
    'token' => $tokenB
]);

assertCondition(
    $helloB['status'] === 'ok' && count($helloB['peers']) === 2,
    "User B joins room (2 Users Online)"
);

// -----------------------------------------------------------------------------
// Test 5: User A requests floor (PTT Press)
// -----------------------------------------------------------------------------
$pttReqA = $signalingService->handle([
    'type' => 'ptt_request',
    'token' => $tokenA
]);

assertCondition(
    $pttReqA['status'] === 'granted' && $pttReqA['speaker'] === 'Alice',
    "User A presses PTT -> Floor Granted (Alice transmitting)"
);

// -----------------------------------------------------------------------------
// Test 6: User B presses PTT while Alice is speaking -> CHANNEL BUSY
// -----------------------------------------------------------------------------
$pttReqB = $signalingService->handle([
    'type' => 'ptt_request',
    'token' => $tokenB
]);

assertCondition(
    $pttReqB['status'] === 'denied' && $pttReqB['reason'] === 'CHANNEL_BUSY' && $pttReqB['speaker'] === 'Alice',
    "User B presses PTT while A is speaking -> CHANNEL BUSY denied"
);

// -----------------------------------------------------------------------------
// Test 7: User A releases floor (PTT Release)
// -----------------------------------------------------------------------------
$pttRelA = $signalingService->handle([
    'type' => 'ptt_release',
    'token' => $tokenA
]);

assertCondition(
    $pttRelA['status'] === 'released',
    "User A releases PTT -> Floor Released"
);

// -----------------------------------------------------------------------------
// Test 8: User B can now request floor
// -----------------------------------------------------------------------------
$pttReqB2 = $signalingService->handle([
    'type' => 'ptt_request',
    'token' => $tokenB
]);

assertCondition(
    $pttReqB2['status'] === 'granted' && $pttReqB2['speaker'] === 'Bob',
    "User B presses PTT after A released -> Floor Granted (Bob transmitting)"
);

// User B releases
$signalingService->handle([
    'type' => 'ptt_release',
    'token' => $tokenB
]);

// -----------------------------------------------------------------------------
// Test 9: WebRTC Signal message dispatch
// -----------------------------------------------------------------------------
$signalResult = $signalingService->handle([
    'type' => 'signal',
    'token' => $tokenA,
    'target_peer_id' => 'peer_b',
    'data' => [
        'type' => 'offer',
        'sdp' => 'v=0\r\no=alice 123 456 IN IP4 127.0.0.1'
    ]
]);

assertCondition($signalResult['status'] === 'ok', "User A sends WebRTC signal offer to User B");

// User B polls for events
$pollB = $signalingService->handle([
    'type' => 'poll',
    'token' => $tokenB,
    'seq' => 0
]);

$receivedOffer = false;
foreach ($pollB['events'] as $ev) {
    if ($ev['type'] === 'signal' && ($ev['from_peer_id'] ?? '') === 'peer_a') {
        $receivedOffer = true;
        break;
    }
}
assertCondition($receivedOffer, "User B polls and receives User A's WebRTC offer");

// -----------------------------------------------------------------------------
// Test 10: User A leaves room
// -----------------------------------------------------------------------------
$leaveA = $signalingService->handle([
    'type' => 'leave',
    'token' => $tokenA
]);

assertCondition($leaveA['status'] === 'ok', "User A leaves channel");

$pollB2 = $signalingService->handle([
    'type' => 'poll',
    'token' => $tokenB,
    'seq' => $pollB['sequence']
]);

assertCondition(count($pollB2['peers']) === 1, "User B observes only 1 peer remaining");

// -----------------------------------------------------------------------------
// Test 11: Maximum Room Capacity Enforcement
// -----------------------------------------------------------------------------
$capChannel = 'cap-room-' . bin2hex(random_bytes(3));
for ($i = 1; $i <= 8; $i++) {
    $tok = $tokenService->generatePeerToken("peer_{$i}", "User_{$i}", $capChannel, 3600);
    $signalingService->handle(['type' => 'hello', 'token' => $tok]);
}
// 9th user attempts join
$tok9 = $tokenService->generatePeerToken("peer_9", "User_9", $capChannel, 3600);
$hello9 = $signalingService->handle(['type' => 'hello', 'token' => $tok9]);

assertCondition(
    $hello9['status'] === 'error' && str_contains($hello9['message'], 'full'),
    "Room capacity enforcement: 9th user denied with 'channel is full' message"
);

// Clean up test room files
@unlink($rootDir . "/storage/rooms/{$testChannel}.json");
@unlink($rootDir . "/storage/rooms/{$capChannel}.json");

echo "\n=== TEST SUMMARY: {$passCount} Passed, {$failCount} Failed ===\n";
exit($failCount === 0 ? 0 : 1);
