<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('assigned_by');
            $table->bigInteger('assigned_to');
            $table->datetime('due_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status',['assigned','in-progress','completed','deleted'])->default('assigned');
            $table->datetime('assigned_at');
            $table->datetime('completed_at')->nullable()->default(null);
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
        Schema::dropIfExists('tasks');
    }
}
