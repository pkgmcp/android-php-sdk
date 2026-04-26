# Android PHP SDK — Interactive Web Demo

A complete web-based control panel for Android Fastboot + ADB operations, powered by pure PHP 8.3.

## Setup

### Prerequisites
- PHP 8.3+ with `ext-zip`
- ADB server running (`adb start-server`)
- At least one Android device connected (USB or wireless)

### Installation

1. Clone the repo:
```bash
git clone https://github.com/pkgmcp/android-php-sdk.git
cd android-php-sdk
```

2. Install dependencies for both packages:
```bash
cd fastboot-php && composer install && cd ..
cd adb-php && composer install && cd ..
```

3. Start the PHP built-in server:
```bash
cd demo/public
php -S localhost:8080
```

4. Open **http://localhost:8080** in your browser

## Features

| Section | Description |
|---|---|
| **Device List** | View all connected devices with state and USB paths |
| **Device Info** | Model, Android version, SDK, CPU ABI, IP address |
| **Shell** | Run any shell command on the device |
| **Install/Uninstall** | Install APKs, uninstall packages, clear app data |
| **File Manager** | Browse, push, pull files on the device |
| **Screen** | Take screenshots, view framebuffer metadata |
| **Port Forward** | Create and list forward/reverse rules |
| **System** | Reboot, root, remount, TCP/IP mode |
| **Fastboot** | Flash partitions, erase data, lock/unlock bootloader |
| **Wireless ADB** | Connect/disconnect devices over WiFi |

## Architecture

```
demo/
├── public/
│   ├── index.html          ← Single-page web app (SPA)
│   └── src/
│       └── api.php         ← PHP API backend (REST-like)
├── composer.json
└── README.md
```

The web UI sends POST requests to `src/api.php` with an `action` parameter. The API maps each action to the appropriate fastboot-php or adb-php method and returns JSON.

## Security

- This is a **demo** — do not expose to the public internet
- Runs on localhost by default
- All file paths are server-side paths (not user uploads)
- No authentication built in — add your own for production use

## Screenshots

The demo features a modern dark design inspired by thegridcn.com with:
- Sidebar navigation with 11 sections
- Real-time device status indicators
- Interactive command outputs with syntax highlighting
- Screenshot preview in-browser
- Quick-action buttons for common commands
- Responsive layout (mobile-friendly)
