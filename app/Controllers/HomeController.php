<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Helpers;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\RoomService;

final class HomeController
{
    public function __construct(private readonly RoomService $rooms = new RoomService())
    {
    }

    public function showJoin(Request $request): void
    {
        if (Session::get('peer_id') && Session::get('channel')) {
            Response::redirect('/channel');
        }

        View::render('join', [
            'title' => 'Join Channel',
            'csrfToken' => Csrf::token(),
            'appName' => Helpers::config('name', 'Walkie Talkie'),
            'publicUrl' => Helpers::absoluteUrl('/'),
        ]);
    }

    public function join(Request $request): void
    {
        if (!Csrf::verify($request->input('_csrf'))) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Forbidden']);
            return;
        }

        $nickname = $this->rooms->normalizeNickname((string) $request->input('nickname', ''));
        $channel = $this->rooms->normalizeChannel((string) $request->input('channel', ''));

        if ($nickname === '' || $channel === '') {
            Session::set('join_error', 'Please provide both a nickname and a channel name.');
            Response::redirect('/');
        }

        Session::set('nickname', $nickname);
        Session::set('channel', $channel);
        Session::set('guest_id', Session::get('guest_id', Helpers::randomId(6)));
        Session::set('peer_id', Session::get('peer_id', Helpers::randomId(8)));

        $session = [
            'nickname' => $nickname,
            'channel' => $channel,
            'guest_id' => (string) Session::get('guest_id'),
            'peer_id' => (string) Session::get('peer_id'),
        ];

        Session::set('signal_token', $this->rooms->createToken($session));
        $joinResult = $this->rooms->join($session);
        if (($joinResult['ok'] ?? false) !== true) {
            Session::set('join_error', $joinResult['reason'] === 'room_full'
                ? 'This channel is currently full. Maximum users reached.'
                : 'Unable to join the channel.');
            Response::redirect('/');
        }

        Response::redirect('/channel');
    }

    public function showChannel(Request $request): void
    {
        $this->requireSession();

        $session = $this->sessionPayload();
        $state = $this->rooms->heartbeat($session);

        View::render('channel', [
            'title' => 'Channel',
            'appName' => Helpers::config('name', 'Walkie Talkie'),
            'session' => $session,
            'state' => $state,
            'csrfToken' => Csrf::token(),
            'signalTimeout' => (int) Helpers::config('signal_floor_timeout_ms', 30000),
            'stunServers' => Helpers::config('stun_servers', []),
            'publicUrl' => Helpers::absoluteUrl('/'),
        ]);
    }

    public function leave(Request $request): void
    {
        if (!Csrf::verify($request->input('_csrf'))) {
            Response::redirect('/');
        }

        if (Session::get('channel') && Session::get('peer_id')) {
            $this->rooms->leave($this->sessionPayload());
        }

        Session::clear();
        Response::redirect('/');
    }

    private function requireSession(): void
    {
        if (!Session::get('peer_id') || !Session::get('channel')) {
            Response::redirect('/');
        }
    }

    private function sessionPayload(): array
    {
        return [
            'nickname' => (string) Session::get('nickname', 'Guest'),
            'channel' => (string) Session::get('channel', ''),
            'guest_id' => (string) Session::get('guest_id', ''),
            'peer_id' => (string) Session::get('peer_id', ''),
            'signal_token' => (string) Session::get('signal_token', ''),
        ];
    }
}
