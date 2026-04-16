<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — POSmeister SPA
|--------------------------------------------------------------------------
| All routes (except /api/*) serve the Vue 3 SPA entry point.
| Vue Router handles client-side navigation from there.
*/

Route::get('/{any}', fn () => view('app'))
    ->where('any', '.*')
    ->name('spa');
