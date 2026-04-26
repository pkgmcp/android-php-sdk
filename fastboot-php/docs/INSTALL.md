# Installation

## Requirements

| Requirement | Version |
|---|---|
| PHP | ≥ 8.1 |
| ext-zip | bundled (for factory ZIP flashing) |
| Composer | 2.x |
| Linux OS | For `LibUsbTransport` direct USB access |

## Composer

```bash
composer require fastboot-php/fastboot-php
```

Or clone and install locally:

```bash
git clone https://github.com/your-org/fastboot-php.git
cd fastboot-php
composer install
```

## Linux USB Access

Android devices in fastboot mode appear as `/dev/bus/usb/<bus>/<device>`.  
You need read/write permission on that path.

### Option A — udev rule (recommended)

Create `/etc/udev/rules.d/51-android.rules`:

```udev
# Google / Pixel
SUBSYSTEM=="usb", ATTR{idVendor}=="18d1", MODE="0666", GROUP="plugdev"

# Samsung
SUBSYSTEM=="usb", ATTR{idVendor}=="04e8", MODE="0666", GROUP="plugdev"

# OnePlus
SUBSYSTEM=="usb", ATTR{idVendor}=="2a70", MODE="0666", GROUP="plugdev"

# Fallback: match all Android devices in fastboot class
SUBSYSTEM=="usb", ATTR{bDeviceClass}=="ff", ATTR{bDeviceSubClass}=="42", MODE="0666", GROUP="plugdev"
```

Reload rules:

```bash
sudo udevadm control --reload-rules
sudo udevadm trigger
```

Add your user to the `plugdev` group:

```bash
sudo usermod -aG plugdev $USER
# Log out and back in
```

### Option B — run as root (not recommended)

```bash
sudo php your_script.php
```

## Finding the Device Path

1. Boot your device into fastboot mode:
   ```bash
   adb reboot bootloader
   ```

2. List USB devices:
   ```bash
   lsusb
   # Bus 001 Device 005: ID 18d1:4ee0 Google Inc. Nexus/Pixel Device (fastboot)
   ```

3. The path is `/dev/bus/usb/001/005` (bus 001, device 005).

## TCP Transport (no udev required)

If you have ADB access:

```bash
# Forward device fastboot socket to localhost
adb forward tcp:5556 localabstract:fastboot
```

Then use `TcpTransport`:

```php
use FastbootPhp\Transport\TcpTransport;
$transport = new TcpTransport('127.0.0.1', 5556);
```
