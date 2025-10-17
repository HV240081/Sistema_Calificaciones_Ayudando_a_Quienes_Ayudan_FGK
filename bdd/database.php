<?php
$dsn = "mysql:host=localhost;dbname=PROYECTO_ES";
$username = "userproyect";
$password = "FGK202412345";


//$dsn = "mysql:host=localhost;dbname=proyecto_es;charset=utf8";
//$username = "root";
//$password = "";

try {
    // Crear la conexión PDO
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Si llega aquí, la conexión fue exitosa
    //echo "Conexión exitosa";
} catch (PDOException $e) {
    // Registrar el error en un archivo de log

    error_log("Error de conexión: " . $e->getMessage(), 3, "/ruta/completa/a/tu/proyecto/app_errors.log");

    error_log("Error de conexión: " . $e->getMessage(), 3, "app_errors.log");

    
    // Mostrar un mensaje genérico al usuario
    die("Error de conexión. Contacte al administrador.");
}

?>
