<?php

declare(strict_types=1);

namespace AdbPhp\Tests\Support;

use AdbPhp\Exceptions\ConnectionException;
use AdbPhp\Protocol\AdbSocket;

/**
 * In-process fake ADB socket for unit testing.
 *
 * Uses a pair of PHP stream wrappers (memory streams) to simulate
 * bidirectional TCP I/O without touching the network.
 *
 * Usage:
 *   $mock = MockAdbSocket::create();
 *   $mock->queueServerResponse("OKAY");        // raw bytes the server will send
 *   $mock->queueServerResponse("DATA00000004ABCD");
 *   // ... use $mock->socket as AdbSocket in tested code
 *   $sent = $mock->getSentData();              // what the code wrote
 */
final class MockAdbSocket
{
    /** @var list<string> */
    private array $sendQueue    = [];
    /** @var list<string> */
    private array $receiveQueue = [];
    /** @var list<string> */
    private array $sentLog      = [];

    public readonly AdbSocket $socket;

    private function __construct()
    {
        // We construct a real AdbSocket but never open it;
        // instead we override its internals via stream injection.
        $this->socket = $this->buildSocket();
    }

    public static function create(): self
    {
        return new self();
    }

    // -------------------------------------------------------------------------
    // Control API
    // -------------------------------------------------------------------------

    /** Queue a raw byte string the fake server will return on the next read. */
    public function queueServerResponse(string $data): void
    {
        $this->receiveQueue[] = $data;
    }

    /**
     * Queue a proper ADB OKAY status + optional length-prefixed body.
     * Equivalent to a successful `readStatus()` + `readLengthPrefixed()`.
     */
    public function queueOkay(string $body = ''): void
    {
        $hex = sprintf('%04X', strlen($body));
        $this->receiveQueue[] = 'OKAY' . $hex . $body;
    }

    /**
     * Queue an ADB FAIL response.
     */
    public function queueFail(string $message): void
    {
        $hex = sprintf('%04X', strlen($message));
        $this->receiveQueue[] = 'FAIL' . $hex . $message;
    }

    /** Return all data the tested code wrote to the socket. */
    public function getSentData(): string
    {
        return implode('', $this->sentLog);
    }

    /** Return sent data split by logical sends. */
    public function getSentChunks(): array
    {
        return $this->sentLog;
    }

    public function reset(): void
    {
        $this->sendQueue    = [];
        $this->receiveQueue = [];
        $this->sentLog      = [];
    }

    // -------------------------------------------------------------------------
    // Internal: inject fake stream into AdbSocket via reflection
    // -------------------------------------------------------------------------

    private function buildSocket(): AdbSocket
    {
        $mock = $this;

        // Create a PHP stream wrapper pair using temp memory streams
        $readStream  = fopen('php://memory', 'r+b');
        $writeStream = fopen('php://memory', 'r+b');

        // We'll use a "tee" stream that intercepts reads from receiveQueue
        // and writes to sentLog. Since PHP doesn't support stream proxies
        // natively without extensions, we use a pair of temp:// streams
        // and control them via StreamWrapper.
        //
        // Simpler approach: use a real AdbSocket with a socketpair simulation
        // via two php://temp streams, wired together through our own wrapper.

        $socket = new AdbSocket('127.0.0.1', 5037, 5000);

        // Inject our controlled resource via Reflection
        $ref  = new \ReflectionClass(AdbSocket::class);
        $prop = $ref->getProperty('socket');
        $prop->setAccessible(true);

        // Build a controlled stream using our MockStream wrapper
        $stream = MockStream::createPair($mock);
        $prop->setValue($socket, $stream);

        return $socket;
    }

    // -------------------------------------------------------------------------
    // Called by MockStream to dequeue / enqueue
    // -------------------------------------------------------------------------

    public function dequeueReceive(int $maxLen): string
    {
        if (empty($this->receiveQueue)) {
            return '';
        }
        $data = array_shift($this->receiveQueue);
        return substr($data, 0, $maxLen);
    }

    public function logSend(string $data): void
    {
        $this->sentLog[] = $data;
    }

    public function hasData(): bool
    {
        return !empty($this->receiveQueue);
    }
}
