# Walkie Talkie

Walkie Talkie is a browser-based push-to-talk voice communication system built with PHP 8 and vanilla JavaScript. Users join a shared channel with a nickname, see who is present, and hold a large PTT button to transmit voice to other participants.

## Features

- PHP 8 MVC-style structure
- Join page with nickname and channel
- Session-based guest identities
- Channel room management using JSON files
- Push-to-talk floor control
- WebRTC audio transport
- Polling-based signaling endpoint
- Participant list and speaker state
- Microphone permission handling
- Audio level visualization
- PWA manifest and service worker
- Mobile-friendly dark UI
- CSRF protection and rate limiting

## Requirements

- PHP 8.3+
- Apache
- Chrome, Edge, or Firefox
- HTTPS in production for microphone access

## XAMPP Installation

1. Copy the project into:

   `C:\xampp\htdocs\walkie_talkie`

2. Copy `.env.example` to `.env` and adjust settings if needed.
3. Start Apache in XAMPP.
4. Open the app in your browser.
5. Enter your nickname and channel name.
6. Allow microphone access when you press and hold the talk button.

## Configuration

Important values live in `.env`:

```env
APP_ENV=local
APP_DEBUG=true
APP_BASE_PATH=/walkie_talkie
SIGNAL_SECRET=CHANGE_THIS_SECRET
SIGNAL_MAX_PEERS=8
SIGNAL_FLOOR_TIMEOUT_MS=30000
```

## Mobile Testing

- Use the same Wi-Fi network on both devices.
- Open the app using the LAN IP or hostname.
- Microphone access works best over HTTPS.
- Install the app from the browser menu when supported.

## PWA Installation

- Android Chrome: use the install prompt or browser menu.
- iPhone Safari: use the Share menu and choose Add to Home Screen.
- Desktop Chrome / Edge: use the install icon in the address bar when available.

## cPanel Deployment

1. Upload the files to your hosting account.
2. Point the document root to the project directory.
3. Configure `.env`.
4. Ensure rewrite rules are enabled.
5. Make sure `storage/rooms` and `storage/logs` are writable.
6. Enable HTTPS so microphone permissions work reliably.

## Troubleshooting

- **Microphone unavailable**: check browser permissions and HTTPS.
- **Channel full**: the room reached the configured user limit.
- **WebRTC connection failed**: try a different network or use TURN.
- **Signaling unavailable**: confirm Apache and PHP are running.
- **PWA not installing**: use a supported browser and HTTPS.
- **Phone cannot connect**: confirm the LAN IP, firewall, and network.

## Notes

- STUN helps NAT traversal, but restrictive networks may still need a TURN server.
- The app is voice only; no video is implemented.
