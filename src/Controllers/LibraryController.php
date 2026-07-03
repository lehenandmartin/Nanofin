<?php

declare(strict_types=1);

namespace Nanofin\Controllers;

use Nanofin\Core\JellyfinService;
use Nanofin\Core\Translator;
use Nanofin\Models\SettingsModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class LibraryController
{
    // Mapping of OfficialRating strings (lowercase) to minimum age integers.
    // Unknown codes return null → permissive (content is shown).
    private const RATING_MAP = [
        'g' => 0, 'tv-y' => 0, 'tv-g' => 0, 'e' => 0,
        'u' => 0, 'tp' => 0, 'approved' => 0, 'passed' => 0,
        'fr-tp' => 0, 'fr-u' => 0, 'fr-na' => 0, 'tous publics' => 0,
        'pg' => 7, 'tv-pg' => 7, 'tv-y7' => 7,
        'public averti' => 10,
        'fr-10' => 10, '-10' => 10,
        '12' => 12, '12a' => 12, 'fr-12' => 12, '-12' => 12,
        'pg-13' => 13,
        'tv-14' => 14, '-14' => 14,
        '15' => 15, 'm' => 15, 'ma15+' => 15,
        'fr-16' => 16, '-16' => 16,
        'r' => 17,
        '18' => 18, 'r18' => 18, 'r18+' => 18,
        'nc-17' => 18, 'x' => 18, 'xxx' => 18, 'ao' => 18,
        'tv-ma' => 18, 'fr-18' => 18, '-18' => 18,
        'banned' => 99, // above every selectable limit: hidden for any restricted account
    ];

    // Jellyfin SortBy field per UI sort key
    private const SORT_MAP = [
        'title'  => ['SortBy' => 'SortName',        'SortOrder' => 'Ascending'],
        'year'   => ['SortBy' => 'ProductionYear',   'SortOrder' => 'Descending'],
        'added'  => ['SortBy' => 'DateCreated',      'SortOrder' => 'Descending'],
        'rating' => ['SortBy' => 'CommunityRating',  'SortOrder' => 'Descending'],
    ];

    // Jellyfin IncludeItemTypes per UI type filter
    private const TYPE_MAP = [
        'movie' => 'Movie',
        'show'  => 'Series',
        'all'   => 'Movie,Series',
    ];

    public function __construct(
        private readonly Twig            $twig,
        private readonly JellyfinService $jellyfin,
        private readonly SettingsModel   $settings,
        private readonly Translator      $translator,
    ) {}

    // ── GET / ─────────────────────────────────────────────────────

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $defaultSort = $this->settings->get('default_sort', 'added');
        $sort        = $this->validSort($params['sort'] ?? $defaultSort);
        $order  = $this->validOrder($params['order']   ?? '');
        $type   = $this->validType($params['type']     ?? 'all');
        $search = trim((string) ($params['q']          ?? ''));
        $page  = max(1, (int) ($params['page'] ?? 1));
        $rows  = max(1, $this->settings->getInt('grid_rows', 4));
        $limit = $rows * 6; // 6 = max columns (xl breakpoint); client trims to rows × actual_cols

        // Content access — restrict the type filter based on user rights
        $access = ($_SESSION['user']['content_access'] ?? 'both');
        [$type, $allowedTypes] = $this->applyAccess($type, $access);

        // Effective sort direction: explicit order param overrides the SORT_MAP default
        $jellyfinOrder  = $this->resolveOrder($sort, $order);
        $effectiveDir   = $jellyfinOrder === 'Ascending' ? 'asc' : 'desc';

        $ageLimit = ($_SESSION['user']['age_limit'] ?? null) !== null
            ? (int) $_SESSION['user']['age_limit']
            : null;

        // Build Jellyfin params
        $jellyfinParams = [
            'IncludeItemTypes' => self::TYPE_MAP[$type],
            'SortBy'           => self::SORT_MAP[$sort]['SortBy'],
            'SortOrder'        => $jellyfinOrder,
            'Limit'            => $limit,
            'StartIndex'       => ($page - 1) * $limit,
        ];

        if ($search !== '') {
            $jellyfinParams['SearchTerm'] = $search;
            // Jellyfin ignores SortBy when SearchTerm is set (relevance order)
        }

        // When an age limit is active, fetch the full library at once so PHP-side
        // pagination is accurate (no pages with fewer items than expected).
        if ($ageLimit !== null) {
            $jellyfinParams['Limit']      = 50000;
            $jellyfinParams['StartIndex'] = 0;
        }

        try {
            $result = $this->jellyfin->getItems($jellyfinParams);
        } catch (\Throwable $e) {
            return $this->twig->render($response, 'library/index.twig', [
                'error'        => $e->getMessage(),
                'items'        => [],
                'total'        => 0,
                'page'         => 1,
                'pages'        => 1,
                'sort'         => $sort,
                'order'        => $order,
                'effectiveDir' => $effectiveDir,
                'type'         => $type,
                'search'       => $search,
                'allowedTypes' => $allowedTypes,
            ]);
        }

        $allItems = array_map(fn($item) => $this->enrichItem($item), $result['Items']);

        if ($ageLimit !== null) {
            // Filter then paginate in PHP for accurate page counts
            $allItems = array_values(array_filter(
                $allItems,
                fn($i) => $this->passesAgeLimit($i['officialRating'] ?? null, $ageLimit),
            ));
            $total = count($allItems);
            $pages = $limit > 0 ? max(1, (int) ceil($total / $limit)) : 1;
            $page  = max(1, min($page, $pages));
            $items = array_slice($allItems, ($page - 1) * $limit, $limit);
        } else {
            $total = $result['TotalRecordCount'];
            $pages = $limit > 0 ? (int) ceil($total / $limit) : 1;
            $items = $allItems;
        }

        return $this->twig->render($response, 'library/index.twig', [
            'items'        => $items,
            'total'        => $total,
            'page'         => $page,
            'pages'        => $pages,
            'rows'         => $rows,
            'sort'         => $sort,
            'order'        => $order,
            'effectiveDir' => $effectiveDir,
            'type'         => $type,
            'search'       => $search,
            'allowedTypes' => $allowedTypes,
        ]);
    }

    // ── GET /movies/{id} ──────────────────────────────────────────

    public function showMovie(Request $request, Response $response, array $args): Response
    {
        $itemId = preg_replace('/[^a-zA-Z0-9]/', '', $args['id'] ?? '');

        try {
            $item = $this->jellyfin->getItem($itemId);
        } catch (\Throwable) {
            return $this->twig->render(
                $response->withStatus(404),
                'errors/404.twig',
            );
        }

        if (($item['Type'] ?? '') !== 'Movie') {
            return $response->withHeader('Location', app_url('/shows/' . $itemId))->withStatus(302);
        }

        // Content-access guard
        $access = $_SESSION['user']['content_access'] ?? 'both';
        if ($access === 'shows') {
            return $response->withStatus(403);
        }

        // Age-limit guard
        $ageLimit = ($_SESSION['user']['age_limit'] ?? null) !== null
            ? (int) $_SESSION['user']['age_limit']
            : null;
        if ($ageLimit !== null && !$this->passesAgeLimit($item['OfficialRating'] ?? null, $ageLimit)) {
            return $response->withStatus(403);
        }

        return $this->twig->render($response, 'library/movie.twig', [
            'item'     => $this->enrichItem($item),
            'duration' => $this->formatDuration((int) ($item['RunTimeTicks'] ?? 0)),
        ]);
    }

    // ── GET /shows/{id} ───────────────────────────────────────────

    public function showShow(Request $request, Response $response, array $args): Response
    {
        $itemId = preg_replace('/[^a-zA-Z0-9]/', '', $args['id'] ?? '');

        try {
            $item    = $this->jellyfin->getItem($itemId);
            $seasons = $this->jellyfin->getSeasons($itemId);
        } catch (\Throwable) {
            return $this->twig->render(
                $response->withStatus(404),
                'errors/404.twig',
            );
        }

        if (($item['Type'] ?? '') !== 'Series') {
            return $response->withHeader('Location', app_url('/movies/' . $itemId))->withStatus(302);
        }

        // Content-access guard
        $access = $_SESSION['user']['content_access'] ?? 'both';
        if ($access === 'movies') {
            return $response->withStatus(403);
        }

        // Age-limit guard
        $ageLimit = ($_SESSION['user']['age_limit'] ?? null) !== null
            ? (int) $_SESSION['user']['age_limit']
            : null;
        if ($ageLimit !== null && !$this->passesAgeLimit($item['OfficialRating'] ?? null, $ageLimit)) {
            return $response->withStatus(403);
        }

        // Load episodes for each season
        $seasonsWithEpisodes = [];
        foreach ($seasons as $season) {
            $episodes = $this->jellyfin->getEpisodes($itemId, $season['Id']);
            $episodes = array_map(fn($ep) => $this->enrichEpisode($ep), $episodes);
            $seasonsWithEpisodes[] = array_merge($season, ['episodes' => $episodes]);
        }

        return $this->twig->render($response, 'library/show.twig', [
            'item'    => $this->enrichItem($item),
            'seasons' => $seasonsWithEpisodes,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Add proxy URLs (poster, detail page) to an item array.
     * Jellyfin URLs and API keys are never passed to templates.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function enrichItem(array $item): array
    {
        $isSeries    = ($item['Type'] ?? '') === 'Series';
        $item['posterUrl']  = '/poster/' . $item['Id'];
        $item['detailUrl']  = $isSeries ? '/shows/' . $item['Id'] : '/movies/' . $item['Id'];
        $item['isSeries']   = $isSeries;
        $item['rating']     = round((float) ($item['CommunityRating'] ?? 0), 1);
        $item['genres']     = $item['Genres'] ?? [];
        $item['year']       = $item['ProductionYear'] ?? null;
        $item['fileSize']      = $this->formatFileSize((int) ($item['MediaSources'][0]['Size'] ?? 0));
        $item['mediaInfo']     = $this->extractMediaInfo($item['MediaSources'] ?? [], $item['MediaStreams'] ?? []);
        $item['officialRating'] = $item['OfficialRating'] ?? null;
        return $item;
    }

    /**
     * Add proxy download URL to an episode array.
     *
     * @param array<string, mixed> $ep
     * @return array<string, mixed>
     */
    private function enrichEpisode(array $ep): array
    {
        $ep['downloadUrl'] = '/download/' . $ep['Id'];
        $ep['duration']    = $this->formatDuration((int) ($ep['RunTimeTicks'] ?? 0));
        $ep['fileSize']    = $this->formatFileSize((int) ($ep['MediaSources'][0]['Size'] ?? 0));
        $ep['mediaInfo']   = $this->extractMediaInfo($ep['MediaSources'] ?? [], $ep['MediaStreams'] ?? []);
        return $ep;
    }

    /**
     * Extract video quality, audio tracks, and subtitle tracks.
     * Uses MediaSources[0]['MediaStreams'] first, falls back to the item-level MediaStreams
     * (Jellyfin populates the nested array for single-item calls but may omit it on list endpoints).
     *
     * @param array<mixed> $mediaSources  Item's MediaSources array
     * @param array<mixed> $itemStreams   Item's top-level MediaStreams array (fallback)
     */
    private function extractMediaInfo(array $mediaSources, array $itemStreams = []): array
    {
        $streams = $mediaSources[0]['MediaStreams'] ?? [];
        if (empty($streams)) {
            $streams = $itemStreams;
        }

        $resolution = null;
        $bestDim    = 0;
        $audio      = [];
        $subtitles  = [];

        foreach ($streams as $s) {
            $type = $s['Type'] ?? '';

            if ($type === 'Video') {
                $h   = (int) ($s['Height'] ?? 0);
                $w   = (int) ($s['Width']  ?? 0);
                $dim = max($h, $w);
                if ($dim > $bestDim) {
                    $bestDim    = $dim;
                    $resolution = match (true) {
                        $w >= 3840 || $h >= 2160 => '4K',
                        $w >= 1920 || $h >= 1080 => '1080p',
                        $w >= 1280 || $h >= 720  => '720p',
                        default                  => null,
                    };
                }
            }

            if ($type === 'Audio') {
                $lang = $s['DisplayLanguage'] ?? null;
                if ($lang === null || $lang === '') {
                    $lang = $this->isoToLanguage($s['Language'] ?? '');
                }
                if ($lang !== null && $lang !== ''
                    && !in_array($lang, array_column($audio, 'lang'), true)) {
                    $audio[] = ['lang' => $lang];
                }
            }

            if ($type === 'Subtitle') {
                $lang = $s['DisplayLanguage'] ?? null;
                if ($lang === null || $lang === '') {
                    $lang = $this->isoToLanguage($s['Language'] ?? '');
                }
                if ($lang !== null && $lang !== ''
                    && !in_array($lang, array_column($subtitles, 'lang'), true)) {
                    $subtitles[] = ['lang' => $lang];
                }
            }
        }

        if ($resolution === null && !$audio && !$subtitles) {
            return [];
        }

        return ['resolution' => $resolution, 'audio' => $audio, 'subtitles' => $subtitles];
    }

    /** Normalize an OfficialRating string to a minimum age integer. Returns null for unknown codes. */
    public static function normalizeRating(?string $rating): ?int
    {
        if ($rating === null || $rating === '') {
            return null;
        }
        $key = strtolower(trim($rating));
        if (isset(self::RATING_MAP[$key])) {
            return self::RATING_MAP[$key];
        }
        // Rating systems vary wildly across countries and metadata sources; most
        // numbered codes embed the minimum age ("6+", "16", "FSK 12", "FR-14").
        // Extract the first standalone 1-2 digit number as a fallback.
        if (preg_match('/(?<!\d)(\d{1,2})(?!\d)/', $key, $m) && (int) $m[1] <= 21) {
            return (int) $m[1];
        }
        return null;
    }

    /** Return true if the item's rating is within the user's age limit. */
    private function passesAgeLimit(?string $rating, int $limit): bool
    {
        $age = self::normalizeRating($rating);
        return $age === null || $age <= $limit;
    }

    /** Map ISO 639-2 language codes to display names. Falls back to uppercase code. */
    private function isoToLanguage(string $code): ?string
    {
        if ($code === '' || $code === 'und') {
            return null;
        }
        $map = [
            'fra' => 'Français',  'fre' => 'Français',
            'eng' => 'English',
            'spa' => 'Español',   'esp' => 'Español',
            'deu' => 'Deutsch',   'ger' => 'Deutsch',
            'ita' => 'Italiano',
            'por' => 'Português',
            'jpn' => 'Japanese',
            'chi' => '中文',       'zho' => '中文',
            'kor' => '한국어',
            'rus' => 'Русский',
            'ara' => 'العربية',
            'nld' => 'Nederlands', 'dut' => 'Nederlands',
            'pol' => 'Polski',
            'swe' => 'Svenska',
            'nor' => 'Norsk',
            'dan' => 'Dansk',
            'fin' => 'Suomi',
        ];
        return $map[strtolower($code)] ?? strtoupper($code);
    }

    /** Format a file size in bytes as a human-readable string (e.g. "8.2 GB"). */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size  = (float) $bytes;
        $i     = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            ++$i;
        }
        return round($size, 1) . "\u{00A0}" . $units[$i];
    }

    /** Format Jellyfin RunTimeTicks (100-ns units) as "1h 23m". */
    private function formatDuration(int $ticks): string
    {
        if ($ticks <= 0) {
            return '';
        }
        $seconds = (int) round($ticks / 10_000_000);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($h > 0) {
            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        }
        return "{$m}m";
    }

    /** Validate the sort param, fallback to 'title'. */
    private function validSort(string $sort): string
    {
        return array_key_exists($sort, self::SORT_MAP) ? $sort : 'title';
    }

    /** Validate the order param — only 'asc' and 'desc' are valid; '' = use default. */
    private function validOrder(string $order): string
    {
        return in_array($order, ['asc', 'desc'], true) ? $order : '';
    }

    /** Resolve the Jellyfin SortOrder value from sort key + optional override. */
    private function resolveOrder(string $sort, string $order): string
    {
        if ($order === 'asc')  return 'Ascending';
        if ($order === 'desc') return 'Descending';
        return self::SORT_MAP[$sort]['SortOrder']; // use the SORT_MAP default
    }

    /** Validate the type param, fallback to 'all'. */
    private function validType(string $type): string
    {
        return array_key_exists($type, self::TYPE_MAP) ? $type : 'all';
    }

    /**
     * Restrict the active type filter and list of shown type buttons
     * based on the user's content_access setting.
     *
     * @return array{0: string, 1: list<string>}  [active type, allowed types]
     */
    private function applyAccess(string $type, string $access): array
    {
        $allowed = match ($access) {
            'movies' => ['movie'],
            'shows'  => ['show'],
            default  => ['all', 'movie', 'show'],
        };

        // Force the type to a valid one for this user
        if (!in_array($type, $allowed, true)) {
            $type = $allowed[0];
        }

        return [$type, $allowed];
    }
}
