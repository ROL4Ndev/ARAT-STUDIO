<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_params', function (Blueprint $table) {
            $table->id();
            $table->enum('lod_level', [
                'LOD_200',
                'LOD_300',
                'LOD_400',
            ])->unique();
            $table->decimal('base_price_m2', 12, 2);
            $table->decimal('lod_factor', 6, 3);
            $table->decimal('max_area_m2', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_params');
    }
};
