# Walkie-Talkie PWA Communication System

A modern, responsive, web-based digital Walkie-Talkie and Push-to-Talk (PTT) voice communication system built from scratch using pure PHP 8.3+, Vanilla JavaScript, WebRTC, and Progressive Web App (PWA) standards.

---

## 📻 1. Project Overview

The **Walkie-Talkie Communication System** allows multiple operators to join virtual voice channels through any web browser and communicate in real time using a digital Push-to-Talk interface.

Just like a real handheld radio:
* Users select a callsign/nickname and frequency channel (e.g. `#general`, `#security`, `#team-a`).
* A large circular **Push-to-Talk (PTT)** button controls the transmission floor.
* **One Speaker at a Time:** If an operator is transmitting, the channel locks and other operators see `CHANNEL BUSY` or who is currently speaking.
* **WebRTC Voice Mesh:** Real-time audio is transmitted directly peer-to-peer with echo cancellation, noise suppression, and auto gain control.
* **Audio Level Monitor:** A retro-tactical LED VU meter visualizes audio levels.
* **Roger Beep & Tactical Squelch:** Synthesized audio tones using the Web Audio API provide authentic radio feedback on transmit start and release.
* **No Database Required:** Operates smoothly with atomic file-locked JSON room storage (`storage/rooms/`), making it simple to deploy, maintain, and study for student programming projects.
* **Installable PWA:** Can be installed directly to the home screen on Android, iOS, or desktop.

---

## 🚀 2. Major Features

| Feature | Description |
| :--- | :--- |
| **Push-to-Talk (PTT)** | Supports mouse hold, touch hold, pointer events, and keyboard **Spacebar** keydown/keyup. |
| **Floor Arbitration** | Server-side atomic file locking (`flock`) prevents race conditions and ensures only one user speaks at a time. |
| **Speaker Timeout** | Configurable speaking duration limit (default: 30 seconds) automatically releases the communication floor. |
| **WebRTC Audio-Only** | Peer-to-peer mesh audio without video overhead, optimizing bandwidth and latency. |
| **HTTP Signaling** | Clean polling-based signaling with sequence numbering and exponential backoff reconnection. |
| **Security & Tokens** | Short-lived `HMAC-SHA256` signed peer tokens, CSRF protection, rate limiting, and strict input sanitization. |
| **LED VU Meter** | Canvas-based multi-color LED segmented audio visualizer reacting to outgoing or incoming speech. |
| **Offline LAN QR Code** | Embedded zero-dependency canvas QR code generator on the join page for instant phone pairing. |
| **Progressive Web App** | Web App Manifest and Service Worker caching the application shell for fast native-like launches. |

---

## 🛠️ 3. Technology Stack

* **Backend:** PHP 8.3+ (Strict types, MVC architectural structure, zero third-party framework dependencies)
* **Storage:** JSON file storage with exclusive file locks (`flock`), PHP native sessions
* **Frontend:** HTML5, CSS3, Vanilla JavaScript (ES6+), Bootstrap 5 (CDN), Font Awesome 6 (CDN)
* **Real-Time Voice:** WebRTC (`RTCPeerConnection`, `getUserMedia`, `MediaStream`)
* **Audio Processing:** Web Audio API (`AudioContext`, `AnalyserNode`, `OscillatorNode`)
* **PWA:** Web App Manifest (`manifest.webmanifest`), Service Worker (`sw.js`)

---

## 📂 4. Project Directory Structure

```text
walkie_talkie_app/
│
├── index.php                      # Root entry point (XAMPP plug-and-play)
├── .htaccess                      # Apache URL rewriting & security headers
├── .env                           # Environment settings & secrets
├── .env.example                   # Example environment configuration
├── README.md                      # Complete system documentation
│
├── config/
│   ├── app.php                    # Application configuration & paths
│   └── routes.php                 # HTTP routing definitions
│
├── app/
│   ├── Controllers/
│   │   ├── HomeController.php     # Landing page, join form, leave handler
│   │   ├── ChannelController.php  # Walkie-talkie UI view controller
│   │   └── SignalingController.php# Signaling API (hello, poll, ptt, signal)
│   ├── Core/
│   │   ├── App.php                # Application bootstrapper & autoloader
│   │   ├── Env.php                # .env file parser
│   │   ├── Request.php            # HTTP request abstraction & IP detection
│   │   ├── Response.php           # JSON & redirect response handler
│   │   ├── Router.php             # Route matcher with middleware support
│   │   ├── Session.php            # Secure session & CSRF management
│   │   └── View.php               # View template rendering engine
│   ├── Helpers/
│   │   ├── SecurityHelper.php     # Sanitization, XSS escaping, HMAC tokens
│   │   └── UrlHelper.php          # Base path and asset URL resolver
│   ├── Middleware/
│   │   ├── CsrfMiddleware.php     # CSRF validation on POST forms
│   │   └── RateLimitMiddleware.php# DoS and request rate limiter
│   └── Services/
│       ├── RateLimiter.php        # Sliding window file-based rate limiter
│       ├── RoomService.php        # Atomic file-locked room state & queue
│       ├── SignalingService.php   # WebRTC signaling & floor arbiter
│       └── TokenService.php       # HMAC-SHA256 session token generator
│
├── views/
│   ├── layouts/
│   │   ├── header.php             # HTML head, PWA meta, and stylesheets
│   │   └── footer.php             # PWA install prompt & scripts
│   ├── join.php                   # Landing & Join Channel view + QR code
│   ├── channel.php                # Main Walkie-Talkie interface
│   └── errors/
│       ├── 404.php                # Custom 404 Not Found view
│       └── 500.php                # Custom 500 Server Error view
│
├── public/
│   ├── index.php                  # Public entry point (for VirtualHosts)
│   ├── manifest.webmanifest       # PWA manifest
│   ├── sw.js                      # PWA service worker
│   │
│   └── assets/
│       ├── css/
│       │   └── app.css            # Tactical radio styling & responsive layout
│       ├── js/
│       │   ├── app.js             # Main coordinator script
│       │   ├── ptt.js             # Push-to-Talk controller (mouse, touch, key)
│       │   ├── webrtc.js          # WebRTC peer connection manager
│       │   ├── signaling.js       # HTTP polling client & backoff reconnect
│       │   ├── audio-level.js     # Canvas VU meter & Web Audio FX
│       │   ├── pwa.js             # Service worker installer
│       │   └── qrcode.min.js      # Zero-dependency QR canvas library
│       └── icons/
│           ├── favicon.svg        # Vector radio icon
│           ├── icon-192.png       # 192x192 PWA icon
│           └── icon-512.png       # 512x512 PWA icon
│
├── storage/
│   ├── rooms/                     # Active JSON channel files (file-locked)
│   ├── logs/                      # Application error logs
│   └── ratelimit/                 # Rate limiting state files
│
└── tests/
    ├── test_walkie_talkie.php     # Automated testing suite
    └── test_http_routes.php       # Routing verification test
```

---

## 💻 5. Local Installation on XAMPP (Windows)

### Step 1: Place the Project in `htdocs`
Copy the project folder to:
```text
C:\xampp\htdocs\walkie_talkie_app
```

### Step 2: Configure Environment
Copy `.env.example` to `.env` (if not already present):
```powershell
Copy-Item .env.example .env
```
Key configuration parameters in `.env`:
* `APP_NAME="Walkie Talkie PWA"`
* `APP_BASE_PATH=` *(Leave blank to automatically detect `/walkie_talkie_app`)*
* `APP_PUBLIC_URL=` *(Set to a phone-reachable LAN URL, e.g. `http://192.168.1.20/walkie_talkie_app`)*
* `SIGNAL_SECRET=your_custom_secret_key` *(HMAC signing salt)*
* `SIGNAL_MAX_PEERS=8` *(Maximum operators per channel)*
* `SIGNAL_FLOOR_TIMEOUT_MS=30000` *(30-second speaking limit)*

### Step 3: Start Apache
Open the **XAMPP Control Panel** and click **Start** next to **Apache**. Ensure Apache is running on port 80 or 443.

### Step 4: Open in Web Browser
Navigate to:
```text
http://localhost/walkie_talkie_app/
```
Or if using a custom Apache VirtualHost pointing to `public/`:
```text
http://localhost/
```

For phone pairing, connect the phone and computer to the same Wi-Fi network.
If the QR code points to an unreachable host, set `APP_PUBLIC_URL` to the
computer's LAN address and allow Apache through the Windows firewall. The QR
code uses a standards-compliant encoder and contains this configured URL.

### Step 5: Test Voice Communication
1. Open two browser windows (or one regular window and one Incognito/Private window).
2. Enter Nickname `Alex` and Channel `team-alpha` in Window 1.
3. Enter Nickname `Bob` and Channel `team-alpha` in Window 2.
4. Allow browser microphone access when prompted.
5. In Window 1, press and hold the **PTT button** (or hold **Spacebar**):
   * Window 1 indicates: `TRANSMITTING`
   * Window 2 indicates: `Alex is talking` and button locks as `CHANNEL BUSY`.
6. Release the button:
   * Window 1 plays a Roger Beep and releases the floor.
   * Window 2 can now press and transmit.

---

## 📱 6. Mobile Testing on the Same Wi-Fi Network

To test on your smartphone or tablet connected to the same Wi-Fi router:

### Step 1: Find Your Computer's Local IP Address
Open PowerShell or Command Prompt on your computer and run:
```powershell
ipconfig
```
Look for your **IPv4 Address** (e.g., `192.168.1.50`).

### Step 2: Open the URL on Your Mobile Browser
On your phone's browser, enter:
```text
http://192.168.1.50/walkie_talkie_app/
```
*(Or click **"Phone QR"** on the join page of your desktop browser and scan the QR code with your phone's camera!)*

### Step 3: Important Browser Security Requirement for Microphones
Modern mobile browsers (Chrome on Android, Safari on iOS) **require a Secure Context (HTTPS or localhost)** to allow access to the microphone via `navigator.mediaDevices.getUserMedia()`.

If you test using a local IP like `http://192.168.1.50/` over plain HTTP, the browser may block the microphone. You have three easy options:

1. **Option A: Chrome Unsafe Origin Flag (Easiest for quick Android testing):**
   * On your Android phone, open Chrome and navigate to:
     `chrome://flags/#unsafely-treat-insecure-origin-as-secure`
   * Enable the flag and enter:
     `http://192.168.1.50`
   * Relaunch Chrome. Microphone permissions will now be permitted over local Wi-Fi!
2. **Option B: Ngrok or Cloudflare Tunnel (Recommended for iPhone/Safari):**
   * Run a free tunnel:
     ```bash
     ngrok http 80
     ```
   * Open the provided HTTPS URL (e.g. `https://xxxx.ngrok-free.app/walkie_talkie_app/`) on your phone. Microphones will work natively.
3. **Option C: Local SSL in XAMPP:**
   * Generate a trusted certificate using `mkcert` and enable HTTPS in Apache's `httpd-ssl.conf`.

---

## 📲 7. PWA Installation Instructions

The application is fully configured as a Progressive Web App (PWA):

* **Android (Chrome):**
  1. Visit the app URL in Chrome.
  2. A banner at the bottom will prompt **"Install Walkie-Talkie App"**, or tap the browser menu (⋮) and select **"Install app"** or **"Add to Home screen"**.
  3. The app launches full-screen without browser address bars.
* **iOS (Safari):**
  1. Visit the app URL in Safari.
  2. Tap the **Share** button (box with upward arrow) at the bottom.
  3. Scroll down and tap **"Add to Home Screen"**.
  4. Launch the app directly from your iOS Home Screen.
* **Desktop (Chrome / Microsoft Edge):**
  1. Click the **Install** icon in the browser address bar (top right) or the **"Install"** button in the app banner.
  2. The app opens in an isolated desktop window with its own taskbar icon.

---

## 🌐 8. cPanel & Production Deployment

To deploy to a live web hosting server with cPanel:

1. **Upload Files:**
   * Compress the project into a `.zip` file (excluding `storage/rooms/*` and `.git`).
   * In cPanel File Manager, upload and extract to `public_html/walkie-talkie` or `public_html/`.
2. **Set Storage Permissions:**
   * Ensure `storage/rooms/`, `storage/logs/`, and `storage/ratelimit/` have write permissions:
     ```bash
     chmod -R 775 storage/
     ```
3. **Configure Environment:**
   * Rename `.env.example` to `.env`.
   * Generate a strong random string for `SIGNAL_SECRET`.
   * Set `APP_ENV=production` and `APP_DEBUG=false`.
4. **Enable HTTPS / SSL:**
   * In cPanel, navigate to **SSL/TLS Status** and run **AutoSSL** (or Let's Encrypt).
   * Ensure HTTPS is enforced. This guarantees reliable microphone permissions across all client devices.
5. **Verify Apache `mod_rewrite`:**
   * Ensure Apache `.htaccess` rewriting is supported by the host.

---

## 🌐 9. WebRTC NAT Traversal (STUN & TURN)

* **STUN Servers:** By default, the application uses public Google STUN servers (`stun:stun.l.google.com:19302`) configured in `.env`.
* **NAT Traversal:** STUN servers allow peers to discover their public IP addresses and establish direct peer-to-peer audio connections across standard consumer routers and home Wi-Fi networks.
* **Production TURN Notice:** On highly restrictive corporate networks, symmetric NAT firewalls, or certain mobile carrier networks, direct peer-to-peer UDP packets may be filtered. In enterprise production environments, adding credentials for a TURN relay server (such as Coturn) in `config/app.php` ensures 100% connectivity across all firewalls.

---

## 🧪 10. Automated Testing

The project includes an automated test suite verifying floor control, concurrency, HMAC tokens, input sanitization, and capacity limits:

```powershell
php tests/test_walkie_talkie.php
```

Expected output:
```text
=== STARTING AUTOMATED TEST SUITE ===

[PASS] Nickname Sanitization (strips tags, trims spaces)
[PASS] Channel Sanitization (slugified lowercase)
[PASS] Token Generation & HMAC-SHA256 Verification
[PASS] Tampered Token Rejected
[PASS] User A joins room (1 User Online)
[PASS] User B joins room (2 Users Online)
[PASS] User A presses PTT -> Floor Granted (Alice transmitting)
[PASS] User B presses PTT while A is speaking -> CHANNEL BUSY denied
[PASS] User A releases PTT -> Floor Released
[PASS] User B presses PTT after A released -> Floor Granted (Bob transmitting)
[PASS] User A sends WebRTC signal offer to User B
[PASS] User B polls and receives User A's WebRTC offer
[PASS] User A leaves channel
[PASS] User B observes only 1 peer remaining
[PASS] Room capacity enforcement: 9th user denied with 'channel is full' message

=== TEST SUMMARY: 15 Passed, 0 Failed ===
```

To test routing and controller responses:
```powershell
php tests/test_http_routes.php
```

---

## 🔧 11. Troubleshooting Guide

| Symptom | Probable Cause | Recommended Resolution |
| :--- | :--- | :--- |
| **Microphone unavailable / blocked** | Insecure context (HTTP over LAN IP) or browser permission denied. | Use `localhost`, configure HTTPS, or enable `chrome://flags/#unsafely-treat-insecure-origin-as-secure` on Android. Click the lock icon in the address bar to reset permissions. |
| **Channel is full** | Room reached the maximum peer limit (default 8). | Join a different channel frequency or adjust `SIGNAL_MAX_PEERS` in `.env`. |
| **WebRTC Audio not heard between peers** | Browser autoplay policy blocked audio before user interacted with the page. | Click anywhere on the channel interface to allow audio autoplay. Check that the speaker mute button at the bottom is not toggled. |
| **Status stays in RECONNECTING** | Signaling polling interrupted or Apache stopped. | Verify Apache is running. Check `storage/logs/app.log` for any PHP errors. Verify write permissions on `storage/rooms/`. |
| **PWA Install button does not appear** | Browser already installed the PWA or browser does not meet install criteria. | Look for the install icon in your browser address bar. In iOS Safari, use "Add to Home Screen" from the Share menu. |

---

## 📄 12. License & Academic Use

This project was built for educational and practical web development instruction. It demonstrates how modern web APIs (WebRTC, Web Audio API, Service Workers) can be elegantly combined with clean, framework-free PHP architecture. Free for educational and personal use.
