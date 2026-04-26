<?php

declare(strict_types=1);

namespace AdbPhp\Transfers;

/**
 * Represents an in-progress or completed file push operation.
 *
 * Mirrors `PushTransfer` from adbkit.
 *
 * PHP 8.3: typed properties throughout.
 *
 * @since PHP 8.3
 */
final class PushTransfer
{
    private bool     $cancelled        = false;
    private int      $bytesTransferred = 0;
    private ?callable $progressCallback = null;

    // -------------------------------------------------------------------------
    // Progress
    // -------------------------------------------------------------------------

    /**
     * Register a progress callback.
     *
     * @param callable $callback `function(int $bytesTransferred): void`
     */
    public function onProgress(callable $callback): static
    {
        $this->progressCallback = $callback;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Cancel
    // -------------------------------------------------------------------------

    /**
     * Request cancellation of the transfer.
     * The transport checks {@see isCancelled()} between chunks.
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    // -------------------------------------------------------------------------
    // Internal (called by SyncService)
    // -------------------------------------------------------------------------

    /** @internal */
    public function addBytes(int $bytes): void
    {
        $this->bytesTransferred += $bytes;
        if ($this->progressCallback !== null) {
            ($this->progressCallback)($this->bytesTransferred);
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getBytesTransferred(): int
    {
        return $this->bytesTransferred;
    }
}
