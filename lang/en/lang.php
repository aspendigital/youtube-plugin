<?php

return [
    'plugin' => [
        'name' => 'YouTube Videos',
        'description' => 'Provides a component to display YouTube videos.'
    ],
    'settings' => [
        'menu_label' => 'YouTube',
        'menu_description' => 'Configure YouTube API Key and Channel settings.',
        'max_items' => 'Max Items',
        'max_items_description' => 'Maximum number of results. Use \'0\' to get all results.',
        'thumb_resolution' => 'Thumbnail Size',
        'thumb_resolution_description' => 'Thumbnails may return cropped images as per the YouTube API. However, \'Full Resolution\' may fail to find an image, but won\'t be cropped.',
        'thumb_resolution_option_full' => 'Full Resolution',
        'thumb_resolution_option_high' => 'High',
        'thumb_resolution_option_medium' => 'Medium',
        'thumb_resolution_option_default' => 'Default',
        'playlist_name' => 'YouTube Playlist Latest Videos',
        'playlist_description' => 'Display a list of latest YouTube videos for a playlist',
        'playlist_id' => 'Playlist Id',
        'playlist_id_description' => 'The YouTube Playlist ID to query against. youtube.com/account_advanced',
        'channel_name' => 'YouTube Channel Latest Videos',
        'channel_description' => 'Display a list of latest YouTube videos for a channel',
        'channel_id' => 'Channel Id',
        'channel_id_description' => 'The YouTube Channel Id to query against. youtube.com/account_advanced'
    ]
];
