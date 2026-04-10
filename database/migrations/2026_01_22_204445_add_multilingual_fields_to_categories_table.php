<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Add English columns
            $table->string('name_en')->nullable()->after('id');
            $table->text('description_en')->nullable()->after('name_en');
            
            // Add German columns
            $table->string('name_de')->nullable()->after('name_en');
            $table->text('description_de')->nullable()->after('description_en');
            
            // Remove old columns
            if (Schema::hasColumn('categories', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('categories', 'description')) {
                $table->dropColumn('description');
            }
        });
        
        // Migrate existing data
        \DB::table('categories')->get()->each(function ($category) {
            \DB::table('categories')->where('id', $category->id)->update([
                'name_en' => $category->name ?? null,
                'description_en' => $category->description ?? null,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->text('description')->nullable()->after('name');
            
            $table->dropColumn(['name_en', 'name_de', 'description_en', 'description_de']);
        });
        
        // Migrate data back
        \DB::table('categories')->get()->each(function ($category) {
            \DB::table('categories')->where('id', $category->id)->update([
                'name' => $category->name_en ?? $category->name_de ?? null,
                'description' => $category->description_en ?? $category->description_de ?? null,
            ]);
        });
    }
};
