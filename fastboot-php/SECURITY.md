# Security Policy — fastboot-php

## Supported Versions

| Version | PHP     | Supported          |
| ------- | ------- | ------------------ |
| 1.0.x   | ≥ 8.3   | ✅ Active          |

## Reporting a Vulnerability

If you discover a security vulnerability in fastboot-php, please:

1. **Do NOT open a public issue** — vulnerabilities can be exploited by attackers while the issue is open
2. Email a detailed report to your configured security contact
3. Include steps to reproduce, impact assessment, and suggested fix if possible

You will receive an acknowledgment within 48 hours and a detailed response within 5 business days.

## Security Architecture

### No Native Code

fastboot-php is **pure PHP** with zero compiled extensions (beyond standard `ext-zip`). This eliminates:
- Buffer overflow exploits in native code
- ABI incompatibility issues
- Supply chain attacks via precompiled binaries

### No Shell Execution

All fastboot protocol operations use direct USB/TCP I/O. No `exec()`, `shell_exec()`, `passthru()`, or `proc_open()` calls are used.

### Input Validation

- Sparse image headers are validated against the magic number before parsing
- Transfer size is validated against the 8-digit hex limit (4 GiB max)
- Bootloader responses are parsed with strict 4-character status token matching
- Unknown status tokens throw `FastbootError` rather than being silently accepted

### USB Access Control

`LibUsbTransport` requires explicit udev rules and appropriate file permissions. It cannot access devices without OS-level authorization.

### Zip File Handling

Factory ZIP parsing uses PHP's built-in `ZipArchive` with temporary files. All temp files are created in `sys_get_temp_dir()` and deleted in a `finally` block.

## Dependency Audit

| Dependency       | Version | Purpose                    | Risk |
| ---------------- | ------- | -------------------------- | ---- |
| `ext-zip`        | PHP bundled | Factory ZIP parsing  | Low (PHP core) |
| `phpunit`        | ^11.0   | Testing (dev only)       | N/A  |

No runtime dependencies beyond PHP 8.3 standard library.
