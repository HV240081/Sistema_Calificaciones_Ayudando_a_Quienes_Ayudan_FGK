<?php
// Incluir el archivo de conexión
session_start();
require_once 'bdd/database.php';


// Verificar si se enviaron los datos del formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtener los datos del formulario de manera segura y convertir el usuario a minúsculas
    $usuario = isset($_POST['usuario']) ? strtolower($_POST['usuario']) : '';
    $contraseña = isset($_POST['contraseña']) ? $_POST['contraseña'] : '';

    // Preparar la consulta SQL
    $sql = "SELECT * FROM USUARIO WHERE LOWER(CONCAT(NOMBRE, '_', APELLIDO)) = :usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();

    // Verificar si se encontró el usuario
    if ($stmt->rowCount() > 0) {
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar la contraseña
        if ($contraseña == $fila['CONTRASENA']) {
            $_SESSION['id_usuario'] = $fila['ID_USUARIO'];
            $_SESSION['nombre'] = $fila['NOMBRE'];
            $_SESSION['apellido'] = $fila['APELLIDO'];
            $_SESSION['id_rol'] = $fila['ID_ROL'];
            
            // Verificar si la contraseña es temporal
            if ($fila['TEMPORAL'] == 1) {
                header("Location: Contraseña.php");
                exit();
            } else {
                switch ($fila['ID_ROL']) {
                    case 1:
                        header("Location: panel_admin.php");
                        break;
                    case 2:
                        header("Location: panel_jurado.php");
                        break;
                    case 3:
                        header("Location: panel_juradop.php");
                        break;
                }
                exit();
            }
        } else {
            $_SESSION['alerta'] = "Contraseña incorrecta.";
        }
    } else {
        $_SESSION['alerta'] = "Usuario no encontrado.";
    }
    //header("Location: panel_admin.php");
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Inicio de sesión</title>
    <link rel="stylesheet" href="assets/css/normalize.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="icon" href="img/FGK02.png" type="image/x-icon">
    <link href="assets/img/favicon.png" rel="icon">
</head>

<body class="inicio_sesion">

<!-- Contenedor para los mensajes de validación en la parte superior del formulario -->
<div id="alerta_index" class="alerta">
    <?php echo isset($_SESSION['alerta']) ? $_SESSION['alerta'] : ''; ?>
</div>

<div class="contenedor-formulario contenedor">
    <div class="imagen-formulario"></div>

    <form class="formulario" action="index.php" method="POST">
        <div class="texto-formulario">
            <h2>Inicia sesión con tu cuenta</h2>
        </div>
        <div class="input">
            <label for="usuario">Usuario</label>
            <input placeholder="Ingresa tu nombre" type="text" id="usuario" name="usuario" required oninput="convertirMinusculas()" onkeypress="bloquearMayusculas(event)">
        </div>
        <div class="input">
            <label for="contraseña">Contraseña</label>
            <input placeholder="Ingresa tu contraseña" type="password" id="contraseña" name="contraseña" required>
        </div>
        <div class="input">
            <input type="submit" value="Iniciar sesión">
        </div>
    </form>
</div>

<script>
    // Mostrar mensaje de sesión si existe
    const alerta = "<?php echo isset($_SESSION['alerta']) ? $_SESSION['alerta'] : ''; ?>";
    if (alerta) {
        const alertaDiv = document.getElementById('alerta_index');
        alertaDiv.innerText = alerta;
        alertaDiv.style.display = 'block';

        // Ocultar el mensaje después de 5 segundos
        setTimeout(() => {
            alertaDiv.style.display = 'none';
        }, 5000);
    }
</script>

</body>
</html>
