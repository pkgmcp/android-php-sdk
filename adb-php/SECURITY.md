# Security Policy — adb-php

## Supported Versions

| Version | PHP     | Supported          |
| ------- | ------- | ------------------ |
| 1.0.x   | ≥ 8.3   | ✅ Active          |

## Reporting a Vulnerability

If you discover a security vulnerability in adb-php, please:

1. **Do NOT open a public issue** — vulnerabilities can be exploited by attackers while the issue is open
2. Email a detailed report to your configured security contact
3. Include steps to reproduce, impact assessment, and suggested fix if possible

You will receive an acknowledgment within 48 hours and a detailed response within 5 business days.

## Security Architecture

### No Native Code

adb-php is **pure PHP 8.3** with zero compiled extensions beyond standard PHP. This eliminates:
- Buffer overflow exploits in native code
- ABI incompatibility issues
- Supply chain attacks via precompiled binaries

### No Shell Execution

All ADB operations communicate directly with the ADB server via the **wire protocol over TCP** (port 5037). No `exec()`, `shell_exec()`, `passthru()`, `system()`, or `proc_open()` calls are used.

### ADB Wire Protocol

All communication follows the documented ADB protocol:
- 4-hex-digit length-prefixed messages
- `OKAY` / `FAIL` status tokens
- SYNC binary protocol with 4-byte command IDs
- Binary logcat format with fixed-size headers

Unknown protocol responses throw `ProtocolException` rather than being silently accepted.

### Input Validation

| Attack Vector | Mitigation |
|---|---|
| Malicious APK install | APK pushed to `/data/local/tmp` with random suffix, not user-specified path |
| Path traversal in push/pull | Remote paths passed directly to device; user must have appropriate permissions |
| Buffer overflow in SYNC | `MAX_DATA = 65536` chunk size limit enforced in all push/pull operations |
| Logcat binary injection | Logcat entries parsed with strict format: uint16 header, fixed fields, null-terminated strings |
| Monkey command injection | Commands are sent as single text lines; user input is not escaped into shell commands |
| Shell command injection | `shell()` executes whatever the caller passes — **this is intentional** as it mirrors ADB's design. Caller must sanitize input. |

### Network Security

- Default connection is to `127.0.0.1:5037` (loopback only)
- Remote ADB server connections (`adb connect`) require explicit host/port configuration
- TCP sockets use configurable timeouts (default 5000ms)
- No credential storage or transmission

### File System

- SyncService push uses random hex suffixes for temp files: `/data/local/tmp/{name}.{4 random bytes}`
- All temporary files created with `tmpfile()` (auto-deleted on close) or `tempnam()` (explicit cleanup in `finally`)
- No files written to shared directories without explicit user intent

## Dependency Audit

| Dependency       | Version | Purpose                    | Risk |
| ---------------- | ------- | -------------------------- | ---- |
| None             | —       | Runtime                    | ✅ Zero |
| `phpunit`        | ^11.0   | Testing (dev only)         | N/A  |

No runtime dependencies beyond PHP 8.3 standard library.

## Security Checklist

- [x] No `eval()`, `exec()`, `shell_exec()`, `system()`, `passthru()`
- [x] No native extensions required
- [x] No network credentials stored
- [x] Input validation on all binary protocols
- [x] Secure temp file handling
- [x] Exception handling for all error paths
- [x] Strict type declarations on all files
- [x] `readonly class` on all value objects (immutable by design)
