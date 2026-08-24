<?php

namespace App\Providers;

use App\Models\TollCapture;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(database_path('migrations/toll_capture'));
        $this->loadMigrationsFrom(database_path('migrations/report_engine'));

        $imageDir = TollCapture::getImageBasePath();
        if ($imageDir !== '' && ! File::isDirectory($imageDir)) {
            try {
                File::makeDirectory($imageDir, 0755, true);
            } catch (\Throwable $e) {
                // Writable image path must be provisioned on deploy (see VEHICLE_IMAGES_PATH / TOLL_CAPTURE_IMAGES_PATH).
            }
        }

        $dir = storage_path('framework/upload-tmp');
        try {
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            if (File::isWritable($dir)) {
                @ini_set('upload_tmp_dir', $dir);
            }
        } catch (\Throwable $e) {
            // Host may still require upload_tmp_dir in php.ini (PHP_INI_SYSTEM).
        }
    }
}
