<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Helpers;

final class RoomService
{
    private const PEER_TTL = 15;
    private const EVENT_LIMIT = 200;

    public function __construct(
        private readonly TokenService $tokenService = new TokenService()
    ) {
    }

    public function normalizeChannel(string $channel): string
    {
        $channel = trim($channel);
        $channel = preg_replace('/[^\pL\pN\s\-_]+/u', '', $channel) ?? '';
        $channel = preg_replace('/\s+/', '-', $channel) ?? '';
        $channel = trim($channel, '-_');
        return mb_strtolower(mb_substr($channel, 0, 64));
    }

    public function normalizeNickname(string $nickname): string
    {
        $nickname = trim(preg_replace('/\s+/', ' ', $nickname) ?? '');
        $nickname = preg_replace('/[^\pL\pN\s\-_\.]+/u', '', $nickname) ?? '';
        return mb_substr($nickname, 0, 24);
    }

    public function roomPath(string $channel): string
    {
        return __DIR__ . '/../../storage/rooms/' . $this->normalizeChannel($channel) . '.json';
    }

    public function createToken(array $session): string
    {
        return $this->tokenService->issue([
            'peer_id' => $session['peer_id'],
            'guest_id' => $session['guest_id'],
            'nickname' => $session['nickname'],
            'channel' => $session['channel'],
            'exp' => time() + 3600,
        ]);
    }

    public function validateSessionToken(string $token, array $session): bool
    {
        $claims = $this->tokenService->validate($token);
        if ($claims === false) {
            return false;
        }

        return ($claims['peer_id'] ?? '') === ($session['peer_id'] ?? '')
            && ($claims['channel'] ?? '') === ($session['channel'] ?? '')
            && ($claims['nickname'] ?? '') === ($session['nickname'] ?? '');
    }

    public function loadState(string $channel): array
    {
        $path = $this->roomPath($channel);
        if (!is_file($path)) {
            return $this->defaultRoom($channel);
        }

        $json = file_get_contents($path);
        $data = json_decode($json ?: '', true);
        if (!is_array($data)) {
            return $this->defaultRoom($channel);
        }

        return array_merge($this->defaultRoom($channel), $data);
    }

    public function withRoomLock(string $channel, callable $callback): mixed
    {
        $path = $this->roomPath($channel);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open room file');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock room file');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $room = $contents ? json_decode($contents, true) : [];
            if (!is_array($room)) {
                $room = [];
            }

            $room = array_merge($this->defaultRoom($channel), $room);
            $result = $callback($room);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($room, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $result;
        } finally {
            fclose($handle);
        }
    }

    public function join(array $session): array
    {
        $channel = $session['channel'];
        return $this->withRoomLock($channel, function (array &$room) use ($session): array {
            $this->cleanupStalePeers($room);
            $peerId = $session['peer_id'];
            $existing = $room['peers'][$peerId] ?? null;

            if ($existing === null && count($room['peers']) >= (int) Helpers::config('signal_max_peers', 8)) {
                return ['ok' => false, 'reason' => 'room_full'];
            }

            $room['peers'][$peerId] = [
                'id' => $peerId,
                'guest_id' => $session['guest_id'],
                'nickname' => $session['nickname'],
                'joined_at' => $existing['joined_at'] ?? time(),
                'last_seen' => time(),
                'speaking' => $room['speaker'] === $peerId,
            ];

            $this->appendEvent($room, 'peer_joined', [
                'peer_id' => $peerId,
                'nickname' => $session['nickname'],
            ]);

            return $this->roomSnapshot($room, $peerId);
        });
    }

    public function heartbeat(array $session): array
    {
        return $this->withRoomLock($session['channel'], function (array &$room) use ($session): array {
            $this->cleanupStalePeers($room);
            $peerId = $session['peer_id'];
            if (!isset($room['peers'][$peerId])) {
                $room['peers'][$peerId] = [
                    'id' => $peerId,
                    'guest_id' => $session['guest_id'],
                    'nickname' => $session['nickname'],
                    'joined_at' => time(),
                    'last_seen' => time(),
                    'speaking' => false,
                ];
                $this->appendEvent($room, 'peer_joined', [
                    'peer_id' => $peerId,
                    'nickname' => $session['nickname'],
                ]);
            }

            $room['peers'][$peerId]['last_seen'] = time();
            return $this->roomSnapshot($room, $peerId);
        });
    }

    public function poll(array $session, int $since): array
    {
        return $this->withRoomLock($session['channel'], function (array &$room) use ($session, $since): array {
            $this->cleanupStalePeers($room);
            $this->touchPeer($room, $session['peer_id'], $session['nickname'], $session['guest_id']);
            $events = array_values(array_filter($room['events'], static fn (array $event): bool => (int) $event['id'] > $since));
            return $this->roomSnapshot($room, $session['peer_id'], $events);
        });
    }

    public function requestFloor(array $session): array
    {
        return $this->withRoomLock($session['channel'], function (array &$room) use ($session): array {
            $this->cleanupStalePeers($room);
            $peerId = $session['peer_id'];
            $now = time();

            if ($room['speaker'] !== null && $room['speaker'] !== $peerId && $room['speaker_until'] > $now) {
                return ['ok' => false, 'reason' => 'busy', 'speaker' => $room['speaker']];
            }

            $room['speaker'] = $peerId;
            $room['speaker_until'] = $now + (int) ceil(((int) Helpers::config('signal_floor_timeout_ms', 30000)) / 1000);
            $room['peers'][$peerId]['last_seen'] = $now;
            $room['peers'][$peerId]['speaking'] = true;

            foreach ($room['peers'] as $id => &$peer) {
                $peer['speaking'] = $id === $peerId;
            }

            $this->appendEvent($room, 'speaker_changed', [
                'peer_id' => $peerId,
                'nickname' => $session['nickname'],
                'speaking' => true,
            ]);

            return $this->roomSnapshot($room, $peerId);
        });
    }

    public function releaseFloor(array $session): array
    {
        return $this->withRoomLock($session['channel'], function (array &$room) use ($session): array {
            $peerId = $session['peer_id'];
            if (($room['speaker'] ?? null) !== $peerId) {
                $this->touchPeer($room, $peerId, $session['nickname'], $session['guest_id']);
                return $this->roomSnapshot($room, $peerId);
            }

            $room['speaker'] = null;
            $room['speaker_until'] = 0;
            if (isset($room['peers'][$peerId])) {
                $room['peers'][$peerId]['speaking'] = false;
                $room['peers'][$peerId]['last_seen'] = time();
            }

            $this->appendEvent($room, 'speaker_changed', [
                'peer_id' => $peerId,
                'nickname' => $session['nickname'],
                'speaking' => false,
            ]);

            return $this->roomSnapshot($room, $peerId);
        });
    }

    public function relaySignal(array $session, array $message): array
    {
        return $this->withRoomLock($session['channel'], function (array &$room) use ($session, $message): array {
            $target = (string) ($message['target'] ?? '');
            if ($target === '' || !isset($room['peers'][$target])) {
                return ['ok' => false, 'reason' => 'target_missing'];
            }

            $payload = $message['payload'] ?? [];
            if (!is_array($payload)) {
                $payload = [];
            }

            $this->appendEvent($room, 'signal', [
                'from' => $session['peer_id'],
                'to' => $target,
                'payload' => $payload,
            ]);

            return ['ok' => true];
        });
    }

    public function leave(array $session): array
    {
        return $this->withRoomLock($session['channel'], function (array &$room) use ($session): array {
            $peerId = $session['peer_id'];
            unset($room['peers'][$peerId]);
            if (($room['speaker'] ?? null) === $peerId) {
                $room['speaker'] = null;
                $room['speaker_until'] = 0;
                $this->appendEvent($room, 'speaker_changed', [
                    'peer_id' => $peerId,
                    'nickname' => $session['nickname'],
                    'speaking' => false,
                ]);
            }

            $this->appendEvent($room, 'peer_left', [
                'peer_id' => $peerId,
                'nickname' => $session['nickname'],
            ]);

            return ['ok' => true];
        });
    }

    private function defaultRoom(string $channel): array
    {
        return [
            'channel' => $this->normalizeChannel($channel),
            'speaker' => null,
            'speaker_until' => 0,
            'sequence' => 0,
            'updated_at' => time(),
            'peers' => [],
            'events' => [],
        ];
    }

    private function appendEvent(array &$room, string $type, array $data): void
    {
        $room['sequence'] = ((int) ($room['sequence'] ?? 0)) + 1;
        $event = [
            'id' => $room['sequence'],
            'type' => $type,
            'time' => time(),
            'data' => $data,
        ];

        $room['events'][] = $event;
        if (count($room['events']) > self::EVENT_LIMIT) {
            $room['events'] = array_slice($room['events'], -self::EVENT_LIMIT);
        }

        $room['updated_at'] = time();
    }

    private function cleanupStalePeers(array &$room): void
    {
        $now = time();
        foreach ($room['peers'] as $id => $peer) {
            if (($peer['last_seen'] ?? 0) < ($now - self::PEER_TTL)) {
                unset($room['peers'][$id]);
                if (($room['speaker'] ?? null) === $id) {
                    $room['speaker'] = null;
                    $room['speaker_until'] = 0;
                }
                $this->appendEvent($room, 'peer_left', [
                    'peer_id' => $id,
                    'nickname' => $peer['nickname'] ?? 'Guest',
                ]);
            }
        }

        if (($room['speaker_until'] ?? 0) > 0 && ($room['speaker_until'] < $now) && ($room['speaker'] ?? null) !== null) {
            $expired = (string) $room['speaker'];
            $room['speaker'] = null;
            $room['speaker_until'] = 0;
            if (isset($room['peers'][$expired])) {
                $room['peers'][$expired]['speaking'] = false;
            }
            $this->appendEvent($room, 'speaker_changed', [
                'peer_id' => $expired,
                'nickname' => $room['peers'][$expired]['nickname'] ?? 'Guest',
                'speaking' => false,
                'reason' => 'timeout',
            ]);
        }
    }

    private function touchPeer(array &$room, string $peerId, string $nickname, string $guestId): void
    {
        if (!isset($room['peers'][$peerId])) {
            $room['peers'][$peerId] = [
                'id' => $peerId,
                'guest_id' => $guestId,
                'nickname' => $nickname,
                'joined_at' => time(),
                'last_seen' => time(),
                'speaking' => false,
            ];
            $this->appendEvent($room, 'peer_joined', [
                'peer_id' => $peerId,
                'nickname' => $nickname,
            ]);
            return;
        }

        $room['peers'][$peerId]['nickname'] = $nickname;
        $room['peers'][$peerId]['guest_id'] = $guestId;
        $room['peers'][$peerId]['last_seen'] = time();
    }

    private function roomSnapshot(array $room, string $peerId, array $events = []): array
    {
        $participants = [];
        foreach ($room['peers'] as $id => $peer) {
            $participants[] = [
                'id' => $id,
                'nickname' => (string) ($peer['nickname'] ?? 'Guest'),
                'online' => true,
                'speaking' => ($room['speaker'] ?? null) === $id,
                'last_seen' => (int) ($peer['last_seen'] ?? 0),
            ];
        }

        return [
            'ok' => true,
            'channel' => $room['channel'],
            'peer_id' => $peerId,
            'speaker' => $room['speaker'],
            'speaker_until' => $room['speaker_until'],
            'participants' => $participants,
            'count' => count($participants),
            'events' => $events,
            'sequence' => (int) $room['sequence'],
            'max_peers' => (int) Helpers::config('signal_max_peers', 8),
        ];
    }
}
