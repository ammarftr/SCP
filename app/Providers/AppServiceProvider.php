<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tampilan pagination seragam (Bootstrap 5, bahasa Indonesia): resources/views/vendor/pagination/scp.blade.php
        Paginator::defaultView('vendor.pagination.scp');

        // Akses lewat Cloudflare Tunnel: tunnel menerima HTTPS lalu meneruskan HTTP ke server lokal.
        // Kalau proxy tidak dikenali, Laravel mengira halaman HTTP sehingga URL css/js/manifest/sw
        // dibuat dengan http:// dan diblokir browser (mixed content). Paksa https bila permintaan
        // aslinya memang https. Pengaman ini tidak bergantung pada TRUSTED_PROXIES.
        $request = request();
        $proto = strtolower((string) $request->header('X-Forwarded-Proto'));
        $visitor = (string) $request->header('CF-Visitor');

        if (str_starts_with($proto, 'https') || str_contains($visitor, '"scheme":"https"')) {
            URL::forceScheme('https');
        }
    }
}
