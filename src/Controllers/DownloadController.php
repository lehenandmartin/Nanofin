<?php

declare(strict_types=1);

namespace Nanofin\Controllers;

use Nanofin\Core\JellyfinService;
use Nanofin\Core\JellyfinStream;
use Nanofin\Core\Translator;
use Nanofin\Models\DownloadModel;
use Nanofin\Models\SettingsModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Stream;
use Slim\Views\Twig;

final class DownloadController
{
    public function __construct(
        private readonly Twig            $twig,
        private readonly JellyfinService $jellyfin,
        private readonly DownloadModel   $downloads,
        private readonly SettingsModel   $settings,
        private readonly Translator      $translator,
    ) {}

    // ── GET /poster/{id} ──────────────────────────────────────────

    /**
     * Serve a cached poster image, fetching it from Jellyfin if needed.
     * The Jellyfin URL and API key are never sent to the browser.
     */
    public function poster(Request $request, Response $response, array $args): Response
    {
        $itemId = $this->sanitizeId($args['id'] ?? '');

        if ($itemId === '') {
            return $response->withStatus(400);
        }

        try {
            $posterPath = $this->jellyfin->getPosterPath($itemId);
        } catch (\Throwable) {
            return $this->servePlaceholder($response);
        }

        if ($posterPath === null || !file_exists($posterPath)) {
            return $this->servePlaceholder($response);
        }

        $size   = (int) filesize($posterPath);
        $stream = new Stream(fopen($posterPath, 'rb'));
        $maxAge = $this->settings->getInt('poster_cache_days', 7) * 86400;

        return $response
            ->withBody($stream)
            ->withHeader('Content-Type',             'image/jpeg')
            ->withHeader('Content-Length',           (string) $size)
            ->withHeader('Cache-Control',            "public, max-age={$maxAge}, immutable")
            ->withHeader('X-Content-Type-Options',   'nosniff');
    }

    // ── GET /download/{id} ────────────────────────────────────────

    /**
     * Stream a Jellyfin file to the browser.
     *
     * Content access is enforced server-side:
     *   movies  → requires content_access ∈ {movies, both}
     *   episodes → requires content_access ∈ {shows, both}
     * In public mode (no session user) all content is accessible.
     */
    public function download(Request $request, Response $response, array $args): Response
    {
        $itemId = $this->sanitizeId($args['id'] ?? '');

        if ($itemId === '') {
            return $response->withStatus(400);
        }

        // ── Fetch item metadata (single Jellyfin call) ──────────
        try {
            $item = $this->jellyfin->getItem($itemId);
        } catch (\Throwable) {
            return $this->twig->render(
                $response->withStatus(404),
                'errors/404.twig',
            );
        }

        // ── Content-access enforcement ──────────────────────────
        $user   = $_SESSION['user'] ?? null;
        $denied = $this->isAccessDenied($item['Type'] ?? '', $user);

        if ($denied) {
            return $this->twig->render(
                $response->withStatus(403),
                'errors/403.twig',
            );
        }

        // ── Log the download ────────────────────────────────────
        $logType     = ($item['Type'] ?? '') === 'Episode' ? 'episode' : 'movie';
        $displayTitle = $this->buildDisplayTitle($item);

        $this->downloads->log(
            userId:    $user !== null ? (int) $user['id'] : null,
            itemId:    $itemId,
            itemTitle: $displayTitle,
            itemType:  $logType,
        );

        // ── Build download metadata from already-fetched item ───
        $meta = $this->buildMeta($item);

        // ── Stream headers ──────────────────────────────────────
        $response = $response
            ->withHeader('Content-Type',        $meta['contentType'])
            ->withHeader('Content-Disposition', 'attachment; filename*=UTF-8\'\'' . rawurlencode($meta['filename']))
            ->withHeader('Cache-Control',       'no-store, no-cache')
            ->withHeader('X-Accel-Buffering',   'no');   // disable nginx proxy buffering

        if ($meta['size'] > 0) {
            $response = $response->withHeader('Content-Length', (string) $meta['size']);
        }

        // ── Stream from Jellyfin ────────────────────────────────
        return $response->withBody(new JellyfinStream($this->jellyfin, $itemId));
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Allow only alphanumeric Jellyfin IDs (UUIDs without dashes accepted too).
     */
    private function sanitizeId(string $raw): string
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $raw);
    }

    /**
     * Return true if the logged-in user is not allowed to download this item type.
     *
     * @param array{id:int,role:string,content_access:string}|null $user
     */
    private function isAccessDenied(string $itemType, ?array $user): bool
    {
        // Public mode (no session) or admin → always allowed
        if ($user === null) {
            return false;
        }

        $access    = $user['content_access'] ?? 'both';
        $isMovie   = $itemType === 'Movie';
        $isEpisode = $itemType === 'Episode';

        if ($isMovie && $access === 'shows') {
            return true;
        }
        if ($isEpisode && $access === 'movies') {
            return true;
        }

        return false;
    }

    /**
     * Build a human-readable log title from a Jellyfin item.
     *
     * Movies  → "The Dark Knight"
     * Episodes → "Breaking Bad S02E05 - Breakage"
     *
     * @param array<string, mixed> $item
     */
    private function buildDisplayTitle(array $item): string
    {
        $title = $item['Name'] ?? 'Unknown';

        if (($item['Type'] ?? '') === 'Episode') {
            $s        = str_pad((string) ($item['ParentIndexNumber'] ?? 1), 2, '0', STR_PAD_LEFT);
            $e        = str_pad((string) ($item['IndexNumber']       ?? 1), 2, '0', STR_PAD_LEFT);
            $showName = $item['SeriesName'] ?? '';
            return trim("{$showName} - S{$s}E{$e} - {$title}");
        }

        return $title;
    }

    /**
     * Build filename, content-type, and file size from a Jellyfin item array.
     * Avoids a second API call by reusing the item already fetched.
     *
     * @param  array<string, mixed> $item
     * @return array{filename: string, contentType: string, size: int}
     */
    private function buildMeta(array $item): array
    {
        $sources = $item['MediaSources'] ?? [];
        $source  = $sources[0] ?? [];

        $path = $source['Path'] ?? '';
        $ext  = $path !== '' ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : 'mkv';
        $size = (int) ($source['Size'] ?? 0);

        // Build a clean filename
        $title = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $item['Name'] ?? 'download');
        $title = trim($title);
        $year  = $item['ProductionYear'] ?? '';

        // For episodes: "Show S01E03 - Title.mkv"
        if (($item['Type'] ?? '') === 'Episode') {
            $s        = str_pad((string) ($item['ParentIndexNumber'] ?? 1), 2, '0', STR_PAD_LEFT);
            $e        = str_pad((string) ($item['IndexNumber']       ?? 1), 2, '0', STR_PAD_LEFT);
            $showName = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $item['SeriesName'] ?? '');
            $filename = trim("{$showName} S{$s}E{$e} - {$title}") . '.' . $ext;
        } else {
            $filename = trim($title . ($year ? " ({$year})" : '')) . '.' . $ext;
        }

        // Sanitise for HTTP header
        $filename = str_replace(['"', "\n", "\r"], '', $filename);

        $mimeMap = [
            'mkv'  => 'video/x-matroska',
            'mp4'  => 'video/mp4',
            'avi'  => 'video/x-msvideo',
            'mov'  => 'video/quicktime',
            'wmv'  => 'video/x-ms-wmv',
            'ts'   => 'video/mp2t',
            'm2ts' => 'video/mp2t',
            'flac' => 'audio/flac',
            'mp3'  => 'audio/mpeg',
        ];

        return [
            'filename'    => $filename,
            'contentType' => $mimeMap[$ext] ?? 'application/octet-stream',
            'size'        => $size,
        ];
    }

    /**
     * Return a minimal SVG placeholder when no poster is available.
     */
    private function servePlaceholder(Response $response): Response
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="600" viewBox="0 0 400 600">'
             . '<rect width="400" height="600" fill="#1a1d27"/>'
             . '<rect x="150" y="220" width="100" height="80" rx="8" fill="#2c3150"/>'
             . '<polygon points="170,245 195,260 170,275" fill="#6c63ff"/>'
             . '<text x="200" y="340" text-anchor="middle" fill="#8b8fa8" '
             . 'font-family="sans-serif" font-size="14">No poster</text>'
             . '</svg>';

        $response->getBody()->write($svg);

        return $response
            ->withHeader('Content-Type',  'image/svg+xml')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }
}
