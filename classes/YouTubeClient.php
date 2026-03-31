<?php namespace Bluhex\YouTube\Classes;

use Bluhex\YouTube\Models\Settings;
use Carbon\Carbon;
use October\Rain\Exception\ApplicationException;

/**
 * YouTube API Client class
 *
 * @author Brendon Park
 */
class YouTubeClient
{
    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var string
     */
    public $apiKey;

    /**
     * Max number of items to fetch in the channel or playlist
     * @var string
     */
    public $maxItems;

    /**
     * Remaining number of items to fetch in the channel or playlist
     * @var string
     */
    public $itemsRemaining;

    /**
     * Remove result limit if $maxItems equals 0
     * @var boolean
     */
    public $isUnlimited;

    /**
     * Max number of results supported by the YouTube API
     * @var string
     *
     * Youtube API limits 'maxResults' to 50
     * https://developers.google.com/youtube/v3/docs/search/list#parameters
     */
    public $apiMaxResults = 50;

    /**
     * @var string
     */
    protected $apiBaseUrl = 'https://www.googleapis.com/youtube/v3/';

    protected function init()
    {
        $settings = Settings::instance();

        if (!strlen($settings->api_key)) {
            throw new ApplicationException('Google API access requires an API Key. Please add your key to Settings / Misc / YouTube');
        }

        $this->apiKey = $settings->api_key;
    }

    /**
     * Grabs the latest videos from a channel or playlist
     *
     * @param $type string Type (channel, playlist)
     * @param $id string YouTube channel ID or playlist ID
     * @param $maxItems int maximum number of items to display
     * @param $thumbResolution string Thumbnail resolution (default, medium, high)
     * @return array|null array of videos or null if failure
     */
    public function getYoutubeVideos($type, $id, $maxItems = 12, $thumbResolution = 'medium')
    {
        $this->maxItems = (int) $maxItems;
        $this->isUnlimited = ($this->maxItems === 0);

        $requestMaxItems = $this->isUnlimited || $this->maxItems > $this->apiMaxResults
            ? $this->apiMaxResults
            : $this->maxItems;

        $params = [
            'maxResults' => $requestMaxItems,
        ];

        try {
            $youtubeResults = $this->fetchResults($type, $id, $params);

            if ($youtubeResults === null) {
                return null;
            }

            $results = $youtubeResults->items ?? [];
            $nextPageToken = $youtubeResults->nextPageToken ?? null;

            if (($this->maxItems > $this->apiMaxResults || $this->isUnlimited) && $nextPageToken) {
                if (!$this->isUnlimited) {
                    $this->itemsRemaining = $this->maxItems - $requestMaxItems;
                }

                $results = $this->getNextPageResults($type, $id, $results, $params, $nextPageToken);
            }

            if ($type === 'channel') {
                return $this->transformChannelResults($results, $thumbResolution);
            }

            if ($type === 'playlist') {
                return $this->transformPlaylistResults($results, $thumbResolution);
            }
        } catch (\Exception $e) {
            // Since we're relying on an outside source, lets not crash the page if we can't reach YouTube
            traceLog($e);
        }

        return null;
    }

    public function getNextPageResults($type, $id, $results, $params, $nextPageToken)
    {
        if ($this->itemsRemaining > $this->apiMaxResults || $this->isUnlimited) {
            $maxResults = $this->apiMaxResults;
        } else {
            $maxResults = $this->itemsRemaining;
        }

        $params['pageToken'] = $nextPageToken;
        $params['maxResults'] = $maxResults;

        if (!$this->isUnlimited) {
            $this->itemsRemaining -= $maxResults;
        }

        $nextPageResults = $this->fetchResults($type, $id, $params);

        if ($nextPageResults === null) {
            return $results;
        }

        $results = array_merge($results, $nextPageResults->items ?? []);

        if (($this->itemsRemaining > 0 || $this->isUnlimited) && !empty($nextPageResults->nextPageToken)) {
            return $this->getNextPageResults($type, $id, $results, $params, $nextPageResults->nextPageToken);
        }

        return $results;
    }

    public function getLatestCacheKey($id, $maxItems, $thumbResolution)
    {
        return 'bluhex_ytvideos_' . $id . '_' . $maxItems . '_' . $thumbResolution;
    }

    protected function fetchResults($type, $id, array $params)
    {
        if ($type === 'channel') {
            $endpoint = 'search';
            $params = array_merge($params, [
                'part' => 'id,snippet',
                'order' => 'date',
                'type' => 'video',
                'channelId' => $id,
            ]);
        } elseif ($type === 'playlist') {
            $endpoint = 'playlistItems';
            $params = array_merge($params, [
                'part' => 'id,snippet',
                'playlistId' => $id,
            ]);
        } else {
            throw new \InvalidArgumentException('Unknown YouTube list type: ' . $type);
        }

        $params['key'] = $this->apiKey;

        return $this->requestJson($this->apiBaseUrl . $endpoint, $params);
    }

    protected function requestJson($url, array $params)
    {
        $requestUrl = $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $response = $this->httpGet($requestUrl);

        if ($response === false || $response === null) {
            return null;
        }

        $decoded = json_decode($response);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Unable to decode YouTube API response: ' . json_last_error_msg());
        }

        if (isset($decoded->error)) {
            $message = isset($decoded->error->message) ? $decoded->error->message : 'Unknown YouTube API error';
            throw new \RuntimeException($message);
        }

        return $decoded;
    }

    protected function httpGet($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_USERAGENT => 'GOED Bluhex YouTube Plugin',
            ]);

            $response = curl_exec($ch);

            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);

                throw new \RuntimeException('YouTube request failed: ' . $error);
            }

            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($status >= 400) {
                throw new \RuntimeException('YouTube request failed with HTTP status ' . $status);
            }

            return $response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "User-Agent: GOED Bluhex YouTube Plugin\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new \RuntimeException('YouTube request failed: ' . ($error['message'] ?? 'unknown error'));
        }

        return $response;
    }

    protected function transformChannelResults($items, $thumbResolution)
    {
        $videos = [];

        foreach ($items as $item) {
            $kind = $item->id->kind ?? null;
            if ($kind !== 'youtube#video') {
                continue;
            }

            $videoId = $item->id->videoId ?? null;
            if (!$videoId) {
                continue;
            }

            $snippet = $item->snippet ?? null;
            if (!$snippet) {
                continue;
            }

            $thumbnail = $this->resolveThumbnailUrl($snippet->thumbnails ?? null, $thumbResolution, $videoId);

            $videos[] = [
                'id' => $videoId,
                'link' => 'https://youtube.com/watch?v=' . $videoId,
                'title' => $snippet->title ?? '',
                'thumbnail' => $thumbnail,
                'description' => $snippet->description ?? '',
                'published_at' => Carbon::parse($snippet->publishedAt ?? 'now'),
            ];
        }

        return $videos;
    }

    protected function transformPlaylistResults($items, $thumbResolution)
    {
        $videos = [];

        foreach ($items as $item) {
            $kind = $item->kind ?? null;
            if ($kind !== 'youtube#playlistItem') {
                continue;
            }

            $snippet = $item->snippet ?? null;
            if (!$snippet) {
                continue;
            }

            $videoId = $snippet->resourceId->videoId ?? null;
            if (!$videoId) {
                continue;
            }

            $title = trim((string) ($snippet->title ?? ''));
            if ($title === '' || strcasecmp($title, 'Private video') === 0 || strcasecmp($title, 'Deleted video') === 0) {
                continue;
            }

            $thumbnail = $this->resolveThumbnailUrl($snippet->thumbnails ?? null, $thumbResolution, $videoId);

            $videos[] = [
                'id' => $videoId,
                'link' => 'https://youtube.com/watch?v=' . $videoId,
                'title' => $title,
                'thumbnail' => $thumbnail,
                'description' => $snippet->description ?? '',
                'published_at' => Carbon::parse($snippet->publishedAt ?? 'now'),
            ];
        }

        return $videos;
    }

    protected function resolveThumbnailUrl($thumbnails, $thumbResolution, $videoId)
    {
        if ($thumbResolution === 'full-resolution') {
            return 'https://img.youtube.com/vi/' . $videoId . '/maxresdefault.jpg';
        }

        if (!$thumbnails) {
            return 'https://img.youtube.com/vi/' . $videoId . '/mqdefault.jpg';
        }

        $sources = [
            'default' => $thumbnails->default ?? null,
            'medium' => $thumbnails->medium ?? null,
            'high' => $thumbnails->high ?? null,
        ];

        $preferredOrder = [
            'default' => ['default', 'medium', 'high'],
            'medium' => ['medium', 'high', 'default'],
            'high' => ['high', 'medium', 'default'],
        ];

        $order = $preferredOrder[$thumbResolution] ?? $preferredOrder['default'];

        foreach ($order as $size) {
            if (!empty($sources[$size]->url)) {
                return $sources[$size]->url;
            }
        }

        return 'https://img.youtube.com/vi/' . $videoId . '/mqdefault.jpg';
    }
}
