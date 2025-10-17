<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

require 'bdd/database.php'; // Esto debería definir $pdo

// Verificar si el usuario está autenticado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Función para validar la contraseña
function validarContrasena($contrasena) {
    $errores = [];
    
    // Requisitos
    if (!preg_match("/[A-Z]/", $contrasena)) {
        $errores[] = "La contraseña debe contener al menos 1 letra mayúscula.";
    }
    if (!preg_match("/\d.*\d/", $contrasena)) {
        $errores[] = "La contraseña debe contener al menos 2 números.";
    }
    if (!preg_match("/[!@#$%^&*()_+{}\[\]:;,.<>?]/", $contrasena)) {
        $errores[] = "La contraseña debe contener al menos 1 carácter especial.";
    }
    if (strlen($contrasena) < 9) {
        $errores[] = "La contraseña debe tener al menos 9 caracteres.";
    }
    
    
    
    
    return $errores;
}

// Verificar si se envió el formulario de cambio de contraseña
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nueva_contrasena = $_POST['nueva_contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    $id_usuario = $_SESSION['id_usuario'];

    if ($nueva_contrasena == $confirmar_contrasena) {
        // Validar la nueva contraseña
        $errores = validarContrasena($nueva_contrasena);

        if (empty($errores)) {
            try {
                // Preparar la consulta con PDO
                $sql = "UPDATE USUARIO SET CONTRASENA = :nueva_contrasena, TEMPORAL = 0 WHERE ID_USUARIO = :id_usuario";
                $stmt = $pdo->prepare($sql);

                // Ejecutar la consulta usando password_hash para mayor seguridad
                if ($stmt->execute([':nueva_contrasena' => password_hash($nueva_contrasena, PASSWORD_DEFAULT), ':id_usuario' => $id_usuario])) {
                    // Mensaje de éxito para depuración
                    echo "Contraseña actualizada correctamente"; // Para depuración

                    // Redirigir según el rol después de cambiar la contraseña
                    switch ($_SESSION['id_rol']) {
                        case 1:
                            header("Location: panel_admin.php");
                            exit(); // Asegúrate de incluir exit después de header
                        case 2:
                            header("Location: panel_jurado.php");
                            exit(); // Asegúrate de incluir exit después de header
                        default:
                            echo "Rol no reconocido"; // Mensaje de depuración
                    }
                } else {
                    echo "Error al actualizar la contraseña"; // Mensaje de error de depuración
                }
            } catch (PDOException $e) {
                // Registrar errores en un archivo de log si algo falla
                error_log("Error al actualizar la contraseña: " . $e->getMessage(), 3, __DIR__ . "/app_errors.log");
                echo "Error al actualizar la contraseña. Por favor, intente de nuevo.";
            }
        } else {
            echo "Errores de validación: " . implode(", ", $errores); // Mensaje de errores de validación
        }
    } else {
        echo "Las contraseñas no coinciden."; // Mensaje si las contraseñas no coinciden
    }

    // Guardar errores en la sesión para mostrar en el formulario
    $_SESSION['errores'] = $errores;
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/normalize.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="icon" href="img/FGK02.png" type="image/x-icon">
</head>
<body class="inicio_sesion">
    <div class="formulario-cambio-contrasena">
        <h2>Cambiar Contraseña</h2>
        <?php
        // Mostrar mensajes de error
        if (isset($_SESSION['errores']) && !empty($_SESSION['errores'])) {
            foreach ($_SESSION['errores'] as $error) {
                echo "<div class='mensaje mensaje-ocultar'>$error</div>";
            }
            // Limpiar los errores después de mostrarlos
            unset($_SESSION['errores']);
        }
        ?>
        <form action="Contraseña.php" method="post">
            <label for="nueva_contrasena">Nueva Contraseña:</label>
            <input type="password" id="nueva_contrasena" name="nueva_contrasena" required>
            <label for="confirmar_contrasena">Confirmar Contraseña:</label>
            <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" required>
            <input type="submit" value="Cambiar Contraseña">
        </form>
        <br>
        <div class="mensaje">
            <ul>
                <li>La contraseña debe tener al menos 9 caracteres.</li>
                <li>Debe contener al menos 1 letra mayúscula.</li>
                <li>Debe contener al menos 2 números.</li>
                <li>Debe contener al menos 1 carácter especial.</li>
            </ul>
        </div>
    </div>
</body>
</html>
