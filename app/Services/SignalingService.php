<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;

/**
 * Class SignalingService
 * Core coordinator for WebRTC signaling and Push-to-Talk message exchanges.
 */
class SignalingService
{
    private RoomService $roomService;
    private TokenService $tokenService;
    private int $maxDataBytes;

    public function __construct()
    {
        $this->roomService = new RoomService();
        $this->tokenService = new TokenService();
        $this->maxDataBytes = (int) App::getConfig('max_data', 12288);
    }

    /**
     * Handle incoming signaling message dispatch
     */
    public function handle(array $payload): array
    {
        $type = (string) ($payload['type'] ?? '');
        $token = (string) ($payload['token'] ?? '');

        if ($token === '') {
            return ['status' => 'error', 'message' => 'Missing authentication token'];
        }

        $session = $this->tokenService->verifyPeerToken($token);
        if (!$session) {
            return ['status' => 'error', 'message' => 'The communication session has expired or is invalid.'];
        }

        $peerId = $session['peer_id'];
        $nickname = $session['nickname'];
        $channel = $session['channel'];

        return match ($type) {
            'hello' => $this->handleHello($channel, $peerId, $nickname),
            'poll' => $this->handlePoll($channel, $peerId, (int) ($payload['seq'] ?? 0)),
            'ptt_request' => $this->handlePttRequest($channel, $peerId, $nickname),
            'ptt_release' => $this->handlePttRelease($channel, $peerId),
            'signal' => $this->handleSignal($channel, $peerId, $payload),
            'leave' => $this->handleLeave($channel, $peerId),
            default => ['status' => 'error', 'message' => "Unknown signal type: {$type}"]
        };
    }

    private function handleHello(string $channel, string $peerId, string $nickname): array
    {
        $joinResult = $this->roomService->joinPeer($channel, $peerId, $nickname);
        if (!$joinResult['success']) {
            return [
                'status' => 'error',
                'message' => $joinResult['error'] ?? 'Unable to join channel',
            ];
        }

        $room = $joinResult['room'];
        $iceServers = App::getConfig('ice_servers', []);
        $floorTimeoutMs = App::getConfig('floor_timeout_ms', 30000);

        return [
            'status' => 'ok',
            'type' => 'hello_ack',
            'self' => [
                'peer_id' => $peerId,
                'nickname' => $nickname,
                'channel' => $channel,
            ],
            'peers' => array_values($room['peers']),
            'speaker' => $room['speaker'],
            'sequence' => (int) ($room['sequence'] ?? 0),
            'ice_servers' => $iceServers,
            'floor_timeout_ms' => $floorTimeoutMs,
        ];
    }

    private function handlePoll(string $channel, string $peerId, int $sinceSeq): array
    {
        $room = $this->roomService->updateHeartbeatAndGetRoom($channel, $peerId);

        // Fetch events since client sequence
        $events = $this->roomService->getPendingEvents($room, $peerId, $sinceSeq);

        return [
            'status' => 'ok',
            'type' => 'poll_ack',
            'events' => $events,
            'speaker' => $room['speaker'],
            'peers' => array_values($room['peers']),
            'sequence' => (int) ($room['sequence'] ?? 0),
        ];
    }

    private function handlePttRequest(string $channel, string $peerId, string $nickname): array
    {
        return $this->roomService->requestFloor($channel, $peerId, $nickname);
    }

    private function handlePttRelease(string $channel, string $peerId): array
    {
        return $this->roomService->releaseFloor($channel, $peerId);
    }

    private function handleSignal(string $channel, string $peerId, array $payload): array
    {
        $targetPeerId = (string) ($payload['target_peer_id'] ?? '');
        $data = $payload['data'] ?? null;

        if ($targetPeerId === '' || !is_array($data)) {
            return ['status' => 'error', 'message' => 'Invalid signal target or data'];
        }

        $jsonSize = strlen(json_encode($data) ?: '');
        if ($jsonSize > $this->maxDataBytes) {
            return ['status' => 'error', 'message' => 'Signal payload exceeds maximum allowed size'];
        }

        $this->roomService->dispatchSignal($channel, $peerId, $targetPeerId, $data);

        return ['status' => 'ok'];
    }

    private function handleLeave(string $channel, string $peerId): array
    {
        $this->roomService->leavePeer($channel, $peerId);
        return ['status' => 'ok', 'message' => 'Left channel'];
    }
}
