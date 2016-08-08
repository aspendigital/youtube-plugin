<?php namespace Bluhex\YouTube\Components;

use Cache;
use Bluhex\YouTube\Models\Settings;
use Bluhex\YouTube\Classes\YouTubeClient;

/**
 * Latest Videos component
 *
 * @author Brendon Park
 *
 */
class Channel extends ListVideosComponentBase
{
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
            'channel_id' => [
                'title'       => 'Channel Id',
                'description' => 'The YouTube Channel Id to query against. youtube.com/account_advanced',
                'type'        => 'string',
            ]
        ];

        $properties = array_merge($properties, parent::defineProperties());

        return $properties;
    }

    public function listVideos()
    {
        $channelId = $this->property('channel_id');
        $maxItems = $this->property('max_items');
        $thumbResolution = $this->property('thumb_resolution');
        $cacheKey = YouTubeClient::instance()->getLatestCacheKey($channelId, $maxItems, $thumbResolution);

        return Cache::remember($cacheKey,
            Settings::get('cache_time'),
            function () use ($channelId, $maxItems, $thumbResolution) {
                return YouTubeClient::instance()->getLatest($channelId, $maxItems, $thumbResolution);
            });
    }
}