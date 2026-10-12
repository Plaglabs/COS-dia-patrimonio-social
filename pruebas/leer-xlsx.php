<?php

/*
 * Lee un .xlsx con el mismo OpenSpout que lo escribe y lo imprime en JSON,
 * una lista de filas de la primera hoja. Lo usa `exportar-inscripciones.mjs`.
 *
 * Con `--hojas`, todas las hojas: {"nombre de la hoja": [filas]}. Lo usa
 * `tanda-12-10.mjs` para la exportación de una organización.
 *
 *   php pruebas/leer-xlsx.php archivo.xlsx [--hojas]
 */

require __DIR__.'/../vendor/autoload.php';

use OpenSpout\Reader\XLSX\Reader;

$todas = in_array('--hojas', $argv, true);

$reader = new Reader;
$reader->open($argv[1]);

$filas = [];
$hojas = [];

foreach ($reader->getSheetIterator() as $hoja) {
    $estas = [];

    foreach ($hoja->getRowIterator() as $fila) {
        $estas[] = $fila->toArray();
    }

    if (! $todas) {
        $filas = $estas;

        break;
    }

    $hojas[$hoja->getName()] = $estas;
}

$reader->close();

echo json_encode($todas ? $hojas : $filas, JSON_UNESCAPED_UNICODE);
