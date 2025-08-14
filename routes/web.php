<?php
use App\Jobs\SendConstanciaCurso;
use Illuminate\Support\Facades\DB;

Route::get('/test-constancia', function () {
    // Busca un pedido de curso de prueba (ajusta el ID según tu base de datos)
    $pedidoCurso = DB::table('pedido_curso')->first();

    if (!$pedidoCurso) {
        return 'No hay pedidos de curso en la base de datos.';
    }

    // Despacha el job (ajusta el número si es necesario)
    SendConstanciaCurso::dispatch($pedidoCurso->Txt_Celular_Entidad ?? '51931629529', $pedidoCurso);

    return 'Job SendConstanciaCurso despachado.';
});
