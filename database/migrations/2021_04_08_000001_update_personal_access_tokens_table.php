<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePersonalAccessTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->after('name', function ($table) {
                $table->string('user_agent')->nullable();
                $table->string('ip')->nullable();
                $table->json('ip_data')->nullable();
                $table->string('device_type')->nullable();
                $table->string('device')->nullable();
                $table->string('platform')->nullable();
                $table->string('browser')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->string('country')->nullable();
            });
            $table->after('updated_at', function ($table) {
                $table->expirable('expires_at');
                $table->softDeletes();
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'user_agent',
                'ip',
                'ip_data',
                'device_type',
                'device',
                'platform',
                'browser',
                'city',
                'region',
                'country',
                'expires_at',
                'deleted_at',
            ]);
        });
    }
}
