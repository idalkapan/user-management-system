<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tokens:purge-legacy', function () {
    $deleted = PersonalAccessToken::query()
        ->whereNull('expires_at')
        ->delete();

    $this->info("Silinen eski token sayısı: {$deleted}");
})->purpose('expires_at değeri boş olan eski personal access token kayıtlarını siler.');
