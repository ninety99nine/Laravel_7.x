<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectUserTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('project_user', function (Blueprint $table) {

            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('project_id');
            $table->string('type')->default('viewer');

            /*  Timestamps  */
            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('project_user');
    }
}
