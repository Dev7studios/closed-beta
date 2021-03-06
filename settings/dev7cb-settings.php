<?php

global $wpsf_settings;

// General Settings section
$wpsf_settings[] = array(
    'section_id' => 'general',
    'section_title' => 'General Settings',
    'section_order' => 1,
    'fields' => array(
        array(
            'id' => 'enabled',
            'title' => 'Enabled',
            'desc' => 'If checked then "Closed Beta" mode is enabled. Only approved users can access the site.',
            'type' => 'checkbox',
            'std' => 0
        ),
        array(
            'id' => 'page-title',
            'title' => 'Page Title',
            'desc' => 'The title of your site (if blank defaults to the site title).',
            'type' => 'text',
            'std' => get_option('blogname')
        ),
        array(
            'id' => 'tagline',
            'title' => 'Tagline',
            'desc' => 'A short, attention grabbing sub-title.',
            'type' => 'text',
            'std' => get_option('blogdescription')
        ),
        array(
            'id' => 'page-content',
            'title' => 'Page Content',
            'desc' => 'Usually a brief description of what your app does.',
            'type' => 'editor',
            'std' => ''
        ),
        array(
            'id' => 'username-label',
            'title' => 'Username Label',
            'desc' => 'A label for the username input.',
            'type' => 'text',
            'std' => 'Enter a username'
        ),
        array(
            'id' => 'email-label',
            'title' => 'Email Label',
            'desc' => 'A label for the email input.',
            'type' => 'text',
            'std' => 'Enter your email address'
        ),
        array(
            'id' => 'signup-text',
            'title' => '"Sign Up" Button Text',
            'desc' => 'The text of the "Sign Up" button.',
            'type' => 'text',
            'std' => 'Sign Up'
        ),
        array(
            'id' => 'login-text',
            'title' => '"Login" Button Text',
            'desc' => 'The text of the "Login" button.',
            'type' => 'text',
            'std' => 'Login'
        ),
        array(
            'id' => 'preview',
            'title' => '',
            'desc' => '',
            'type' => 'custom',
            'std' => '<a href="'. home_url('?closed-beta-preview=true') .'" target="_blank" class="button">Preview Splash Page</a>'
        )
    )
);

// Style Settings section
$wpsf_settings[] = array(
    'section_id' => 'style',
    'section_title' => 'Style Settings',
    'section_order' => 2,
    'fields' => array(
        array(
            'id' => 'background-image',
            'title' => 'Background Image',
            'desc' => 'Choose a background image for the splash page.',
            'type' => 'file',
            'std' => ''
        ),
        array(
            'id' => 'background-style',
            'title' => 'Background Style',
            'desc' => 'Choose a background image style for the splash page.',
            'type' => 'select',
            'std' => 'full',
            'choices' => array(
                'full' => 'Full Size',
                'full_stretched' => 'Full Size (stretched)',
                'tiled' => 'Tiled'
            )
        ),
        array(
            'id' => 'background-position',
            'title' => 'Background Position',
            'desc' => 'Choose a background position for the splash page.',
            'type' => 'radio',
            'std' => 'center',
            'choices' => array(
                'center' => 'Center',
                'left' => 'Left',
                'right' => 'Right'
            )
        ),
        array(
            'id' => 'background-color',
            'title' => 'Background Color',
            'desc' => 'Choose a background color for the splash page.',
            'type' => 'color',
            'std' => '#'
        ),
        array(
            'id' => 'text-color',
            'title' => 'Text Color',
            'desc' => 'Choose a text color for the splash page.',
            'type' => 'color',
            'std' => '#'
        ),
        array(
            'id' => 'link-color',
            'title' => 'Link Color',
            'desc' => 'Choose a link color for the splash page.',
            'type' => 'color',
            'std' => '#'
        ),
        array(
            'id' => 'overlay',
            'title' => 'Overlay',
            'desc' => 'Select an overlay style for the splash page.',
            'type' => 'radio',
            'std' => 'black',
            'choices' => array(
                'black' => 'Black',
                'white' => 'White',
                'none' => 'None'
            )
        ),
        array(
            'id' => 'custom-css',
            'title' => 'Custom CSS',
            'desc' => 'Add any custom CSS here.',
            'type' => 'textarea',
            'std' => ''
        ),
        array(
            'id' => 'preview',
            'title' => '',
            'desc' => '',
            'type' => 'custom',
            'std' => '<a href="'. home_url('?closed-beta-preview=true') .'" target="_blank" class="button">Preview Splash Page</a>'
        )
    )
);

// Advanced Settings section
$wpsf_settings[] = array(
    'section_id' => 'advanced',
    'section_title' => 'Advanced Settings',
    'section_order' => 3,
    'fields' => array(
        array(
            'id' => 'access-settings',
            'title' => 'Access to Closed Beta',
            'desc' => 'Users with this capability (role) or higher will be able to manage these settings.',
            'type' => 'select',
            'std' => 'manage_options',
            'choices' => array(
                'manage_options' => 'manage_options (Administrator)',
                'manage_categories' => 'manage_categories (Editor)',
                'publish_posts' => 'publish_posts (Author)',
                'edit_posts' => 'edit_posts (Contributor)',
                'read' => 'read (Subscriber)'
            )
        ),
        array(
            'id' => 'enablefeeds',
            'title' => 'Enable Feeds',
            'desc' => '',
            'type' => 'checkbox',
            'std' => 0
        ),
        array(
            'id' => 'enabletrackbacks',
            'title' => 'Enable Trackbacks',
            'desc' => '',
            'type' => 'checkbox',
            'std' => 0
        ),
        array(
            'id' => 'enablexmlrpc',
            'title' => 'Enable XMLRPC Publishing',
            'desc' => '',
            'type' => 'checkbox',
            'std' => 0
        ),
    )
);

// Support section
$wpsf_settings[] = array(
    'section_id' => 'support',
    'section_title' => 'Support',
    'section_order' => 4,
    'fields' => array(
        array(
            'id' => 'useful-links',
            'title' => 'Useful Links',
            'desc' => '',
            'type' => 'custom',
            'std' => 'Website: <a href="http://codecanyon.net/item/showcase-wordpress-gallery-plugin/2866252?ref=gilbitron">Closed Beta</a><br />
            Created by: <a href="http://dev7studios.com">Dev7studios</a><br />
            Support: <a href="http://support.dev7studios.com/discussions/closed-beta-wordpress-plugin">Support Forums</a><br />
            Changelog: <a href="http://codecanyon.net/item/showcase-wordpress-gallery-plugin/2866252?ref=gilbitron">Changelog</a><br />
            Automatic Updates: <a href="http://wp-updates.com">WP Updates</a><br />'
        )
    )
);

?>