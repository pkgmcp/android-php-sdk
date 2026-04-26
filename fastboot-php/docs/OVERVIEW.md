# Overview

## What is fastboot-php?

`fastboot-php` is a pure-PHP implementation of the [Android Fastboot protocol](https://android.googlesource.com/platform/system/core/+/refs/heads/main/fastboot/README.md).  
It is a 1-to-1 port of [fastboot.js](https://github.com/kdrag0n/fastboot.js) by Danny Lin (kdrag0n), translated from JavaScript into idiomatic PHP 8.1+.

## Why?

Fastboot.js targets web browsers via the **WebUSB API**.  This PHP port enables server-side and CLI tooling to:

- Flash firmware images from PHP applications (CI/CD pipelines, web installers, etc.)
- Automate device provisioning in a factory or QA environment
- Integrate Android device management into existing PHP infrastructure
- Unit-test fastboot logic without a physical device (via the mock transport)

## Supported Features

| Feature | Status |
|---|---|
| Send raw fastboot commands | ✅ |
| Read bootloader variables (`getvar`) | ✅ |
| Upload raw payloads (`download:`) | ✅ |
| Flash raw images | ✅ |
| Flash sparse images | ✅ |
| Auto-split oversized sparse images | ✅ |
| A/B slot detection | ✅ |
| Raw → sparse conversion | ✅ |
| Factory ZIP flashing | ✅ |
| Erase partitions | ✅ |
| Lock / Unlock bootloader | ✅ |
| Reboot (OS / bootloader / recovery / fastbootd) | ✅ |
| Progress callbacks | ✅ |
| Pluggable transport layer | ✅ |
| Direct USB via device file (`/dev/bus/usb/…`) | ✅ |
| TCP tunnel transport | ✅ |
| Mock transport for testing | ✅ |
