<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
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
        TrustProxies::at(config('trustedproxy.proxies'));
        TrustProxies::withHeaders(config('trustedproxy.headers'));

        // Lempar exception saat mass-assignment menyentuh kolom
        // yang tidak ada di $fillable, alih-alih mendiamkannya.
        // Ini akan menangkap bug seperti "keterangan_dispensasi"
        // yang tadinya gagal tersimpan tanpa error apapun.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
    }
}
