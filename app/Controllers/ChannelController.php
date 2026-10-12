<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Helpers\UrlHelper;
use App\Services\RoomService;

/**
 * Class ChannelController
 * Handles Walkie-Talkie Channel UI rendering and session verification.
 */
class ChannelController
{
    private RoomService $roomService;

    public function __construct()
    {
        $this->roomService = new RoomService();
    }

    /**
     * Display Channel Walkie-Talkie interface
     */
    public function index(Request $request): void
    {
        $user = Session::get('walkie_user');

        if (!$user || empty($user['peer_id']) || empty($user['channel']) || empty($user['token'])) {
            Session::flash('error', 'Please enter your nickname and channel name first.');
            Response::redirect(UrlHelper::url());
            return;
        }

        // Room state check
        $room = $this->roomService->readRoom($user['channel']);
        $maxPeers = (int) App::getConfig('max_peers', 8);

        View::render('channel', [
            'appName' => App::getConfig('name', 'Walkie Talkie PWA'),
            'user' => $user,
            'csrfToken' => Session::csrfToken(),
            'maxPeers' => $maxPeers,
            'floorTimeoutMs' => (int) App::getConfig('floor_timeout_ms', 30000),
            'iceServers' => App::getConfig('ice_servers', []),
            'signalUrl' => UrlHelper::url('api/signal'),
            'leaveUrl' => UrlHelper::url('leave'),
            'homeUrl' => UrlHelper::url(),
        ]);
    }
}
