<?php namespace Bluhex\YouTube\Components;

use Cms\Classes\ComponentBase;

abstract class ListVideosComponentBase extends ComponentBase
{
    public $videos;

    /**
     * Returns information about this component, including name and description.
     */
    public function componentDetails()
    {
        return [];
    }

    public function defineProperties()
    {
        return [
            'max_items'        => [
                'title'       => 'Max Items',
                'description' => 'Maximum number of results, acceptable values are 0 to 50.',
                'default'     => '12'
            ],
            'thumb_resolution' => [
                'title'       => 'Thumbnail Size',
                'type'        => 'dropdown',
                'description' => "Thumbnails may return cropped images as per the YouTube API.
                                    However, 'Full Resolution' may fail to find an image, but won't be cropped.",
                'default'     => 'medium',
                'options'     => [
                    'full-resolution' => 'Full Resolution',
                    'high'            => 'High',
                    'medium'          => 'Medium',
                    'default'         => 'Default'
                ]
            ]
        ];
    }

    public function onRun()
    {
        $this->videos = $this->listVideos();
    }

    /**
     * Queries the YouTube API for videos. Implementations should implement caching strategy.
     * @return mixed
     */
    public abstract function listVideos();

}
