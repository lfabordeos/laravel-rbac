<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePermissibleObjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permissible_objects', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->index();
            $table->string('ownable_type')->nullable(true)->index();
            $table->string('ownable_column')->nullable(true)->index();
            $table->string('owner_object')->nullable(true)->index();
            $table->json('options');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('permissible_objects');
    }
}
