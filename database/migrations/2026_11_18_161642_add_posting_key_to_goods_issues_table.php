<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goods_issues', function (Blueprint $table) {
            $table->char('posting_key', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('goods_issues')->whereNotNull('posting_key')->exists()) {
            throw new RuntimeException('Cannot discard issue posting identities used to prevent duplicate stock.');
        }
        Schema::table('goods_issues', function (Blueprint $table) {
            $table->dropUnique(['posting_key']);
            $table->dropColumn('posting_key');
        });
    }
};
