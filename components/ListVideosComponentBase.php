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
                'title'       => 'bluhex.youtube::lang.settings.max_items',
                'description' => 'bluhex.youtube::lang.settings.max_items_description',
                'default'     => '12'
            ],
            'thumb_resolution' => [
                'title'       => 'bluhex.youtube::lang.settings.thumb_resolution',
                'type'        => 'dropdown',
                'description' => "bluhex.youtube::lang.settings.thumb_resolution_description",
                'default'     => 'medium',
                'options'     => [
                    'full-resolution' => 'bluhex.youtube::lang.settings.thumb_resolution_option_full',
                    'high'            => 'bluhex.youtube::lang.settings.thumb_resolution_option_high',
                    'medium'          => 'bluhex.youtube::lang.settings.thumb_resolution_option_medium',
                    'default'         => 'bluhex.youtube::lang.settings.thumb_resolution_option_default'
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
