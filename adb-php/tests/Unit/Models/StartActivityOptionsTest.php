<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Models;

use AdbPhp\Models\StartActivityOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartActivityOptions::class)]
final class StartActivityOptionsTest extends TestCase
{
    #[Test]
    public function defaultsAreCorrect(): void
    {
        $o = new StartActivityOptions();
        $this->assertFalse($o->wait);
        $this->assertFalse($o->debug);
        $this->assertFalse($o->force);
        $this->assertNull($o->action);
        $this->assertNull($o->data);
        $this->assertNull($o->mimeType);
        $this->assertNull($o->category);
        $this->assertNull($o->component);
        $this->assertNull($o->flags);
        $this->assertSame([], $o->extras);
    }

    #[Test]
    public function namedArgumentsWorkCorrectly(): void
    {
        $o = new StartActivityOptions(
            wait:      true,
            action:    'android.intent.action.VIEW',
            component: 'com.example/.MainActivity',
            extras:    ['key' => 'value'],
        );

        $this->assertTrue($o->wait);
        $this->assertSame('android.intent.action.VIEW', $o->action);
        $this->assertSame('com.example/.MainActivity', $o->component);
        $this->assertSame(['key' => 'value'], $o->extras);
    }

    #[Test]
    public function isReadonlyClass(): void
    {
        $r = new \ReflectionClass(StartActivityOptions::class);
        $this->assertTrue($r->isReadOnly());
    }
}
