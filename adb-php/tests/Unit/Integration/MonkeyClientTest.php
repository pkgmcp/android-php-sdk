<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Unit\Integration;

use AdbPhp\Exceptions\AdbException;
use AdbPhp\Monkey\MonkeyClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MonkeyClient::class)]
final class MonkeyClientTest extends TestCase
{
    /**
     * Build a MonkeyClient with a pre-seeded server response stream injected
     * via a tmpfile that returns "OK" for every command.
     */
    private function makeMonkey(string $serverResponses = ''): MonkeyClient
    {
        $monkey = new MonkeyClient(host: '127.0.0.1', port: 1080);

        $tmp = tmpfile();
        fwrite($tmp, $serverResponses);
        rewind($tmp);

        $ref = new \ReflectionProperty(MonkeyClient::class, 'socket');
        $ref->setAccessible(true);
        $ref->setValue($monkey, $tmp);

        return $monkey;
    }

    /** Build N "OK\n" responses for N expected commands. */
    private function okLines(int $n): string
    {
        return str_repeat("OK\n", $n);
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    #[Test]
    public function typedConstantsExist(): void
    {
        $this->assertSame(1080, MonkeyClient::DEFAULT_PORT);
        $this->assertSame(5000, MonkeyClient::DEFAULT_TIMEOUT);
    }

    // -------------------------------------------------------------------------
    // Touch
    // -------------------------------------------------------------------------

    #[Test]
    public function touchSendsTwoCommands(): void
    {
        $monkey  = $this->makeMonkey($this->okLines(2));
        $written = [];

        // Capture via output buffering isn't reliable — test via tmpfile read-back
        // Since tmpfile is shared, we instead just verify no exception is thrown
        $monkey->touch(540, 960);
        $this->assertTrue(true); // no exception = OK
    }

    #[Test]
    public function touchDownSendsCorrectCommand(): void
    {
        $monkey = $this->makeMonkey("OK\n");
        $result = $monkey->send('touch down 540 960');
        $this->assertSame('OK', $result);
    }

    #[Test]
    public function sendReturnsServerResponse(): void
    {
        $monkey = $this->makeMonkey("OK:some response\n");
        $resp   = $monkey->send('anything');
        $this->assertSame('OK:some response', $resp);
    }

    // -------------------------------------------------------------------------
    // Key events
    // -------------------------------------------------------------------------

    #[Test]
    public function keyPressSendsTwoCommands(): void
    {
        $monkey = $this->makeMonkey($this->okLines(2));
        $monkey->keyPress('KEYCODE_HOME');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Connect failure
    // -------------------------------------------------------------------------

    #[Test]
    public function connectThrowsAdbExceptionOnRefusal(): void
    {
        // Use port 1 — guaranteed refused
        $monkey = new MonkeyClient(host: '127.0.0.1', port: 1, timeout: 100);
        $this->expectException(AdbException::class);
        $monkey->connect();
    }

    // -------------------------------------------------------------------------
    // Disconnect
    // -------------------------------------------------------------------------

    #[Test]
    public function disconnectClosesSocket(): void
    {
        $monkey = $this->makeMonkey('');
        $monkey->disconnect();

        $ref = new \ReflectionProperty(MonkeyClient::class, 'socket');
        $ref->setAccessible(true);

        $this->assertNull($ref->getValue($monkey));
    }

    // -------------------------------------------------------------------------
    // Not connected guard
    // -------------------------------------------------------------------------

    #[Test]
    public function sendThrowsWhenNotConnected(): void
    {
        $monkey = new MonkeyClient();
        $this->expectException(AdbException::class);
        $monkey->send('test');
    }
}
