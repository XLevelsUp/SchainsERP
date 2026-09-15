<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Dev Utility Routes
|--------------------------------------------------------------------------
| Run seeders and migrations from the browser.
| These are safe — seeders use firstOrCreate/updateOrCreate so no data
| is ever deleted or overwritten.
*/

Route::get('/run-seeders', function () {
    $output = new \Symfony\Component\Console\Output\BufferedOutput();
    Artisan::call('db:seed', ['--force' => true], $output);
    return response('<pre>' . $output->fetch() . '</pre>');
});

Route::get('/run-migrations', function () {
    $output = new \Symfony\Component\Console\Output\BufferedOutput();
    Artisan::call('migrate', ['--force' => true], $output);
    return response('<pre>' . $output->fetch() . '</pre>');
});
