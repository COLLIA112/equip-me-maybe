<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->char('catalog_model_key', 64)->nullable()->index();
            $table->boolean('catalog_is_numbered')->default(false);
        });

        DB::table('equipment')->select(['id', 'name'])->eachById(function (object $item) {
            $model = preg_replace('/\s+#\d+$/u', '', trim($item->name));
            DB::table('equipment')->where('id', $item->id)
                ->update([
                    'catalog_model_key' => hash('sha256', Str::lower($model)),
                    'catalog_is_numbered' => $model !== trim($item->name),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropIndex(['catalog_model_key']);
            $table->dropColumn(['catalog_model_key', 'catalog_is_numbered']);
        });
    }
};
