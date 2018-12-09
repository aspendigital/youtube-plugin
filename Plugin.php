<?php namespace Bluhex\YouTube;

use System\Classes\PluginBase;

/**
 * YouTube Videos plugin
 *
 * @author Brendon Park
 */
class Plugin extends PluginBase
{

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'bluhex.youtube::lang.plugin.name',
            'description' => 'bluhex.youtube::lang.plugin.description',
            'author'      => 'Bluhex Studios',
            'icon'        => 'icon-youtube-play',
            'homepage'    => 'https://github.com/bluhex/october-youtube'
        ];
    }

    /**
     * Register the settings listing.
     *
     * @return array
     */
    public function registerSettings()
    {
        return [
            'config' => [
                'label'       => 'bluhex.youtube::lang.settings.menu_label',
                'icon'        => 'icon-youtube-play',
                'description' => 'bluhex.youtube::lang.settings.menu_description',
                'class'       => 'Bluhex\YouTube\Models\Settings',
                'order'       => 600
            ]
        ];
    }

    /**
     * Register the components.
     *
     * @return array
     */
    public function registerComponents()
    {
        return [
            '\Bluhex\YouTube\Components\Channel' => 'youtubeChannel',
            '\Bluhex\YouTube\Components\PlayList' => 'youtubePlaylist'
        ];
    }

    /**
     * Register page snippets.
     *
     * @return array
     */
    public function registerPageSnippets()
    {
        return [
            '\Bluhex\YouTube\Components\Channel' => 'youtubeChannel',
            '\Bluhex\YouTube\Components\PlayList' => 'youtubePlaylist'
        ];
    }
}
