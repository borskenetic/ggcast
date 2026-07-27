<?php

namespace App\Support;

/**
 * Resolve per-school branding text, colors (via CSS), and image assets.
 */
class Branding
{
    /**
     * Public URL for a configured asset key, with optional fallback file.
     */
    public static function url(string $key): string
    {
        return self::assetUrl($key);
    }

    /**
     * Resolve asset path; when missing, use another branding key (e.g. platform → school logo).
     *
     * @param  list<string>  $fallbackKeys
     */
    public static function assetUrl(string $key, string|array|null $fallbackKeys = null): string
    {
        $path = config("branding.assets.{$key}");

        if (is_string($path) && $path !== '' && PublicAssetPath::resolve($path) !== null) {
            return asset($path);
        }

        foreach ((array) ($fallbackKeys ?? []) as $fallbackKey) {
            $fallbackPath = config("branding.assets.{$fallbackKey}");

            if (! is_string($fallbackPath) || $fallbackPath === '') {
                continue;
            }

            if (PublicAssetPath::resolve($fallbackPath) !== null) {
                return asset($fallbackPath);
            }
        }

        if (is_string($path) && $path !== '') {
            return asset($path);
        }

        return '';
    }

    public static function has(string $key): bool
    {
        $path = config("branding.assets.{$key}");

        return is_string($path) && $path !== '' && PublicAssetPath::resolve($path) !== null;
    }

    public static function faviconMime(): string
    {
        $path = config('branding.assets.favicon', '');

        if (! is_string($path) || $path === '') {
            return 'image/x-icon';
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'ico' => 'image/x-icon',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/x-icon',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function forViews(): array
    {
        return [
            'school_name' => config('branding.school_name'),
            'library_name' => config('branding.library_name'),
            'system_name' => config('branding.system_name'),
            'portal_subtitle' => config('branding.portal_subtitle'),
            'css_path' => config('branding.css_path'),
            'logo_url' => self::url('logo'),
            'logo_landscape_url' => self::url('logo_landscape'),
            'logo_compact_url' => self::url('logo_compact'),
            'favicon_url' => self::url('favicon'),
            'favicon_mime' => self::faviconMime(),
            'banner_url' => self::url('banner'),
            'default_avatar_url' => self::url('default_avatar'),
            'default_book_url' => self::url('default_book'),
            'partner_zendy_url' => self::url('partner_zendy'),
            'platform_logo_url' => self::assetUrl('platform_logo', ['logo', 'logo_landscape']),
            'platform_logo_landscape_url' => self::assetUrl('platform_logo_landscape', ['logo_landscape', 'logo']),
            'platform_vendor_logo_url' => self::assetUrl('platform_vendor_logo', ['logo_landscape', 'logo']),
            'school_home_url' => config('branding.links.school_home'),
            'zendy_url' => config('branding.links.zendy'),
        ];
    }

    /**
     * Props for React admin shell (camelCase).
     *
     * @return array<string, mixed>
     */
    public static function forShell(): array
    {
        $view = self::forViews();

        return [
            'cssPath' => $view['css_path'],
            'schoolName' => $view['school_name'],
            'libraryName' => $view['library_name'],
            'systemName' => $view['system_name'],
            'portalSubtitle' => $view['portal_subtitle'],
            'assets' => [
                'logo' => $view['logo_url'],
                'logoLandscape' => $view['logo_landscape_url'],
                'logoCompact' => $view['logo_compact_url'],
                'favicon' => $view['favicon_url'],
                'banner' => $view['banner_url'],
                'defaultAvatar' => $view['default_avatar_url'],
                'defaultBook' => $view['default_book_url'],
            ],
            'links' => [
                'schoolHome' => $view['school_home_url'],
                'zendy' => $view['zendy_url'],
            ],
        ];
    }
}
