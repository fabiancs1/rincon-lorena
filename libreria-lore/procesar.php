<?php

if (isset($_FILES['archivo'])) {

    $archivo = $_FILES['archivo']['tmp_name'];
    $puntos = [];

    if (($handle = fopen($archivo, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $puntos[] = [
                "x" => floatval($data[0]),
                "y" => floatval($data[1])
            ];
        }
        fclose($handle);
    }

    echo "<h2>Archivo Procesado</h2>";
    echo "Total de puntos: " . count($puntos);
    echo "<br><a href='proyecto.php'>Volver</a>";
}
