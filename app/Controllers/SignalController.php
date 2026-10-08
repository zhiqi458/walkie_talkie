<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Helpers;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\RateLimiter;
use App\Services\RoomService;

final class SignalController
{
    public function __construct(
        private readonly RoomService $rooms = new RoomService(),
        private readonly RateLimiter $rateLimiter = new RateLimiter()
    ) {
    }

    public function handle(Request $request): void
    {
        $session = $this->sessionPayload();
        if ($session === []) {
            Response::json(['ok' => false, 'error' => 'session_expired'], 401);
        }

        if (!$this->rateLimiter->allow(
            'signal_' . $session['peer_id'],
            (int) Helpers::config('signal_rate_limit_max', 20),
            (int) Helpers::config('signal_rate_limit_window', 10)
        )) {
            Response::json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $body = $request->json();
        $type = (string) ($body['type'] ?? '');
        $token = (string) ($body['token'] ?? Session::get('signal_token', ''));

        if (!$this->rooms->validateSessionToken($token, $session)) {
            Response::json(['ok' => false, 'error' => 'invalid_token'], 403);
        }

        $result = match ($type) {
            'hello' => $this->rooms->heartbeat($session),
            'poll' => $this->rooms->poll($session, (int) ($body['since'] ?? 0)),
            'ptt_request' => $this->rooms->requestFloor($session),
            'ptt_release' => $this->rooms->releaseFloor($session),
            'signal' => $this->rooms->relaySignal($session, $body),
            'leave' => $this->rooms->leave($session),
            default => ['ok' => false, 'error' => 'unknown_signal'],
        };

        Response::json($result);
    }

    private function sessionPayload(): array
    {
        $channel = (string) Session::get('channel', '');
        $peerId = (string) Session::get('peer_id', '');
        $nickname = (string) Session::get('nickname', '');
        $guestId = (string) Session::get('guest_id', '');

        if ($channel === '' || $peerId === '' || $nickname === '') {
            return [];
        }

        return [
            'channel' => $channel,
            'peer_id' => $peerId,
            'nickname' => $nickname,
            'guest_id' => $guestId,
        ];
    }
}
