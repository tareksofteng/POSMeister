<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    private const SUPPORTED = ['en', 'de', 'bn', 'ar'];
    private const DEFAULT   = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);
        App::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        // 1. Explicit query param (?lang=de)
        if ($lang = $request->query('lang')) {
            if (in_array($lang, self::SUPPORTED, true)) {
                return $lang;
            }
        }

        // 2. Accept-Language header (first match wins)
        $header = $request->header('Accept-Language', '');
        foreach (explode(',', $header) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            $primary = explode('-', $tag)[0];
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return self::DEFAULT;
    }
}
