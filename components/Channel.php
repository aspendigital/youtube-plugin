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
            'name'        => 'bluhex.youtube::lang.settings.channel_name',
            'description' => 'bluhex.youtube::lang.settings.channel_description'
        ];
    }

    public function defineProperties()
    {
        $properties = [
            'channel_id' => [
                'title'       => 'bluhex.youtube::lang.settings.channel_id',
                'description' => 'bluhex.youtube::lang.settings.channel_id_description',
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
