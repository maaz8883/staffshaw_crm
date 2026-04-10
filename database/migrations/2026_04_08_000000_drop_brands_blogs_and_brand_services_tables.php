<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('brand_services');
        Schema::dropIfExists('blogs');
        Schema::dropIfExists('brands');
    }

    public function down(): void
    {
        // Intentionally empty: brands / blogs / brand services feature removed.
    }
};
