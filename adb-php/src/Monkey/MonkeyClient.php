<?php

declare(strict_types=1);

namespace AdbPhp\Monkey;

use AdbPhp\Exceptions\AdbException;

/**
 * Client for the Android Monkey UI/application exerciser event injector.
 *
 * Mirrors `openMonkey()` from adbkit.
 *
 * Monkey commands are plain-text lines sent over TCP:
 *   touch down <x> <y>  |  touch up <x> <y>  |  touch move <x> <y>
 *   key down <code>     |  key up <code>
 *   trackball <dx> <dy> |  sleep <ms>
 *   type <string>       |  flip open | flip closed
 *   quit                |  done
 *
 * PHP 8.3: typed class constants, named constructor arguments.
 *
 * @since PHP 8.3
 */
final class MonkeyClient
{
    /** Default Monkey server port. */
    public const int DEFAULT_PORT    = 1080;

    /** Default socket timeout (ms). */
    public const int DEFAULT_TIMEOUT = 5000;

    /** @var resource|null */
    private mixed $socket = null;

    public function __construct(
        private readonly string $host    = '127.0.0.1',
        private readonly int    $port    = self::DEFAULT_PORT,
        private readonly int    $timeout = self::DEFAULT_TIMEOUT,
    ) {}

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Connect to the Monkey server.
     *
     * @throws AdbException
     */
    public function connect(): void
    {
        $errno  = 0;
        $errstr = '';

        $sock = @fsockopen(
            hostname:      $this->host,
            port:          $this->port,
            error_code:    $errno,
            error_message: $errstr,
            timeout:       $this->timeout / 1000.0,
        );

        if ($sock === false) {
            throw new AdbException(
                "Cannot connect to Monkey at {$this->host}:{$this->port}: [{$errno}] {$errstr}",
            );
        }

        stream_set_timeout($sock, 0, $this->timeout * 1000);
        $this->socket = $sock;
    }

    // -------------------------------------------------------------------------
    // Raw command
    // -------------------------------------------------------------------------

    /**
     * Send a raw Monkey command and return the response line.
     *
     * @throws AdbException
     */
    public function send(string $command): string
    {
        $this->assertConnected();
        fwrite($this->socket, $command . "\n");
        $response = fgets($this->socket);
        if ($response === false) {
            throw new AdbException('Monkey server closed the connection.');
        }
        return rtrim($response, "\r\n");
    }

    // -------------------------------------------------------------------------
    // Touch events
    // -------------------------------------------------------------------------

    /** Tap at (x, y) — sends touch down then touch up. */
    public function touch(int $x, int $y): void
    {
        $this->touchDown($x, $y);
        $this->touchUp($x, $y);
    }

    /** Touch-down event at (x, y). */
    public function touchDown(int $x, int $y): void
    {
        $this->send("touch down {$x} {$y}");
    }

    /** Touch-up event at (x, y). */
    public function touchUp(int $x, int $y): void
    {
        $this->send("touch up {$x} {$y}");
    }

    /** Touch-move event to (x, y). */
    public function touchMove(int $x, int $y): void
    {
        $this->send("touch move {$x} {$y}");
    }

    // -------------------------------------------------------------------------
    // Key events
    // -------------------------------------------------------------------------

    /** Press and release a key. */
    public function keyPress(int|string $keycode): void
    {
        $this->keyDown($keycode);
        $this->keyUp($keycode);
    }

    /** Key-down event. */
    public function keyDown(int|string $keycode): void
    {
        $this->send("key down {$keycode}");
    }

    /** Key-up event. */
    public function keyUp(int|string $keycode): void
    {
        $this->send("key up {$keycode}");
    }

    // -------------------------------------------------------------------------
    // Other events
    // -------------------------------------------------------------------------

    /** Trackball movement. */
    public function trackball(int $dx, int $dy): void
    {
        $this->send("trackball {$dx} {$dy}");
    }

    /** Sleep for $ms milliseconds on the device. */
    public function sleep(int $ms): void
    {
        $this->send("sleep {$ms}");
    }

    /** Type a string (requires a focused text field). */
    public function type(string $text): void
    {
        $this->send("type {$text}");
    }

    /** Flip display open. */
    public function flipOpen(): void
    {
        $this->send('flip open');
    }

    /** Flip display closed. */
    public function flipClosed(): void
    {
        $this->send('flip closed');
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /** Gracefully quit the Monkey server and close the socket. */
    public function quit(): void
    {
        if ($this->socket !== null) {
            @fwrite($this->socket, "quit\n");
        }
        $this->disconnect();
    }

    /** Close the TCP socket without sending quit. */
    public function disconnect(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    // -------------------------------------------------------------------------

    private function assertConnected(): void
    {
        if ($this->socket === null) {
            throw new AdbException('MonkeyClient: not connected. Call connect() first.');
        }
    }
}
