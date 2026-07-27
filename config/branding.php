<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding stylesheet (per school)
    |--------------------------------------------------------------------------
    |
    | Path under /public. Copy branding.css.example → branding/branding.css locally.
    |
    */
    'css_path' => env('BRANDING_CSS', 'branding/branding.css'),

    /*
    |--------------------------------------------------------------------------
    | Display names
    |--------------------------------------------------------------------------
    */
    'school_name' => env('BRANDING_SCHOOL_NAME', env('APP_NAME', 'Library')),
    'library_name' => env('BRANDING_LIBRARY_NAME', 'Library'),
    'system_name' => env('BRANDING_SYSTEM_NAME', 'PANTAS'),
    'portal_subtitle' => env('BRANDING_PORTAL_SUBTITLE', 'Staff portal'),

    /*
    |--------------------------------------------------------------------------
    | Image assets (paths under /public)
    |--------------------------------------------------------------------------
    */
    'assets' => [
        'logo' => env('BRANDING_LOGO', 'images/pantasLogo.png'),
        'logo_landscape' => env('BRANDING_LOGO_LANDSCAPE', 'images/Pantas logo landscape.png'),
        'logo_compact' => env('BRANDING_LOGO_COMPACT', 'images/pantasLogo.png'),
        'favicon' => env('BRANDING_FAVICON', 'images/pantasLogo.png'),
        'banner' => env('BRANDING_BANNER', 'images/Bannernew.jpg'),
        'default_avatar' => env('BRANDING_DEFAULT_AVATAR', 'images/2x2_undifined_gender.jpg'),
        'default_book' => env('BRANDING_DEFAULT_BOOK', 'images/defaultBook.png'),
        'partner_zendy' => env('BRANDING_PARTNER_ZENDY_LOGO', ''),
        'platform_logo' => env('BRANDING_PLATFORM_LOGO', 'images/pantasLogo.png'),
        'platform_logo_landscape' => env('BRANDING_PLATFORM_LOGO_LANDSCAPE', 'images/Pantas logo landscape.png'),
        'platform_vendor_logo' => env('BRANDING_PLATFORM_VENDOR_LOGO', 'images/pantasLogo.png'),
    ],

    /*
    |--------------------------------------------------------------------------
    | External links
    |--------------------------------------------------------------------------
    */
    'links' => [
        'school_home' => env('BRANDING_SCHOOL_HOME_URL', '/'),
        'zendy' => env('BRANDING_ZENDY_URL', 'https://zendy.io'),
    ],

];
