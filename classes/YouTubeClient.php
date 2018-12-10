<?php namespace Bluhex\YouTube\Classes;

use Google_Client;
use Bluhex\YouTube\Models\Settings;
use Carbon\Carbon;
use October\Rain\Exception\ApplicationException;

/**
 * YouTube API Client class
 *
 * @author Brendon Park
 *
 */
class YouTubeClient
{

    use \October\Rain\Support\Traits\Singleton;

    /**
     * @var Google_Client Google API Client
     */
    public $client;
    public $service;

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

    protected function init()
    {
        $settings = Settings::instance();

        if (!strlen($settings->api_key))
            throw new ApplicationException('Google API access requires an API Key. Please add your key to Settings / Misc / YouTube');

        // Create the Google Client
        $client = new Google_Client();
        $client->setDeveloperKey($settings->api_key);
        $this->client = $client;
        $this->service = new \Google_Service_YouTube($client);
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
        $this->maxItems = $maxItems;
        $this->isUnlimited = ($this->maxItems == 0) ? true : false;

        // Youtube limits the number of results to 50
        if ($maxItems > $this->apiMaxResults || $this->isUnlimited) {
            $maxItems = $this->apiMaxResults;
        }

        // Setup shared query params
        $params = array(
            'maxResults' => $maxItems
        );

        try {
            // Get YouTube Channel videos
            if ($type == 'channel') {
                $params['order'] = 'date';
                $params['channelId'] = $id;

                $youtubeResults = $this->service->search->listSearch('id,snippet', $params);
            }

            // Get YouTube Playlist videos
            if ($type == 'playlist') {
                $params['playlistId'] = $id;

                $youtubeResults = $this->service->playlistItems->listPlaylistItems('id,snippet', $params);
            }

            $results = $youtubeResults->items;

            // Return a the nextPageToken string if there are additional results
            $nextPageToken = $youtubeResults->nextPageToken;

            // Get next page results
            if (($this->maxItems > $this->apiMaxResults || $this->isUnlimited) && $nextPageToken) {
                if (!$this->isUnlimited) {
                    $this->itemsRemaining = $this->maxItems - $maxItems;
                }

                $results = $this->getNextPageResults($type, $results, $params, $nextPageToken);
            }

            // Transform results
            if ($type == 'channel') {
                return $this->transformChannelResults($results, $thumbResolution);
            } elseif ($type == 'playlist') {
                return $this->transformPlaylistResults($results, $thumbResolution);
            }
        } catch (\Exception $e) {
            // Since we're relying on an outside source, lets not crash the page if we can't reach YouTube
            traceLog($e);

            return null;
        }
    }

    public function getNextPageResults($type, $results, $params, $nextPageToken)
    {
        // Youtube limits the number of results to 50
        if ($this->itemsRemaining > $this->apiMaxResults || $this->isUnlimited) {
            $maxResults = $this->apiMaxResults;
        } else {
            $maxResults = $this->itemsRemaining;
        }

        // Add params
        $params['pageToken'] = $nextPageToken;
        $params['maxResults'] = $maxResults;

        // Keep track of the remaining items in case we need to fetch more results
        if (!$this->isUnlimited) {
            $this->itemsRemaining = $this->itemsRemaining - $maxResults;
        }

        // Get next page results with the updated params
        if ($type === 'channel') {
            $nextPageResults = $this->service->search->listSearch('id,snippet', $params);
        } elseif ($type === 'playlist') {
            $nextPageResults = $this->service->playlistItems->listPlaylistItems('id,snippet', $params);
        }

        // Merge new results with the previous results
        $results = array_merge($results, $nextPageResults['items']);

        // Get the next page if there are more results to fetch
        if (($this->itemsRemaining > 0 || $this->isUnlimited) && $nextPageResults->nextPageToken) {
            $nextPageToken = $nextPageResults->nextPageToken;

            return $this->getNextPageResults($type, $results, $params, $nextPageToken);
        }

        return $results;
    }

    public function getLatestCacheKey($id, $maxItems, $thumbResolution)
    {
        // Components with the same channel or playlist and item count will use the same cached response
        return 'bluhex_ytvideos_' . $id . '_' . $maxItems . '_' . $thumbResolution;
    }

    protected function transformChannelResults($items, $thumbResolution)
    {

        // Parse the results
        $videos = [];
        foreach ($items as $item) {
            $kind = $item->getId()->getKind();
            if ($kind != 'youtube#video') {
                continue;
            }

            // Get the desired thumbnail resolution, YouTube's API doesn't support a proper high-res thumbnail
            $thumbnails = $item->snippet->getThumbnails();
            switch ($thumbResolution) {
                case 'full-resolution':
                    $thumbnail = 'https://img.youtube.com/vi/' . $item->getId()->getVideoId() . '/maxresdefault.jpg';
                    break;
                case 'default':
                    $thumbnail = $thumbnails->getDefault()->url;
                    break;
                case 'medium':
                    $thumbnail = $thumbnails->getMedium()->url;
                    break;
                case 'high':
                    $thumbnail = $thumbnails->getHigh()->url;
                    break;
                default:
                    $thumbnail = $thumbnails->getDefault()->url;
                    break;
            }

            array_push($videos, array(
                'id'           => $item->getId()->getVideoId(),
                'link'         => 'https://youtube.com/watch?v=' . $item->getId()->getVideoId(),
                'title'        => $item->getSnippet()->getTitle(),
                'thumbnail'    => $thumbnail,
                'description'  => $item->getSnippet()->getDescription(),
                'published_at' => Carbon::parse($item->getSnippet()->getPublishedAt())
            ));
        }
        return $videos;
    }

    protected function transformPlaylistResults($items, $thumbResolution)
    {
        // Parse the results
        $videos = [];
        foreach ($items as $item) {
            $kind = $item->getKind();
            if ($kind != 'youtube#playlistItem') {
                continue;
            }

            // Get the desired thumbnail resolution, YouTube's API doesn't support a proper high-res thumbnail
            $snippet = $item->getSnippet();
            $thumbnails = $snippet->getThumbnails();
            switch ($thumbResolution) {
                case 'full-resolution':
                    $thumbnail = 'https://img.youtube.com/vi/' . $snippet->getResourceId()->getVideoId() . '/maxresdefault.jpg';
                    break;
                case 'default':
                    $thumbnail = $thumbnails->getDefault()->url;
                    break;
                case 'medium':
                    $thumbnail = $thumbnails->getMedium()->url;
                    break;
                case 'high':
                    $thumbnail = $thumbnails->getHigh()->url;
                    break;
                default:
                    $thumbnail = $thumbnails->getDefault()->url;
                    break;
            }

            array_push($videos, array(
                'id'           => $snippet->getResourceId()->getVideoId(),
                'link'         => 'https://youtube.com/watch?v=' . $snippet->getResourceId()->getVideoId(),
                'title'        => $snippet->getTitle(),
                'thumbnail'    => $thumbnail,
                'description'  => $snippet->getDescription(),
                'published_at' => Carbon::parse($snippet->getPublishedAt())
            ));
        }

        return $videos;
    }
}
