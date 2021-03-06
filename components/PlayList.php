<?php namespace Bluhex\YouTube\Components;

use Cache;
use Bluhex\YouTube\Models\Settings;
use Bluhex\YouTube\Classes\YouTubeClient;

class PlayList extends ListVideosComponentBase
{

    /**
     * Returns information about this component, including name and description.
     */
    public function componentDetails()
    {
        return [
            'name'        => 'bluhex.youtube::lang.settings.playlist_name',
            'description' => 'bluhex.youtube::lang.settings.playlist_description'
        ];
    }

    public function defineProperties()
    {
        $properties = [
            'playlist_id' => [
                'title'       => 'bluhex.youtube::lang.settings.playlist_id',
                'description' => 'bluhex.youtube::lang.settings.playlist_id_description',
                'type'        => 'string',
            ]
        ];

        $properties = array_merge($properties, parent::defineProperties());
        return $properties;
    }

    /**
     * Queries the YouTube API for videos. Implementations should implement caching strategy.
     * @return mixed
     */
    public function listVideos()
    {
        $playlistId = $this->property('playlist_id');
        $maxItems = $this->property('max_items');
        $thumbResolution = $this->property('thumb_resolution');
        $cacheKey = YouTubeClient::instance()->getLatestCacheKey($playlistId, $maxItems, $thumbResolution);

        return Cache::remember($cacheKey,
            Settings::get('cache_time'),
            function () use ($playlistId, $maxItems, $thumbResolution) {
                return YouTubeClient::instance()->getYoutubeVideos('playlist', $playlistId, $maxItems, $thumbResolution);
            });
    }
}
