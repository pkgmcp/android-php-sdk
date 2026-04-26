<?php
/**
 * adb-php — Read CPU stats from /proc/stat.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AdbPhp\AdbClient;

$adb    = AdbClient::create();
$device = $adb->getDevice('emulator-5554');

$stat = $device->openProcStat();

$cpu = $stat->cpu;
printf("CPU — total: %d | active: %d | idle: %d\n", $cpu->total(), $cpu->active(), $cpu->idle);
printf("CPU usage: %.1f%%\n", $cpu->total() > 0 ? ($cpu->active() / $cpu->total() * 100) : 0);

echo "\nPer-core:\n";
foreach ($stat->cores as $name => $core) {
    printf("  %-6s active: %d / total: %d\n", $name, $core->active(), $core->total());
}
