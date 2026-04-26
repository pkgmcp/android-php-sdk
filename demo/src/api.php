<?php

declare(strict_types=1);

/**
 * android-php-sdk — Web Demo Application
 *
 * Interactive web UI for Fastboot + ADB operations.
 * Modern dark design inspired by thegridcn.com
 */

require_once __DIR__ . '/../fastboot-php/vendor/autoload.php';
require_once __DIR__ . '/../adb-php/vendor/autoload.php';

use FastbootPhp\FastbootDevice;
use FastbootPhp\Common as FBCommon;
use FastbootPhp\Transport\TcpTransport as FBTcpTransport;
use AdbPhp\AdbClient;
use AdbPhp\Exceptions\AdbException;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// -------------------------------------------------------------------------
// Session-based device state
// -------------------------------------------------------------------------
session_start();

if (!isset($_SESSION['adb_client'])) {
    $_SESSION['adb_client'] = AdbClient::create(
        host: $_ENV['ADB_HOST'] ?? '127.0.0.1',
        port: (int) ($_ENV['ADB_PORT'] ?? 5037),
    );
}
$adb = $_SESSION['adb_client'];

// -------------------------------------------------------------------------
// API Endpoints
// -------------------------------------------------------------------------
try {
    match ($action) {

        // ── Server ──────────────────────────────────────────────────────
        'server_status' => respond(['version' => $adb->version()]),

        // ── Devices ─────────────────────────────────────────────────────
        'list_devices' => respond([
            'devices' => array_map(fn($d) => ['id' => $d->id, 'type' => $d->type], $adb->listDevices()),
        ]),

        'list_devices_paths' => respond([
            'devices' => array_map(fn($d) => ['id' => $d->id, 'type' => $d->type, 'path' => $d->path], $adb->listDevicesWithPaths()),
        ]),

        // ── Device Info ─────────────────────────────────────────────────
        'device_info' => do_device_info(),
        'device_shell' => do_shell(),
        'device_properties' => do_properties(),
        'device_features' => do_features(),
        'device_packages' => do_packages(),
        'device_ip' => do_ip(),
        'device_state' => do_state(),

        // ── App Management ──────────────────────────────────────────────
        'install_apk' => do_install_apk(),
        'uninstall_package' => do_uninstall(),
        'clear_package' => do_clear(),
        'is_installed' => do_is_installed(),

        // ── File Transfer ───────────────────────────────────────────────
        'push_file' => do_push_file(),
        'pull_file' => do_pull_file(),
        'stat_file' => do_stat_file(),
        'readdir' => do_readdir(),

        // ── Screen ──────────────────────────────────────────────────────
        'screencap' => do_screencap(),
        'framebuffer' => do_framebuffer(),

        // ── Port Forwarding ─────────────────────────────────────────────
        'list_forwards' => do_list_forwards(),
        'add_forward' => do_add_forward(),
        'list_reverses' => do_list_reverses(),
        'add_reverse' => do_add_reverse(),

        // ── System ──────────────────────────────────────────────────────
        'reboot' => do_reboot(),
        'root' => do_root(),
        'remount' => do_remount(),
        'tcpip' => do_tcpip(),
        'usb' => do_usb(),

        // ── Wireless ADB ────────────────────────────────────────────────
        'wireless_connect' => do_wireless_connect(),
        'wireless_disconnect' => do_wireless_disconnect(),

        // ── Fastboot ────────────────────────────────────────────────────
        'fastboot_connect' => do_fastboot_connect(),
        'fastboot_disconnect' => do_fastboot_disconnect(),
        'fastboot_getvar' => do_fastboot_getvar(),
        'fastboot_flash' => do_fastboot_flash(),
        'fastboot_erase' => do_fastboot_erase(),
        'fastboot_reboot' => do_fastboot_reboot(),
        'fastboot_lock' => do_fastboot_lock(),
        'fastboot_unlock' => do_fastboot_unlock(),

        // ── Default ─────────────────────────────────────────────────────
        default => error("Unknown action: {$action}"),
    };
} catch (\Throwable $e) {
    error($e->getMessage());
}

// -------------------------------------------------------------------------
// Handlers
// -------------------------------------------------------------------------

function do_device_info(): void
{
    $serial = req('serial');
    $dev = get_device($serial);
    respond([
        'serial'      => $dev->getSerialNo(),
        'state'       => $dev->getState(),
        'path'        => $dev->getDevicePath(),
        'ip'          => $dev->getDHCPIpAddress(),
        'model'       => $dev->getProperties()['ro.product.model'] ?? null,
        'android'     => $dev->getProperties()['ro.build.version.release'] ?? null,
        'abi'         => $dev->getProperties()['ro.product.cpu.abi'] ?? null,
        'sdk'         => $dev->getProperties()['ro.build.version.sdk'] ?? null,
        'manufacturer'=> $dev->getProperties()['ro.product.manufacturer'] ?? null,
    ]);
}

function do_shell(): void
{
    $serial = req('serial');
    $cmd    = req('command');
    $dev    = get_device($serial);
    respond(['output' => $dev->shell($cmd)]);
}

function do_properties(): void
{
    $dev  = get_device(req('serial'));
    $all  = $dev->getProperties();
    $keys = req('keys') ?? '';
    if ($keys !== '') {
        $filter = array_map('trim', explode(',', $keys));
        $all = array_filter($all, fn($k) => in_array($k, $filter, true), ARRAY_FILTER_USE_KEY);
    }
    respond(['properties' => $all]);
}

function do_features(): void
{
    $dev = get_device(req('serial'));
    respond(['features' => $dev->getFeatures()]);
}

function do_packages(): void
{
    $dev = get_device(req('serial'));
    respond(['packages' => $dev->getPackages()]);
}

function do_ip(): void
{
    $dev = get_device(req('serial'));
    respond(['ip' => $dev->getDHCPIpAddress(req('iface') ?: 'wlan0')]);
}

function do_state(): void
{
    $dev = get_device(req('serial'));
    respond(['state' => $dev->getState()]);
}

function do_install_apk(): void
{
    $serial = req('serial');
    $path   = req('path');
    $dev    = get_device($serial);
    $dev->install($path);
    respond(['status' => 'installed', 'path' => $path]);
}

function do_uninstall(): void
{
    $dev = get_device(req('serial'));
    $dev->uninstall(req('package'));
    respond(['status' => 'uninstalled', 'package' => req('package')]);
}

function do_clear(): void
{
    $dev = get_device(req('serial'));
    $dev->clear(req('package'));
    respond(['status' => 'cleared', 'package' => req('package')]);
}

function do_is_installed(): void
{
    $dev = get_device(req('serial'));
    respond(['installed' => $dev->isInstalled(req('package'))]);
}

function do_push_file(): void
{
    $dev  = get_device(req('serial'));
    $mode = (int) (req('mode') ?: '644');
    $dev->push(req('local_path'), req('remote_path'), octdec($mode));
    respond(['status' => 'pushed']);
}

function do_pull_file(): void
{
    $dev    = get_device(req('serial'));
    $result = $dev->pull(req('remote_path'));
    respond(['size' => $result['transfer']->getBytesTransferred()]);
}

function do_stat_file(): void
{
    $dev   = get_device(req('serial'));
    $entry = $dev->stat(req('path'));
    respond([
        'name' => $entry->name,
        'size' => $entry->size,
        'mode' => decoct($entry->mode),
        'is_directory' => $entry->isDirectory(),
        'is_file' => $entry->isFile(),
    ]);
}

function do_readdir(): void
{
    $dev     = get_device(req('serial'));
    $entries = $dev->readdir(req('path'));
    respond([
        'entries' => array_map(fn($e) => [
            'name'  => $e->name,
            'size'  => $e->size,
            'mode'  => decoct($e->mode),
            'is_dir'=> $e->isDirectory(),
            'is_file' => $e->isFile(),
        ], $entries),
    ]);
}

function do_screencap(): void
{
    $dev = get_device(req('serial'));
    $png = $dev->screencap();
    respond([
        'size'    => strlen($png),
        'preview' => 'data:image/png;base64,' . base64_encode($png),
    ]);
}

function do_framebuffer(): void
{
    $dev    = get_device(req('serial'));
    $result = $dev->framebuffer(req('format') ?: 'rgba');
    respond([
        'width'  => $result['meta']->width,
        'height' => $result['meta']->height,
        'bpp'    => $result['meta']->bpp,
        'size'   => strlen($result['data']),
    ]);
}

function do_list_forwards(): void
{
    $dev = get_device(req('serial'));
    respond(['forwards' => array_map(fn($f) => ['local' => $f->local, 'remote' => $f->remote], $dev->listForwards())]);
}

function do_add_forward(): void
{
    $dev = get_device(req('serial'));
    $dev->forward(req('local'), req('remote'));
    respond(['status' => 'forwarded']);
}

function do_list_reverses(): void
{
    $dev = get_device(req('serial'));
    respond(['reverses' => array_map(fn($r) => ['remote' => $r->remote, 'local' => $r->local], $dev->listReverses())]);
}

function do_add_reverse(): void
{
    $dev = get_device(req('serial'));
    $dev->reverse(req('remote'), req('local'));
    respond(['status' => 'reversed']);
}

function do_reboot(): void
{
    $dev = get_device(req('serial'));
    $dev->reboot(req('mode') ?: '');
    respond(['status' => 'rebooting']);
}

function do_root(): void
{
    $dev = get_device(req('serial'));
    $dev->root();
    respond(['status' => 'root_requested']);
}

function do_remount(): void
{
    $dev = get_device(req('serial'));
    $dev->remount();
    respond(['status' => 'remounted']);
}

function do_tcpip(): void
{
    $dev = get_device(req('serial'));
    $dev->tcpip((int) (req('port') ?: 5555));
    respond(['status' => 'tcpip_enabled']);
}

function do_usb(): void
{
    $dev = get_device(req('serial'));
    $dev->usb();
    respond(['status' => 'usb_mode']);
}

function do_wireless_connect(): void
{
    $host = req('host');
    $port = (int) (req('port') ?: 5555);
    $_SESSION['adb_client'] = AdbClient::create();
    $msg = $_SESSION['adb_client']->connect($host, $port);
    respond(['status' => 'connected', 'message' => $msg]);
}

function do_wireless_disconnect(): void
{
    $host = req('host');
    $port = (int) (req('port') ?: 5555);
    $_SESSION['adb_client'] = AdbClient::create();
    $msg = $_SESSION['adb_client']->disconnect($host, $port);
    respond(['status' => 'disconnected', 'message' => $msg]);
}

// ── Fastboot ─────────────────────────────────────────────────────────────

function do_fastboot_connect(): void
{
    $path = req('device_path');
    $dev  = new FastbootDevice(new FBTcpTransport('127.0.0.1', 5556));
    $dev->connect();
    $_SESSION['fastboot_device'] = $dev;
    respond(['status' => 'connected', 'product' => $dev->getVariable('product')]);
}

function do_fastboot_disconnect(): void
{
    if (isset($_SESSION['fastboot_device'])) {
        $_SESSION['fastboot_device']->disconnect();
        unset($_SESSION['fastboot_device']);
    }
    respond(['status' => 'disconnected']);
}

function do_fastboot_getvar(): void
{
    $dev = get_fastboot_device();
    $var = req('variable');
    respond(['variable' => $var, 'value' => $dev->getVariable($var)]);
}

function do_fastboot_flash(): void
{
    $dev = get_fastboot_device();
    $partition = req('partition');
    $path      = req('image_path');
    $dev->flashBlob($partition, file_get_contents($path));
    respond(['status' => 'flashed', 'partition' => $partition]);
}

function do_fastboot_erase(): void
{
    $dev = get_fastboot_device();
    $dev->erase(req('partition'));
    respond(['status' => 'erased', 'partition' => req('partition')]);
}

function do_fastboot_reboot(): void
{
    $dev = get_fastboot_device();
    $mode = req('mode') ?: '';
    match ($mode) {
        'bootloader' => $dev->rebootBootloader(),
        'recovery'   => $dev->rebootRecovery(),
        default      => $dev->reboot(),
    };
    respond(['status' => 'rebooting', 'mode' => $mode]);
}

function do_fastboot_lock(): void
{
    $dev = get_fastboot_device();
    $dev->lock();
    respond(['status' => 'locked']);
}

function do_fastboot_unlock(): void
{
    $dev = get_fastboot_device();
    $dev->unlock();
    respond(['status' => 'unlocked']);
}

// -------------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------------

function get_device(string $serial): \AdbPhp\DeviceClient
{
    if ($serial === '') {
        throw new AdbException('No device serial provided.');
    }
    return $_SESSION['adb_client']->getDevice($serial);
}

function get_fastboot_device(): FastbootDevice
{
    if (!isset($_SESSION['fastboot_device'])) {
        throw new AdbException('Fastboot device not connected. Connect first.');
    }
    return $_SESSION['fastboot_device'];
}

function req(string $key): string
{
    return $_POST[$key] ?? $_GET[$key] ?? '';
}

function respond(array $data): void
{
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function error(string $msg): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES);
    exit;
}
