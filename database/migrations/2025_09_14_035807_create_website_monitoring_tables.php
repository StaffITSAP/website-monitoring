<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('nama_website');
            $table->string('url');
            $table->boolean('aktif')->default(true);
            $table->integer('interval_pemeriksaan')->default(5); // dalam menit
            $table->timestamps();
        });

        Schema::create('log_pemeriksaan_website', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->integer('kode_status')->nullable();
            $table->float('waktu_respons')->nullable(); // dalam detik
            $table->boolean('berhasil')->default(false);
            $table->text('pesan_error')->nullable();
            $table->text('screenshot_path')->nullable();
            $table->timestamps();
            
            $table->index(['website_id', 'created_at']);
        });

        Schema::create('laporan_harian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->integer('total_website');
            $table->integer('website_aktif');
            $table->integer('website_error');
            $table->float('rata_rata_waktu_respons');
            $table->string('path_xlsx')->nullable();
            $table->string('path_pdf')->nullable();
            $table->timestamps();
            
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_harian');
        Schema::dropIfExists('log_pemeriksaan_website');
        Schema::dropIfExists('websites');
    }
};