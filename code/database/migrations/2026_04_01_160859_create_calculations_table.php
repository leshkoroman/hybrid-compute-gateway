<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quantum_dot_type_id')->constrained('quantum_dot_types');
            
            $table->foreignId('core_material_id')->constrained('materials');
            $table->foreignId('matrix_material_id')->constrained('materials');
            
            $table->json('geometry_params'); 
            
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('results_path')->nullable(); 
            $table->text('error_log')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('calculations');
    }
};