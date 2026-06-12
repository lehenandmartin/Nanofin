<?php

declare(strict_types=1);

namespace Nanofin\Core;

use RuntimeException;

/**
 * Handles all communication with the Jellyfin API.
 *
 * Security contract: the Jellyfin API key is NEVER sent to the browser.
 * Every request is server-side only.  Downloads and posters are proxied
 * through PHP — the client never sees the Jellyfin URL or API key.
 */
final class JellyfinService
{
    private string  $baseUrl;
    private string  $apiKey;
    private ?string $cachedUserId   = null;
    private int     $posterCacheDays;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int    $posterCacheDays = 7,
    ) {
        $this->baseUrl         = rtrim($baseUrl, '/');
        $this->apiKey          = $apiKey;
        $this->posterCacheDays = max(1, $posterCacheDays);
    }

    // ── Connection test ────────────────────────────────────────────

    /**
     * Verify that the Jellyfin URL and API key are valid.
     * Returns true on success, throws RuntimeException with a human-readable
     * message on failure.
     */
    /**
     * Lightweight server reachability check — no API key required.
     * Calls /System/Info/Public (unauthenticated) and verifies the response
     * looks like a Jellyfin server.
     * Returns the ServerName string on success, or null on failure.
     */
    public function pingServer(): ?string
    {
        if ($this->baseUrl === '') {
            return null;
        }

        $json = $this->rawGet($this->baseUrl . '/System/Info/Public');
        if ($json === null) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return isset($data['ServerName']) ? (string) $data['ServerName'] : null;
    }

    public function testConnection(): bool
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('Jellyfin URL and API key must be configured.');
        }

        // /System/Info requires a valid API key (unlike /System/Info/Public
        // which is unauthenticated and would always return success).
        try {
            $data = $this->get('/System/Info');
        } catch (RuntimeException $e) {
            // Distinguish "server unreachable" from "API key rejected"
            $msg = $e->getMessage();
            if (str_contains($msg, '401') || str_contains($msg, 'Unauthorized')) {
                throw new RuntimeException(
                    'Invalid API key — Jellyfin returned 401 Unauthorized.',
                    0,
                    $e,
                );
            }
            throw new RuntimeException(
                'Unable to reach Jellyfin server: ' . $msg,
                0,
                $e,
            );
        }

        if (!isset($data['ServerName'])) {
            throw new RuntimeException(
                'Unexpected Jellyfin API response — is the URL correct?'
            );
        }

        return true;
    }

    // ── User ID resolution ─────────────────────────────────────────

    /**
     * Return the first admin user ID, resolved once and cached for 24 h.
     */
    public function getUserId(): string
    {
        if ($this->cachedUserId !== null) {
            return $this->cachedUserId;
        }

        $cacheFile = $this->cacheFilePath('user_id');

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = trim((string) file_get_contents($cacheFile));
            if ($cached !== '') {
                $this->cachedUserId = $cached;
                return $this->cachedUserId;
            }
        }

        $users = $this->get('/Users');

        // Find the first admin user, fallback to first user
        $userId = null;
        foreach ($users as $u) {
            if (!empty($u['Policy']['IsAdministrator'])) {
                $userId = $u['Id'];
                break;
            }
        }
        $userId ??= $users[0]['Id'] ?? null;

        if ($userId === null) {
            throw new RuntimeException('Unable to resolve Jellyfin user ID — no users found.');
        }

        $this->cachedUserId = $userId;
        file_put_contents($cacheFile, $userId, LOCK_EX);

        return $this->cachedUserId;
    }

    /** Invalidate the cached user ID (call after changing API credentials). */
    public function invalidateUserIdCache(): void
    {
        $this->cachedUserId = null;
        $cacheFile = $this->cacheFilePath('user_id');
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    // ── Library ────────────────────────────────────────────────────

    /**
     * Return a paginated list of movies and/or TV shows.
     *
     * @param array{
     *   IncludeItemTypes?: string,
     *   SortBy?: string,
     *   SortOrder?: string,
     *   Limit?: int,
     *   StartIndex?: int,
     *   SearchTerm?: string,
     * } $options
     * @return array{Items: list<array<string,mixed>>, TotalRecordCount: int}
     */
    public function getItems(array $options = []): array
    {
        $userId = $this->getUserId();

        $params = array_merge([
            'IncludeItemTypes' => 'Movie,Series',
            'Recursive'        => 'true',
            'Fields'           => 'Overview,Genres,ProductionYear,CommunityRating,RunTimeTicks,DateCreated,MediaSources',
            'ImageTypeLimit'   => '1',
            'EnableImageTypes' => 'Primary',
            'SortBy'           => 'SortName',
            'SortOrder'        => 'Ascending',
            'Limit'            => '50',
            'StartIndex'       => '0',
        ], $options);

        $result = $this->get("/Users/{$userId}/Items", $params);

        return [
            'Items'            => $result['Items']            ?? [],
            'TotalRecordCount' => (int) ($result['TotalRecordCount'] ?? 0),
        ];
    }

    /**
     * Return a single item (movie or series) with full metadata.
     *
     * @return array<string, mixed>
     */
    public function getItem(string $itemId): array
    {
        $userId = $this->getUserId();
        $item   = $this->get("/Users/{$userId}/Items/{$itemId}", [
            'Fields' => 'Overview,Genres,ProductionYear,CommunityRating,RunTimeTicks,DateCreated,MediaSources,MediaStreams,People',
        ]);

        if (empty($item['Id'])) {
            throw new RuntimeException("Item {$itemId} not found in Jellyfin.");
        }

        return $item;
    }

    /**
     * Return seasons for a TV series, ordered by index.
     *
     * @return list<array<string, mixed>>
     */
    public function getSeasons(string $seriesId): array
    {
        $userId = $this->getUserId();
        $result = $this->get("/Shows/{$seriesId}/Seasons", [
            'userId' => $userId,
            'Fields' => 'Overview,ProductionYear',
        ]);

        $seasons = $result['Items'] ?? [];
        usort($seasons, fn($a, $b) => ($a['IndexNumber'] ?? 0) <=> ($b['IndexNumber'] ?? 0));

        return $seasons;
    }

    /**
     * Return episodes for a given season, ordered by episode index.
     *
     * @return list<array<string, mixed>>
     */
    public function getEpisodes(string $seriesId, string $seasonId): array
    {
        $userId = $this->getUserId();
        $result = $this->get("/Shows/{$seriesId}/Episodes", [
            'userId'   => $userId,
            'seasonId' => $seasonId,
            'Fields'   => 'Overview,MediaSources,MediaStreams,RunTimeTicks',
        ]);

        $episodes = $result['Items'] ?? [];
        usort($episodes, fn($a, $b) => ($a['IndexNumber'] ?? 0) <=> ($b['IndexNumber'] ?? 0));

        return $episodes;
    }

    // ── Poster proxy ───────────────────────────────────────────────

    /**
     * Return the local path for an item's poster, downloading & caching it if
     * needed.  Returns null when no poster is available.
     */
    public function getPosterPath(string $itemId, int $maxWidth = 400): ?string
    {
        $cacheDir  = $this->posterCacheDir();
        $cachePath = $cacheDir . '/' . $itemId . '.jpg';
        $maxAge    = $this->posterCacheDays * 86400;

        // Serve from cache if fresh enough
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < $maxAge) {
            return $cachePath;
        }

        // Fetch from Jellyfin
        $url  = $this->baseUrl
            . "/Items/{$itemId}/Images/Primary"
            . "?maxWidth={$maxWidth}&quality=90&api_key=" . urlencode($this->apiKey);

        $data = $this->rawGet($url);

        if ($data === null) {
            // Remove stale cache if present
            if (file_exists($cachePath)) {
                unlink($cachePath);
            }
            return null;
        }

        if (file_put_contents($cachePath, $data, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write poster cache: {$cachePath}");
        }

        return $cachePath;
    }

    /**
     * Delete cached posters older than $days days.
     * Returns the number of files deleted.
     */
    public function cleanPosterCache(?int $days = null): int
    {
        $days    ??= $this->posterCacheDays;
        $maxAge    = $days * 86400;
        $cacheDir  = $this->posterCacheDir();
        $deleted   = 0;

        foreach (glob($cacheDir . '/*.jpg') ?: [] as $file) {
            if ((time() - filemtime($file)) > $maxAge) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Return the number of files currently in the poster cache.
     */
    public function posterCacheCount(): int
    {
        return count(glob($this->posterCacheDir() . '/*.jpg') ?: []);
    }

    // ── Download stream ────────────────────────────────────────────

    /**
     * Stream a Jellyfin file directly to the PHP output buffer.
     *
     * Call this from the download controller only.
     * The response headers (Content-Type, Content-Disposition, Content-Length)
     * must be set by the caller before invoking this method.
     *
     * @throws RuntimeException on network failure
     */
    public function streamDownload(string $itemId): void
    {
        $url = $this->baseUrl . "/Items/{$itemId}/Download?api_key=" . urlencode($this->apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION  => static function ($ch, $data): int {
                echo $data;
                return strlen($data);
            },
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 0,        // no timeout for large files
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $ok    = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        // curl_close() is a no-op since PHP 8.0 and deprecated in PHP 8.5

        if ($ok === false || $errno !== 0) {
            throw new RuntimeException(
                "Download stream failed (curl #{$errno}: {$error})."
            );
        }
    }

    /**
     * Return the file name and content-type for a Jellyfin item,
     * derived from its MediaSources metadata.
     *
     * @return array{filename: string, contentType: string, size: int}
     */
    public function getDownloadMeta(string $itemId): array
    {
        $item    = $this->getItem($itemId);
        $sources = $item['MediaSources'] ?? [];
        $source  = $sources[0] ?? [];

        $path        = $source['Path'] ?? '';
        $ext         = $path !== '' ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : 'mkv';
        $size        = (int) ($source['Size'] ?? 0);
        $title       = preg_replace('/[^\w\s\-.]/', '', $item['Name'] ?? 'download');
        $year        = $item['ProductionYear'] ?? '';
        $filename    = trim($title . ($year ? " ({$year})" : '')) . '.' . $ext;

        $mimeMap = [
            'mkv'  => 'video/x-matroska',
            'mp4'  => 'video/mp4',
            'avi'  => 'video/x-msvideo',
            'mov'  => 'video/quicktime',
            'wmv'  => 'video/x-ms-wmv',
            'ts'   => 'video/mp2t',
            'm2ts' => 'video/mp2t',
        ];

        return [
            'filename'    => $filename,
            'contentType' => $mimeMap[$ext] ?? 'application/octet-stream',
            'size'        => $size,
        ];
    }

    // ── HTTP helpers ───────────────────────────────────────────────

    /**
     * @param array<string, string|int|bool> $params
     * @return array<mixed>
     * @throws RuntimeException
     */
    private function get(string $path, array $params = []): array
    {
        if ($this->apiKey !== '') {
            $params['api_key'] = $this->apiKey;
        }

        $url  = $this->baseUrl . $path;
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $json = $this->rawGet($url);

        if ($json === null) {
            throw new RuntimeException("Jellyfin API request failed: {$path}");
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Jellyfin returned invalid JSON for {$path}.", 0, $e);
        }

        // Jellyfin wraps most list responses in {Items:[...], TotalRecordCount:N}
        // but some endpoints return a bare array — normalise both.
        return is_array($data) ? $data : [];
    }

    private function rawGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Nanofin/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno    = curl_errno($ch);
        // curl_close() is a no-op since PHP 8.0 and deprecated in PHP 8.5

        if ($response === false || $errno !== 0 || $httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return (string) $response;
    }

    // ── Path helpers ───────────────────────────────────────────────

    private function posterCacheDir(): string
    {
        return defined('POSTER_CACHE_DIR')
            ? POSTER_CACHE_DIR
            : dirname(__DIR__, 2) . '/cache/posters';
    }

    private function cacheFilePath(string $name): string
    {
        return defined('CACHE_DIR')
            ? CACHE_DIR . '/' . $name
            : dirname(__DIR__, 2) . '/cache/' . $name;
    }
}
