<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;

/**
 * Class RoomService
 * Manages virtual walkie-talkie channels, active peers, atomic file locks,
 * communication floor control, and signaling event queues.
 */
class RoomService
{
    private string $storageDir;
    private int $maxPeers;
    private int $floorTimeoutMs;
    private int $peerHeartbeatTimeoutSec;

    public function __construct()
    {
        $this->storageDir = (string) (App::getConfig('paths')['rooms'] ?? (dirname(__DIR__, 2) . '/storage/rooms'));
        $this->maxPeers = (int) App::getConfig('max_peers', 8);
        $this->floorTimeoutMs = (int) App::getConfig('floor_timeout_ms', 30000);
        $this->peerHeartbeatTimeoutSec = (int) App::getConfig('peer_heartbeat_timeout_sec', 12);

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
    }

    /**
     * Get path to room storage JSON file
     */
    private function getRoomFilePath(string $channel): string
    {
        return $this->storageDir . '/' . preg_replace('/[^a-z0-9_-]/', '', strtolower($channel)) . '.json';
    }

    /**
     * Atomically access and mutate room data under an exclusive lock
     *
     * @param string $channel
     * @param callable(array $room): array $callback Must return modified room array
     * @return array The updated room array
     */
    public function mutateRoom(string $channel, callable $callback): array
    {
        $filePath = $this->getRoomFilePath($channel);
        $fp = fopen($filePath, 'c+');

        if (!$fp) {
            throw new \RuntimeException("Unable to open room file for channel: {$channel}");
        }

        // Acquire exclusive lock
        flock($fp, LOCK_EX);

        try {
            $raw = stream_get_contents($fp);
            $room = null;

            if ($raw !== false && trim($raw) !== '') {
                $room = json_decode($raw, true);
            }

            if (!is_array($room)) {
                $room = $this->createDefaultRoomStructure($channel);
            }

            // Always check floor timeout and prune dead peers on any mutation
            $room = $this->maintainRoomState($room);

            // Execute custom mutation logic
            $room = $callback($room);

            // Save back
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($room, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $room;
    }

    /**
     * Read room data safely under a shared lock
     */
    public function readRoom(string $channel): array
    {
        $filePath = $this->getRoomFilePath($channel);
        if (!file_exists($filePath)) {
            return $this->createDefaultRoomStructure($channel);
        }

        $fp = fopen($filePath, 'r');
        if (!$fp) {
            return $this->createDefaultRoomStructure($channel);
        }

        flock($fp, LOCK_SH);
        try {
            $raw = stream_get_contents($fp);
            $room = !empty($raw) ? json_decode($raw, true) : null;
            if (!is_array($room)) {
                $room = $this->createDefaultRoomStructure($channel);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $this->maintainRoomState($room);
    }

    /**
     * Initial room template
     */
    private function createDefaultRoomStructure(string $channel): array
    {
        return [
            'channel' => $channel,
            'created_at' => time(),
            'updated_at' => time(),
            'speaker' => null, // ['peer_id' => string, 'nickname' => string, 'started_at' => float, 'expires_at' => float]
            'peers' => [],    // [peer_id => ['peer_id' => ..., 'nickname' => ..., 'joined_at' => ..., 'last_seen' => ...]]
            'sequence' => 0,
            'events' => [],
        ];
    }

    /**
     * Clean up timed-out peers and auto-expire floor speaking limit
     */
    private function maintainRoomState(array $room): array
    {
        $now = microtime(true);
        $intNow = time();
        $changed = false;

        // 1. Check speaker floor timeout
        if (!empty($room['speaker'])) {
            $speakerExp = (float) ($room['speaker']['expires_at'] ?? 0);
            if ($now >= $speakerExp) {
                // Speaking floor expired
                $expiredSpeaker = $room['speaker'];
                $room['speaker'] = null;
                $changed = true;

                $room = $this->pushEvent($room, [
                    'type' => 'speaker_stopped',
                    'peer_id' => $expiredSpeaker['peer_id'],
                    'nickname' => $expiredSpeaker['nickname'],
                    'reason' => 'timeout',
                ]);
            }
        }

        // 2. Prune peers who missed heartbeats
        $activePeers = [];
        $cutoff = $intNow - $this->peerHeartbeatTimeoutSec;

        foreach ($room['peers'] as $peerId => $peer) {
            $lastSeen = (int) ($peer['last_seen'] ?? 0);
            if ($lastSeen >= $cutoff) {
                $activePeers[$peerId] = $peer;
            } else {
                // Peer timed out
                $changed = true;
                // If timed-out peer was the speaker, release the floor!
                if (!empty($room['speaker']) && $room['speaker']['peer_id'] === $peerId) {
                    $room['speaker'] = null;
                    $room = $this->pushEvent($room, [
                        'type' => 'speaker_stopped',
                        'peer_id' => $peerId,
                        'nickname' => $peer['nickname'],
                        'reason' => 'disconnected',
                    ]);
                }

                $room = $this->pushEvent($room, [
                    'type' => 'peer_left',
                    'peer_id' => $peerId,
                    'nickname' => $peer['nickname'],
                    'reason' => 'timeout',
                ]);
            }
        }

        $room['peers'] = $activePeers;

        // 3. Trim event queue (keep events from last 45 seconds, max 60 events)
        if (count($room['events']) > 60) {
            $eventCutoff = $intNow - 45;
            $room['events'] = array_values(array_filter($room['events'], function ($ev) use ($eventCutoff) {
                return ($ev['timestamp'] ?? 0) >= $eventCutoff;
            }));
        }

        if ($changed) {
            $room['updated_at'] = $intNow;
        }

        return $room;
    }

    /**
     * Add event to room queue with auto-incrementing sequence number
     */
    public function pushEvent(array $room, array $eventData, ?string $targetPeerId = null): array
    {
        $room['sequence'] = (int) ($room['sequence'] ?? 0) + 1;
        $event = array_merge($eventData, [
            'seq' => $room['sequence'],
            'target' => $targetPeerId, // null = broadcast to all peers, or specific peer_id
            'timestamp' => time(),
        ]);

        $room['events'][] = $event;
        return $room;
    }

    /**
     * Register or update peer presence ("hello")
     */
    public function joinPeer(string $channel, string $peerId, string $nickname): array
    {
        $result = ['success' => false, 'error' => null, 'room' => null];

        $this->mutateRoom($channel, function (array $room) use ($peerId, $nickname, &$result) {
            // Check peer limit
            $isNew = !isset($room['peers'][$peerId]);
            if ($isNew && count($room['peers']) >= $this->maxPeers) {
                $result['error'] = "This channel is currently full. Maximum {$this->maxPeers} users are allowed.";
                return $room;
            }

            $room['peers'][$peerId] = [
                'peer_id' => $peerId,
                'nickname' => $nickname,
                'joined_at' => $isNew ? time() : ($room['peers'][$peerId]['joined_at'] ?? time()),
                'last_seen' => time(),
            ];

            if ($isNew) {
                $room = $this->pushEvent($room, [
                    'type' => 'peer_joined',
                    'peer_id' => $peerId,
                    'nickname' => $nickname,
                ]);
            }

            $result['success'] = true;
            $result['room'] = $room;
            return $room;
        });

        return $result;
    }

    /**
     * Heartbeat / polling update and return updated room in one atomic lock
     */
    public function updateHeartbeatAndGetRoom(string $channel, string $peerId): array
    {
        return $this->mutateRoom($channel, function (array $room) use ($peerId) {
            if (isset($room['peers'][$peerId])) {
                $room['peers'][$peerId]['last_seen'] = time();
            }
            return $room;
        });
    }

    /**
     * Heartbeat / polling update
     */
    public function updatePeerHeartbeat(string $channel, string $peerId): void
    {
        $this->updateHeartbeatAndGetRoom($channel, $peerId);
    }

    /**
     * Peer explicitly leaving room
     */
    public function leavePeer(string $channel, string $peerId): void
    {
        $this->mutateRoom($channel, function (array $room) use ($peerId) {
            if (isset($room['peers'][$peerId])) {
                $nickname = $room['peers'][$peerId]['nickname'];
                unset($room['peers'][$peerId]);

                if (!empty($room['speaker']) && $room['speaker']['peer_id'] === $peerId) {
                    $room['speaker'] = null;
                    $room = $this->pushEvent($room, [
                        'type' => 'speaker_stopped',
                        'peer_id' => $peerId,
                        'nickname' => $nickname,
                        'reason' => 'left',
                    ]);
                }

                $room = $this->pushEvent($room, [
                    'type' => 'peer_left',
                    'peer_id' => $peerId,
                    'nickname' => $nickname,
                ]);
            }

            return $room;
        });
    }

    /**
     * Request Push-to-Talk communication floor (Atomic floor grant)
     */
    public function requestFloor(string $channel, string $peerId, string $nickname): array
    {
        $response = ['status' => 'denied', 'speaker' => null, 'remaining_ms' => 0];

        $this->mutateRoom($channel, function (array $room) use ($peerId, $nickname, &$response) {
            $now = microtime(true);

            // Check if someone else is currently speaking
            if (!empty($room['speaker'])) {
                $currentSpeaker = $room['speaker'];
                $speakerExp = (float) ($currentSpeaker['expires_at'] ?? 0);

                if ($now < $speakerExp && $currentSpeaker['peer_id'] !== $peerId) {
                    // Floor is occupied by someone else!
                    $response = [
                        'status' => 'denied',
                        'reason' => 'CHANNEL_BUSY',
                        'speaker' => $currentSpeaker['nickname'],
                        'speaker_id' => $currentSpeaker['peer_id'],
                        'remaining_ms' => max(0, (int) (($speakerExp - $now) * 1000)),
                    ];
                    return $room;
                }
            }

            // Floor is free or previous speaker expired: Grant floor to this peer!
            $durationSec = $this->floorTimeoutMs / 1000.0;
            $expiresAt = $now + $durationSec;

            $room['speaker'] = [
                'peer_id' => $peerId,
                'nickname' => $nickname,
                'started_at' => $now,
                'expires_at' => $expiresAt,
            ];

            $room = $this->pushEvent($room, [
                'type' => 'speaker_started',
                'peer_id' => $peerId,
                'nickname' => $nickname,
                'timeout_ms' => $this->floorTimeoutMs,
            ]);

            $response = [
                'status' => 'granted',
                'speaker' => $nickname,
                'speaker_id' => $peerId,
                'timeout_ms' => $this->floorTimeoutMs,
            ];

            return $room;
        });

        return $response;
    }

    /**
     * Release Push-to-Talk communication floor
     */
    public function releaseFloor(string $channel, string $peerId): array
    {
        $response = ['status' => 'released'];

        $this->mutateRoom($channel, function (array $room) use ($peerId, &$response) {
            if (!empty($room['speaker']) && $room['speaker']['peer_id'] === $peerId) {
                $speaker = $room['speaker'];
                $room['speaker'] = null;

                $room = $this->pushEvent($room, [
                    'type' => 'speaker_stopped',
                    'peer_id' => $peerId,
                    'nickname' => $speaker['nickname'],
                    'reason' => 'released',
                ]);
            }
            return $room;
        });

        return $response;
    }

    /**
     * Send WebRTC signaling payload (offer, answer, candidate) to a peer
     */
    public function dispatchSignal(string $channel, string $fromPeerId, string $targetPeerId, array $data): void
    {
        $this->mutateRoom($channel, function (array $room) use ($fromPeerId, $targetPeerId, $data) {
            return $this->pushEvent($room, [
                'type' => 'signal',
                'from_peer_id' => $fromPeerId,
                'data' => $data,
            ], $targetPeerId);
        });
    }

    /**
     * Retrieve events newer than $sinceSeq that are targeted to $peerId or broadcast
     */
    public function getPendingEvents(array $room, string $peerId, int $sinceSeq): array
    {
        $events = [];
        foreach ($room['events'] as $ev) {
            $seq = (int) ($ev['seq'] ?? 0);
            if ($seq <= $sinceSeq) {
                continue;
            }

            // Target check: either broadcast ($ev['target'] === null) or specifically for this peer
            $target = $ev['target'] ?? null;
            if ($target === null || $target === $peerId) {
                // Avoid sending own signal events back to self
                if (($ev['from_peer_id'] ?? null) === $peerId && ($ev['type'] ?? '') === 'signal') {
                    continue;
                }
                $events[] = $ev;
            }
        }
        return $events;
    }
}
