<?php
/**
 * adb-php — Monkey event injection.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb    = AdbClient::create();
$device = $adb->getDevice('emulator-5554');

// Start Monkey server and connect
$monkey = $device->openMonkey(1080);

echo "Monkey connected. Injecting events...\n";

// Press Home
$monkey->keyPress('KEYCODE_HOME');
sleep(1);

// Tap the centre of a 1080p screen
$monkey->touch(540, 960);
sleep(1);

// Swipe: touch down → move → up
$monkey->touchDown(540, 1200);
for ($y = 1200; $y > 400; $y -= 100) {
    $monkey->touchMove(540, $y);
    usleep(20000);
}
$monkey->touchUp(540, 400);

// Type some text (requires a focused text field)
$monkey->type('Hello from adb-php!');

$monkey->quit();
echo "Done.\n";
