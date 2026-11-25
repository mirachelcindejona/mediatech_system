<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft konten per halaman CMS. "Simpan draft" hanya menulis ke tabel ini,
 * sehingga halaman yang sudah Published tetap tampil seperti semula di website
 * sampai tombol Publish diklik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_drafts', function (Blueprint $table) {
            $table->bigIncrements('draft_id');
            $table->unsignedBigInteger('page_id');
            $table->longText('content');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique('page_id');
            $table->foreign('page_id')->references('page_id')->on('cms_pages')->cascadeOnDelete();
            $table->foreign('updated_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_page_drafts');
    }
};
