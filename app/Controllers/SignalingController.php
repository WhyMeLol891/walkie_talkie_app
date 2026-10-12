<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Services\SignalingService;

/**
 * Class SignalingController
 * API Endpoint for WebRTC mesh coordination and floor exchange.
 */
class SignalingController
{
    private SignalingService $signalingService;
    private int $maxBodyBytes;

    public function __construct()
    {
        $this->signalingService = new SignalingService();
        $this->maxBodyBytes = (int) App::getConfig('max_body', 32768);
    }

    /**
     * POST /api/signal
     */
    public function handle(Request $request): void
    {
        if ($request->getBodySize() > $this->maxBodyBytes) {
            Response::json(['error' => 'Payload exceeds maximum allowed size.'], 413);
            return;
        }

        $payload = $request->getJson();
        if (empty($payload)) {
            // Check form post fallback
            $payload = $request->getPost();
        }

        if (empty($payload) || !isset($payload['type'])) {
            Response::json(['error' => 'Invalid signaling request payload.'], 400);
            return;
        }

        $result = $this->signalingService->handle($payload);

        $status = ($result['status'] ?? '') === 'error' ? 400 : 200;
        Response::json($result, $status);
    }
}
