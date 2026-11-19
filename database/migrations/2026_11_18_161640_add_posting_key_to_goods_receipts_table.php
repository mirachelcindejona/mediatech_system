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
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->char('posting_key', 64)->nullable()->unique();
            $table->char('posting_fingerprint', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('goods_receipts')->whereNotNull('posting_key')->orWhereNotNull('posting_fingerprint')->exists()) {
            throw new RuntimeException('Cannot discard receipt posting identities used to prevent duplicate stock.');
        }
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropUnique(['posting_key']);
            $table->dropColumn('posting_key');
            $table->dropColumn('posting_fingerprint');
        });
    }
};
