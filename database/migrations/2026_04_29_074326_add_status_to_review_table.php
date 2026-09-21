<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('review', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'hidden'])
                ->default('approved')
                ->after('rating');
        });
    }

    public function down()
    {
        Schema::table('review', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
