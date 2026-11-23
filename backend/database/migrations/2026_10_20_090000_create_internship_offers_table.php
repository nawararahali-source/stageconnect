<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('internship_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('domain', 255);
            $table->text('description');
            $table->string('duration', 100);
            $table->string('location', 255);
            $table->string('work_mode', 20)->default('onsite');
            $table->json('required_skills')->nullable();
            $table->unsignedSmallInteger('positions')->default(1);
            $table->date('deadline')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('internship_offers');
    }
};
