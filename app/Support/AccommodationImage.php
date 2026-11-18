<?php

namespace App\Support;

class AccommodationImage
{
    private const PLACEHOLDER = 'image/placeholders/accommodation.svg';

    public static function placeholder(): string
    {
        return asset(self::PLACEHOLDER);
    }

    /** Prefer the saved cover, then a loadable image from this owner's gallery only. */
    public static function cover(iterable $images): string
    {
        foreach (collect($images)->sortByDesc('is_primary') as $image) {
            if ($url = self::localUrl($image->image_url)) return $url;
        }
        return self::placeholder();
    }

    /** Resolve existing public images without fetching any remote resources. */
    public static function localUrl(?string $value): ?string
    {
        if (!$value || !($parts = parse_url(str_replace('\\', '/', trim($value))))) {
            return null;
        }
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        if (isset($parts['host'])) {
            $hosts = array_filter([parse_url(config('app.url'), PHP_URL_HOST), request()->getHost()]);
            if (!in_array(strtolower($parts['host']), array_map('strtolower', $hosts), true)
                || isset($parts['user']) || isset($parts['pass'])) {
                return null;
            }
        }

        $path = str_replace('\\', '/', rawurldecode($parts['path'] ?? ''));
        if (isset($parts['host'])) {
            foreach (array_filter([parse_url(config('app.url'), PHP_URL_PATH), request()->getBaseUrl()]) as $basePath) {
                $prefix = rtrim($basePath, '/') . '/';
                if (str_starts_with($path, $prefix)) {
                    $path = substr($path, strlen($prefix));
                    break;
                }
            }
        }
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }
        if (str_starts_with($path, 'storage/app/public/')) {
            $path = 'storage/' . substr($path, 19);
        }
        $segments = explode('/', $path);
        if ($path === '' || str_contains($path, "\0") || str_contains($path, ':')
            || array_intersect($segments, ['.', '..', ''])) {
            return null;
        }

        $stored = str_starts_with($path, 'storage/');
        $disk = $stored ? \Illuminate\Support\Facades\Storage::disk('public') : null;
        $file = realpath($stored ? $disk->path(substr($path, 8)) : public_path($path));
        $root = realpath($stored ? $disk->path('') : public_path());
        if (!$file || !$root || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
            || !is_file($file) || !is_readable($file)) {
            return null;
        }
        if ($path !== self::PLACEHOLDER && @getimagesize($file) === false) {
            return null;
        }

        return asset(implode('/', array_map('rawurlencode', $segments)));
    }
}
