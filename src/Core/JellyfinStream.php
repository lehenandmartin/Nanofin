<?php

declare(strict_types=1);

namespace Nanofin\Core;

use Psr\Http\Message\StreamInterface;

/**
 * A PSR-7 StreamInterface that streams a Jellyfin download on-the-fly.
 *
 * Slim writes the response body by calling read() or __toString().
 * For large video files we must NOT buffer — we call streamDownload()
 * which echoes chunks directly, then return an empty string to Slim.
 *
 * This class satisfies the PSR-7 contract without holding the file in memory.
 */
final class JellyfinStream implements StreamInterface
{
    private bool $consumed = false;

    public function __construct(
        private readonly JellyfinService $jellyfin,
        private readonly string          $itemId,
    ) {}

    public function __toString(): string
    {
        if ($this->consumed) {
            return '';
        }
        $this->consumed = true;

        // Disable output buffering layers before streaming
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        try {
            $this->jellyfin->streamDownload($this->itemId);
        } catch (\Throwable) {
            // Stream already started — nothing we can do about headers
        }

        return '';
    }

    // ── StreamInterface stubs ─────────────────────────────────────
    // Slim only ever calls __toString() or write() for response bodies;
    // the remaining methods are required by the interface.

    public function close(): void {}
    public function detach(): mixed { return null; }
    public function getSize(): ?int { return null; }
    public function tell(): int { return 0; }
    public function eof(): bool { return $this->consumed; }
    public function isSeekable(): bool { return false; }
    public function seek(int $offset, int $whence = SEEK_SET): void {}
    public function rewind(): void {}
    public function isWritable(): bool { return false; }
    public function write(string $string): int { return 0; }
    public function isReadable(): bool { return true; }

    public function read(int $length): string
    {
        return $this->__toString();
    }

    public function getContents(): string
    {
        return $this->__toString();
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
