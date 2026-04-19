<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->float('v_pot')->default(0);
            $table->float('gamma1');
            $table->float('gamma2');
            $table->float('gamma3');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('materials');
    }
};