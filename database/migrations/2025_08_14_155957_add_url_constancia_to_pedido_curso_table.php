<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUrlConstanciaToPedidoCursoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pedido_curso', function (Blueprint $table) {
            $table->string('url_constancia')->nullable()->after('send_constancia');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pedido_curso', function (Blueprint $table) {
            $table->dropColumn('url_constancia');
        });
    }
}
