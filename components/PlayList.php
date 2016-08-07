<?php
/**
 * Created by PhpStorm.
 * User: Bryan
 * Date: 8/7/2016
 * Time: 1:47 PM
 */

namespace Bluhex\YouTube\Components;


use Cms\Classes\ComponentBase;

class PlayList extends ListVideosComponentBase
{

    /**
     * Returns information about this component, including name and description.
     */
    public function componentDetails()
    {
        return [
            'name'        => 'Latest Videos',
            'description' => 'Display a list of latest YouTube videos for a channel'
        ];
    }

    public function defineProperties()
    {
        $properties = [
            'playlist_id' => [
                'title'       => 'Playlist Id',
                'description' => 'The YouTube Playlist ID to query against. youtube.com/account_advanced',
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
                return YouTubeClient::instance()->getPlaylist($playlistId, $maxItems, $thumbResolution);
            });
    }
}