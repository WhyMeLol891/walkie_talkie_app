<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Helpers\SecurityHelper;
use App\Helpers\UrlHelper;
use App\Services\RoomService;
use App\Services\TokenService;

/**
 * Class HomeController
 * Handles landing/join page and joining/leaving channels.
 */
class HomeController
{
    private RoomService $roomService;
    private TokenService $tokenService;

    public function __construct()
    {
        $this->roomService = new RoomService();
        $this->tokenService = new TokenService();
    }

    /**
     * Display Join Landing Page
     */
    public function index(Request $request): void
    {
        // Check if user is already in an active session
        $currentUser = Session::get('walkie_user');
        $activeChannel = $currentUser['channel'] ?? null;

        // Auto-fill channel if passed via query param (e.g. ?c=security or ?channel=team-a)
        $prefillChannel = $request->input('c') ?? $request->input('channel') ?? ($currentUser['channel'] ?? '');
        $prefillNickname = $currentUser['nickname'] ?? '';

        $error = Session::getFlash('error');
        $success = Session::getFlash('success');

        $appUrl = UrlHelper::fullUrl();

        View::render('join', [
            'appName' => App::getConfig('name', 'Walkie Talkie PWA'),
            'csrfToken' => Session::csrfToken(),
            'prefillChannel' => SecurityHelper::sanitizeChannel((string) $prefillChannel),
            'prefillNickname' => SecurityHelper::sanitizeNickname((string) $prefillNickname),
            'appUrl' => $appUrl,
            'error' => $error,
            'success' => $success,
            'maxPeers' => App::getConfig('max_peers', 8),
        ]);
    }

    /**
     * Process Join Form Submission
     */
    public function join(Request $request): void
    {
        $rawNickname = (string) $request->input('nickname', '');
        $rawChannel = (string) $request->input('channel', '');

        $nickname = SecurityHelper::sanitizeNickname($rawNickname);
        $channel = SecurityHelper::sanitizeChannel($rawChannel);

        if ($nickname === '') {
            Session::flash('error', 'Please enter a valid nickname (1-24 characters).');
            Response::redirect(UrlHelper::url());
            return;
        }

        if ($channel === '') {
            Session::flash('error', 'Please enter a valid channel name (e.g. team-alpha).');
            Response::redirect(UrlHelper::url());
            return;
        }

        // Check if room is full
        $room = $this->roomService->readRoom($channel);
        $maxPeers = (int) App::getConfig('max_peers', 8);

        // Check if existing peer session matches
        $existing = Session::get('walkie_user');
        $peerId = ($existing && $existing['channel'] === $channel)
            ? $existing['peer_id']
            : SecurityHelper::generatePeerId();

        if (!isset($room['peers'][$peerId]) && count($room['peers']) >= $maxPeers) {
            Session::flash('error', "This channel is currently full. Maximum {$maxPeers} users are allowed.");
            Response::redirect(UrlHelper::url());
            return;
        }

        // Generate HMAC-SHA256 authenticated token
        $token = $this->tokenService->generatePeerToken($peerId, $nickname, $channel);

        // Save session
        Session::set('walkie_user', [
            'peer_id' => $peerId,
            'nickname' => $nickname,
            'channel' => $channel,
            'token' => $token,
            'joined_at' => time(),
        ]);

        Response::redirect(UrlHelper::url('channel'));
    }

    /**
     * Leave channel and return to join page
     */
    public function leave(Request $request): void
    {
        $user = Session::get('walkie_user');
        if ($user) {
            $this->roomService->leavePeer($user['channel'], $user['peer_id']);
            Session::remove('walkie_user');
        }

        Session::flash('success', 'You have left the channel.');
        Response::redirect(UrlHelper::url());
    }
}
