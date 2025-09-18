<?php

/**
 * YKP Dashboard Route Update
 *
 * Add this to your routes/web.php file to use the optimized dashboard:
 */

// Replace your existing dashboard route with this optimized version
Route::get('/dashboard', function () {
    return view('modern-dashboard-optimized');
})->middleware('auth')->name('dashboard');

/**
 * Optional: Keep the old version for comparison
 * You can temporarily use both routes to compare performance
 */
Route::get('/dashboard-old', function () {
    return view('modern-dashboard');
})->middleware('auth')->name('dashboard.old');

/**
 * After testing, you can remove the old route and rename the optimized one
 * to your original route path if needed.
 */
