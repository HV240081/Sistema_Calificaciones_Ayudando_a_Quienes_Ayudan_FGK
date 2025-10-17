<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
  header("Location: index.php");
  exit();
}

require 'bdd/database.php';

$id_usuario = $_SESSION['id_usuario'];

// Obtener el rol del usuario actual
$sql = "SELECT U.NOMBRE, U.APELLIDO, U.ID_ROL, R.ROL FROM USUARIO U JOIN ROL R ON U.ID_ROL = R.ID_ROL WHERE ID_USUARIO = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Nombre y rol del usuario actual
$nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
$rol = $row['ROL'];
$id_rol_usuario = $row['ID_ROL']; // Ahora debería funcionar correctamente

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener todos los proyectos
$sql = "SELECT ID_PROYECTO, PROYECTO FROM PROYECTO";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Inicializar variables
$criterios = [];
$id_actividad = null;
$id_proyecto = null;
$permiso_actividad = null;
$actividad_porcentaje = null; // --- CAMBIO: almacenar porcentaje de la actividad


// Verifica si se ha recibido un ID de actividad en la URL
if (isset($_GET['id'])) {
  $id_actividad = $_GET['id'];

  // Consulta para obtener los criterios y el permiso relacionados con la actividad
  $sql = "SELECT C.ID_CRITERIO, C.CRITERIO, C.DESCRIPCION, C.PORCENTAJE, A.ID_PERMISO 
          FROM CRITERIO C 
          JOIN ACTIVIDAD A ON A.ID_ACTIVIDAD = C.ID_ACTIVIDAD
          WHERE C.ID_ACTIVIDAD = :ID_ACTIVIDAD";
  $stmt = $pdo->prepare($sql);
  $stmt->execute(['ID_ACTIVIDAD' => $id_actividad]);
  $criterios = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Obtener el permiso de la actividad
  if (!empty($criterios)) {
    $permiso_actividad = $criterios[0]['ID_PERMISO']; // Asumiendo que la actividad tiene un permiso único
  }


  // --- CAMBIO: obtener también el porcentaje de la actividad
  $sqlPorcentaje = "SELECT PORCENTAJE FROM ACTIVIDAD WHERE ID_ACTIVIDAD = :ID_ACTIVIDAD";
  $stmtPorc = $pdo->prepare($sqlPorcentaje);
  $stmtPorc->execute(['ID_ACTIVIDAD' => $id_actividad]);
  $actividad_porcentaje = $stmtPorc->fetchColumn();
  // Aseguramos que sea numérico
  $actividad_porcentaje = $actividad_porcentaje !== false ? (int)$actividad_porcentaje : null;
}

// --- CAMBIO: Filtrar criterios a mostrar si la actividad es 30%
$criterios_mostrar = $criterios;
if ($actividad_porcentaje === 30) {
  // Solo mostrar criterios "Proyecto" y "Presentación general del Proyecto"
  $criterios_mostrar = array_filter($criterios, function($c) {
    $nombre = mb_strtolower(trim($c['CRITERIO']));
    return $nombre === mb_strtolower('Proyecto') || $nombre === mb_strtolower('Presentación general del Proyecto') || $nombre === mb_strtolower('Presentacion general del Proyecto');
  });
  // Reindex
  $criterios_mostrar = array_values($criterios_mostrar);
}

$permiso_valido = false;
if ($permiso_actividad === 1 && $id_rol_usuario === 2) {
  // El permiso es "jurado" (ID 1) y el usuario es jurado (ID_ROL 1)
  $permiso_valido = true;
} elseif ($permiso_actividad === 2 && $id_rol_usuario === 1) {
  // El permiso es "administrador" (ID 2) y el usuario es administrador (ID_ROL 2)
  $permiso_valido = true;
}


$permiso_tabla = false;
if ($permiso_actividad === 1 && $id_rol_usuario === 1){
  //permiso es "jurado" y          el usuario es "admin"
  $permiso_tabla = false;
}elseif ($permiso_actividad === 2 && $id_rol_usuario === 1){
  //permiso es "admin" y           el usuario es "admin"
  $permiso_tabla = true;
}

// Procesar el envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_usuario = $_POST['idUsuario'];  // El ID del usuario que está calificando
  $id_proyecto = $_POST['idProyecto'];  // El ID del proyecto que se está calificando
  $notas = $_POST['notas'];  // Las notas dadas por el usuario a los criterios

  $comentario = $_POST['comentario'] ?? '';  // Comentario opcional

  $id_nota_criterios = [];  // Para almacenar los IDs de las notas insertadas
  $total_calificacion_proyecto = 0;  // Suma de la calificación ponderada de cada criterio
  $total_porcentaje_proyecto = 0;  // Suma del porcentaje de cada criterio

  // Validar campos requeridos
  if (empty($id_usuario) || empty($id_proyecto) || empty($notas)) {
    header('Location: calificarAdmin.php?id=' . $id_actividad . '&error=empty_fields');
    exit;
  }

  // Verificar si el usuario ya calificó este proyecto
  $sql = "SELECT COUNT(*) FROM NOTA_CRITERIO NC
        JOIN NOTA_CRITERIO_CALIFICACION NCC ON NC.ID_NOTACRITERIO = NCC.ID_NOTA_CRITERIO
        JOIN CALIFICACION C ON NCC.ID_CALIFICACION = C.ID_CALIFICACION
        WHERE NC.ID_USUARIO = :ID_USUARIO 
        AND C.ID_PROYECTO = :ID_PROYECTO
        AND C.ID_ACTIVIDAD = :ID_ACTIVIDAD";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
      'ID_USUARIO' => $id_usuario,
      'ID_PROYECTO' => $id_proyecto,
      'ID_ACTIVIDAD' => $id_actividad
  ]);

  $alreadyRated = $stmt->fetchColumn();

  if ($alreadyRated > 0) {
      header('Location: calificarAdmin.php?id=' . $id_actividad . '&error=already_rated');
      exit;
  }

  // Verificar token CSRF para evitar ataques cross-site
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Error: Token CSRF inválido.");
  }

  // Iniciar transacción para asegurarnos de que todos los datos se guardan correctamente o se revierten en caso de error
  $pdo->beginTransaction();

  try {
    foreach ($notas as $id_criterio => $nota) {
      // Obtener el porcentaje del criterio
      $sql = "SELECT PORCENTAJE FROM CRITERIO WHERE ID_CRITERIO = :ID_CRITERIO";
      $stmt = $pdo->prepare($sql);
      $stmt->execute(['ID_CRITERIO' => $id_criterio]);
      $porcentaje = $stmt->fetchColumn();

      // Calcular la nota ponderada en función del porcentaje del criterio
      $nota_ponderada = $nota * ($porcentaje / 100);
      $total_calificacion_proyecto += $nota_ponderada;
      $total_porcentaje_proyecto += $porcentaje;

      // Insertar la nota en la tabla NOTA_CRITERIO para este usuario y criterio
      $sql = "INSERT INTO NOTA_CRITERIO (ID_USUARIO, ID_CRITERIO, NOTA) VALUES (:ID_USUARIO, :ID_CRITERIO, :NOTA)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'ID_USUARIO' => $id_usuario,
        'ID_CRITERIO' => $id_criterio,
        'NOTA' => $nota
      ]);

      // Almacenar el ID de la nota insertada para luego vincularla con la tabla de calificaciones
      $id_nota_criterios[] = $pdo->lastInsertId();
    }

    // Asegurar que el total del porcentaje es correcto antes de calcular el promedio
    $promedio_actividad = ($total_porcentaje_proyecto > 0) ? ($total_calificacion_proyecto / $total_porcentaje_proyecto) * 100 : 0;

    // Insertar la calificación global del proyecto en la tabla CALIFICACION
    $sql = "INSERT INTO CALIFICACION (ID_ACTIVIDAD, CALIFICACION, ID_PROYECTO) VALUES (:ID_ACTIVIDAD, :CALIFICACION, :ID_PROYECTO)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'ID_ACTIVIDAD' => $id_actividad,
      'CALIFICACION' => $promedio_actividad,
      'ID_PROYECTO' => $id_proyecto
    ]);

    $id_calificacion = $pdo->lastInsertId();

    // Relacionar cada nota de NOTA_CRITERIO con la calificación global en la tabla intermedia Nota_Criterio_Calificacion
    foreach ($id_nota_criterios as $id_nota_criterio) {
      $sql = "INSERT INTO NOTA_CRITERIO_CALIFICACION (ID_NOTA_CRITERIO, ID_CALIFICACION) VALUES (:ID_NOTA_CRITERIO, :ID_CALIFICACION)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        'ID_NOTA_CRITERIO' => $id_nota_criterio,
        'ID_CALIFICACION' => $id_calificacion
      ]);
    }

    if ($id_rol_usuario == 2) {
      // Calcular el promedio de calificación de los administradores
      $sql_admin = "SELECT SUM(C.CALIFICACION * (A.PORCENTAJE / 100)) AS PROMEDIO_ADMIN
              FROM CALIFICACION C
              JOIN ACTIVIDAD A ON C.ID_ACTIVIDAD = A.ID_ACTIVIDAD
              WHERE C.ID_PROYECTO = :ID_PROYECTO
              AND A.ID_PERMISO = (SELECT ID_PERMISO FROM PERMISOS WHERE PERMISO = 'Ad')";
      $stmt_admin = $pdo->prepare($sql_admin);
      $stmt_admin->execute(['ID_PROYECTO' => $id_proyecto]);
      $result_admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
      $promedio_admin = $result_admin['PROMEDIO_ADMIN'] ? $result_admin['PROMEDIO_ADMIN'] : 0;

      // Obtener el promedio de calificación de los jurados
      $sql_jurado = "SELECT SUM(DISTINCT C.CALIFICACION * (A.PORCENTAJE / 100)) AS PROMEDIO_JURADO
               FROM CALIFICACION C
               JOIN NOTA_Criterio_Calificacion NCC ON C.ID_CALIFICACION = NCC.ID_CALIFICACION
               JOIN NOTA_CRITERIO NC ON NCC.ID_NOTA_CRITERIO = NC.ID_NOTACRITERIO
               JOIN ACTIVIDAD A ON C.ID_ACTIVIDAD = A.ID_ACTIVIDAD
               WHERE C.ID_PROYECTO = :ID_PROYECTO
               AND NC.ID_USUARIO = :ID_USUARIO
               AND A.ID_PERMISO = (SELECT ID_PERMISO FROM PERMISOS WHERE PERMISO = 'Ju')";
      $stmt_jurado = $pdo->prepare($sql_jurado);
      $stmt_jurado->execute([
        'ID_PROYECTO' => $id_proyecto,
        'ID_USUARIO' => $id_usuario
      ]);
      $result_jurado = $stmt_jurado->fetch(PDO::FETCH_ASSOC);
      $promedio_jurado = $result_jurado['PROMEDIO_JURADO'] ? $result_jurado['PROMEDIO_JURADO'] : 0;
      // Si no hay calificaciones, establece a 0

      // Calcular la calificación final sumando ambos promedios
      $calificacion_final = ($promedio_admin + $promedio_jurado); // El / 100 ya fue hecho en la consulta

      // Guardar en la tabla NOTAS
      $sql_insert = "INSERT INTO NOTAS (ID_USUARIO, ID_PROYECTO, COMENTARIOS, CALIFICACION, ID_CALIFICACION) 
               VALUES (:ID_USUARIO, :ID_PROYECTO, :COMENTARIOS, :CALIFICACION, :ID_CALIFICACION)";
      $stmt_insert = $pdo->prepare($sql_insert);
      $stmt_insert->execute([
        'ID_USUARIO' => $id_usuario,
        'ID_PROYECTO' => $id_proyecto,
        'COMENTARIOS' => $comentario,
        'CALIFICACION' => $calificacion_final,
        'ID_CALIFICACION' => $id_calificacion
      ]);

      // Almacenar el último ID insertado
      $id_nota = $pdo->lastInsertId(); // Esto debe realizarse en la consulta correspondiente a NOTA_CRITERIO

      $sql_suma_calificaciones = "SELECT SUM(CALIFICACION) AS SUMA_CALIFICACIONES, COUNT(*) AS CANTIDAD_USUARIOS
                                FROM NOTAS  
                                WHERE ID_PROYECTO = :ID_PROYECTO";
      $stmt_suma = $pdo->prepare($sql_suma_calificaciones);
      $stmt_suma->execute(['ID_PROYECTO' => $id_proyecto]);

      $result_suma = $stmt_suma->fetch(PDO::FETCH_ASSOC);
      $suma_calificaciones = $result_suma['SUMA_CALIFICACIONES'] ? $result_suma['SUMA_CALIFICACIONES'] : 0;
      $cantidad_usuarios = $result_suma['CANTIDAD_USUARIOS'] ? $result_suma['CANTIDAD_USUARIOS'] : 1; // Evitar división por cero

      // Calcular el promedio
      $promedio_final = $suma_calificaciones / $cantidad_usuarios;

      // Verificar si ya existe un registro en NFinal para este proyecto y actividad
      $sql_verificar_nfinal = "SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = :ID_PROYECTO";
      $stmt_verificar = $pdo->prepare($sql_verificar_nfinal);
      $stmt_verificar->execute(['ID_PROYECTO' => $id_proyecto]);

      $id_nfinal = $stmt_verificar->fetchColumn();

      if ($id_nfinal) {
        // Si ya existe, actualizar el registro
        $sql_actualizar_nfinal = "UPDATE NFINAL SET NOTA_FINAL = :NOTA_FINAL WHERE ID_NFINAL = :ID_NFINAL";
        $stmt_actualizar = $pdo->prepare($sql_actualizar_nfinal);
        $stmt_actualizar->execute(['NOTA_FINAL' => $promedio_final, 'ID_NFINAL' => $id_nfinal]);
      } else {
        // Si no existe, insertar un nuevo registro
        $sql_insertar_nfinal = "INSERT INTO NFINAL (ID_PROYECTO, ID_ACTIVIDAD, NOTA_FINAL) VALUES (:ID_PROYECTO, :ID_ACTIVIDAD, :NOTA_FINAL)";
        $stmt_insertar = $pdo->prepare($sql_insertar_nfinal);
        $stmt_insertar->execute(['ID_PROYECTO' => $id_proyecto, 'ID_ACTIVIDAD' => $id_actividad, 'NOTA_FINAL' => $promedio_final]);

        // Obtener el nuevo ID de NFinal
        $id_nfinal = $pdo->lastInsertId();
      }

      // Insertar en la tabla intermedia notas_nfinal
      $sql_insertar_intermedia = "INSERT INTO NOTAS_NFINAL (ID_NOTA, ID_NFINAL) VALUES (:ID_NOTA, :ID_NFINAL)";
      $stmt_intermedia = $pdo->prepare($sql_insertar_intermedia);
      $stmt_intermedia->execute(['ID_NOTA' => $id_nota, 'ID_NFINAL' => $id_nfinal]);
    }

    // Redirigir al ID de actividad
    $pdo->commit();
    header('Location: calificarAdmin.php?id=' . $id_actividad . '&success=1');
  } catch (Exception $e) {
    // En caso de error, revertir la transacción
    $pdo->rollBack();
    die("Error al calificar: " . $e->getMessage());
  }
}

if (isset($_GET['delete']) && isset($_GET['id_proyecto']) && isset($_GET['id_actividad'])) {
  $id_calificacion = $_GET['delete'];
  $id_proyecto = $_GET['id_proyecto'];
  $id_actividad = $_GET['id_actividad'];

  // Iniciar una transacción
  $pdo->beginTransaction();

  try {
    // Obtener el ID de calificación
    $sql_notas_id = "SELECT ID_CALIFICACION FROM CALIFICACION WHERE ID_CALIFICACION = :ID_CALIFICACION";
    $stmt_notas_id = $pdo->prepare($sql_notas_id);
    $stmt_notas_id->execute(['ID_CALIFICACION' => $id_calificacion]);
    $datos_notas_id = $stmt_notas_id->fetch(PDO::FETCH_ASSOC);

    if ($datos_notas_id) {
      // Obtener notas criterio asociadas
      $sql_notas_calificacion = "SELECT ID_NOTA_CRITERIO FROM NOTA_CRITERIO_CALIFICACION WHERE ID_CALIFICACION = :ID_CALIFICACION";
      $stmt_notas = $pdo->prepare($sql_notas_calificacion);
      $stmt_notas->execute(['ID_CALIFICACION' => $datos_notas_id['ID_CALIFICACION']]);
      $datos_notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC); // Cambiado a fetchAll para obtener un array

      if ($id_rol_usuario == 2) {
        $sql_notas_individuales = "SELECT ID_NOTAS FROM NOTAS WHERE ID_CALIFICACION = :ID_CALIFICACION";
        $stmt_notas_individuales = $pdo->prepare($sql_notas_individuales);
        $stmt_notas_individuales->execute(['ID_CALIFICACION' => $datos_notas_id['ID_CALIFICACION']]);
        $datos_notas_individuales = $stmt_notas_individuales->fetch(PDO::FETCH_ASSOC);
      }

      // Borrar notas criterio calificación
      $sql_delete_notas = "DELETE FROM NOTA_CRITERIO_CALIFICACION WHERE ID_CALIFICACION = :ID_CALIFICACION";
      $stmt_delete_notas = $pdo->prepare($sql_delete_notas);
      $stmt_delete_notas->execute(['ID_CALIFICACION' => $datos_notas_id['ID_CALIFICACION']]);

      // Eliminar cada nota criterio
      foreach ($datos_notas as $nota) {
        $sql_delete_notas_criterio = "DELETE FROM NOTA_CRITERIO WHERE ID_NOTACRITERIO = :ID_NOTA_CRITERIO";
        $stmt_delete_notas_criterio = $pdo->prepare($sql_delete_notas_criterio);
        $stmt_delete_notas_criterio->execute(['ID_NOTA_CRITERIO' => $nota['ID_NOTA_CRITERIO']]);
      }

      // Eliminar notas individuales si corresponde
      if ($id_rol_usuario == 2) {
        $sql_delete_notas_relacion = "DELETE FROM NOTAS_NFINAL WHERE ID_NOTA = :ID_NOTAS";
        $stmt_delete_notas_relacion = $pdo->prepare($sql_delete_notas_relacion);
        $stmt_delete_notas_relacion->execute(['ID_NOTAS' => $datos_notas_individuales['ID_NOTAS']]);

        $sql_delete_notas_individuales = "DELETE FROM NOTAS WHERE ID_NOTAS = :ID_NOTAS";
        $stmt_delete_notas_individuales = $pdo->prepare($sql_delete_notas_individuales);
        $stmt_delete_notas_individuales->execute(['ID_NOTAS' => $datos_notas_individuales['ID_NOTAS']]);
      }

      // Borrar calificación
      $sql_delete_notas_calificacion = "DELETE FROM CALIFICACION WHERE ID_CALIFICACION = :ID_CALIFICACION";
      $stmt_delete_notas_calificacion = $pdo->prepare($sql_delete_notas_calificacion);
      $stmt_delete_notas_calificacion->execute(['ID_CALIFICACION' => $datos_notas_id['ID_CALIFICACION']]);

      // Calcular el nuevo promedio de calificaciones
      $sql_suma_calificaciones = "SELECT SUM(CALIFICACION) AS SUMA_CALIFICACIONES, COUNT(*) AS CANTIDAD_USUARIOS FROM NOTAS WHERE ID_PROYECTO = :ID_PROYECTO";
      $stmt_suma = $pdo->prepare($sql_suma_calificaciones);
      $stmt_suma->execute(['ID_PROYECTO' => $id_proyecto]);

      $result_suma = $stmt_suma->fetch(PDO::FETCH_ASSOC);
      $suma_calificaciones = $result_suma['SUMA_CALIFICACIONES'] ?? 0;
      $cantidad_usuarios = $result_suma['CANTIDAD_USUARIOS'] ?? 1; // Evitar división por cero

      if ($cantidad_usuarios > 0) {
        $promedio_final = $suma_calificaciones / $cantidad_usuarios;

        // Verificar si ya existe un registro en NFinal para este proyecto y actividad
        $sql_verificar_nfinal = "SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = :ID_PROYECTO AND ID_ACTIVIDAD = :ID_ACTIVIDAD";
        $stmt_verificar = $pdo->prepare($sql_verificar_nfinal);
        $stmt_verificar->execute(['ID_PROYECTO' => $id_proyecto, 'ID_ACTIVIDAD' => $id_actividad]);

        $id_nfinal = $stmt_verificar->fetchColumn();

        if ($id_nfinal) {
          // Si ya existe, actualizar el registro
          $sql_actualizar_nfinal = "UPDATE NFINAL SET NOTA_FINAL = :NOTA_FINAL WHERE ID_NFINAL = :ID_NFINAL";
          $stmt_actualizar = $pdo->prepare($sql_actualizar_nfinal);
          $stmt_actualizar->execute(['NOTA_FINAL' => $promedio_final, 'ID_NFINAL' => $id_nfinal]);
        } else {
          // Si no existe, insertar un nuevo registro
          $sql_insertar_nfinal = "INSERT INTO NFINAL (ID_PROYECTO, ID_ACTIVIDAD, NOTA_FINAL) VALUES (:ID_PROYECTO, :ID_ACTIVIDAD, :NOTA_FINAL)";
          $stmt_insertar = $pdo->prepare($sql_insertar_nfinal);
          $stmt_insertar->execute(['ID_PROYECTO' => $id_proyecto, 'ID_ACTIVIDAD' => $id_actividad, 'NOTA_FINAL' => $promedio_final]);
        }
      } else {
        $sql_delete_notas_final = "DELETE FROM NFINAL WHERE ID_PROYECTO = :ID_PROYECTO";
        $stmt_delete_notas_final = $pdo->prepare($sql_delete_notas_final);
        $stmt_delete_notas_final->execute(['ID_PROYECTO' => $id_proyecto]);
      }

      // Confirmar la transacción
      $pdo->commit();
      header('Location: calificarAdmin.php?id=' . $id_actividad . '&success=1');
      exit;
    } else {
      // Si no se encontró la calificación
      header('Location: calificarAdmin.php?id=' . $id_actividad . '&error=doesntexist');
      exit;
    }
  } catch (Exception $e) {
    // Si ocurre un error, revertir la transacción
    $pdo->rollBack();
    // Manejar el error de forma adecuada (puedes registrar el error o mostrar un mensaje)
    echo "Error: " . $e->getMessage();
    exit;
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Panel Administrador</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="panel_admin.php" class="logo d-flex align-items-center">
        <img src="assets/img/logo.png" alt="" style="width: 100px; height: auto;">
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">
        <li class="nav-item dropdown pe-3">
          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $nombre_usuario; ?></span>
          </a><!-- End Profile Iamge Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6><?php echo $nombre_usuario; ?></h6>
              <span><?php echo $rol; ?></span>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="users-profileadmin.php">
                <i class="bi bi-person"></i>
                <span>Perfil</span>
              </a>
            </li>

            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="index.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar Sesion</span>
              </a>
            </li>
          </ul><!-- End Profile Dropdown Items -->
        </li><!-- End Profile Nav -->
      </ul>
    </nav><!-- End Icons Navigation -->
  </header><!-- End Header -->

  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-item">
        <a class="nav-link collapsed" href="panel_admin.php">
          <i class="bi bi-grid"></i>
          <span>Panel Administrador</span>
        </a>
      </li><!-- End Dashboard Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="addusers.php">
          <i class="bi bi-person"></i>
          <span>Usuarios</span>
        </a>
      </li><!-- End Profile Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="addprojects.php">
          <i class="bi bi-card-list"></i>
          <span>Proyectos </span>
        </a>
      </li><!-- End Register Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="addfunds.php">
          <i class="bi bi-gem"></i>
          <span>Fondos</span>
        </a>
      </li><!-- End Icons Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="addactivities.php">
          <i class="bi bi-search"></i>
          <span>Actividades</span>
        </a>
      </li><!-- End Activities Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-menu-button-wide"></i><span>Resultados</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="components-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="adminrindividual.php">
              <i class="bi bi-circle"></i><span>Resultados Individuales</span>
            </a>
          </li>
          <li>
            <a href="admindetallado.php">
              <i class="bi bi-circle"></i><span>Resultados Específicos</span>
            </a>
          </li>
          <li>
            <a href="adminiglobal.php">
              <i class="bi bi-circle"></i><span>Resultados Globales</span>
            </a>
          </li>
        </ul>
      </li><!-- End Components Nav -->

      <li class="nav-item">
        <a class="nav-link" data-bs-target="#evaluacion-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-check-circle"></i><span>Evaluación</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="evaluacion-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
          <!-- Aquí se cargarán dinámicamente las actividades -->
          <?php
          include 'bdd/database.php'; // Asegúrate de tener una conexión PDO en este archivo

          $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD"; // Ajusta el nombre de la tabla y los campos según tu base de datos
          $stmt = $pdo->prepare($sql);
          $stmt->execute();
          $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if ($actividades) {
            foreach ($actividades as $actividad) {
              echo '<li>';
              echo '<a href="calificarAdmin.php?id=' . $actividad["ID_ACTIVIDAD"] . '">';
              echo '<i class="bi bi-circle"></i><span>' . $actividad["NOM_ACTIVIDAD"] . ' (' . $actividad["PORCENTAJE"] . '%)</span>';
              echo '</a>';
              echo '</li>';
            }
          } else {
            echo '<li><a href="#"><span>No hay actividades disponibles</span></a></li>';
          }
          ?>
        </ul>
      </li><!-- End Evaluación Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="users-profileadmin.php">
          <i class="bi bi-person"></i>
          <span>Perfil</span>
        </a>
      </li><!-- End Profile Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="manual_admin.php">
          <i class="bi bi-question-circle"></i>
          <span>Manual</span>
        </a>
      </li><!-- End F.A.Q Page Nav -->
    </ul>
  </aside><!-- End Sidebar-->

  <main id="main" class="main">
    <div class="pagetitle">
      <h1>Calificaciones</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_admin.php">Inicio</a></li>
          <li class="breadcrumb-item active">Calificaciones</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <!-- Inicio de la rúbrica del criterio -->
    <section class="section dashboard">
      <div class="row">
        <div class="col-12">
          <div class="card recent-sales overflow-auto">
            <div class="card-body">
              <h5 class="card-title">Rúbrica de criterio <span></span></h5>

              <div class="row mb-5">
                <?php if ($criterios): ?>
                  <?php foreach ($criterios as $criterio): ?>
                    <div class="col-md-4 d-flex">
                      <div class="card h-100">
                        <div class="card-body">
                          <h5 class="card-title"><?php echo htmlspecialchars($criterio['CRITERIO']); ?></h5>
                          <p><?php echo htmlspecialchars($criterio['DESCRIPCION']); ?></p>
                          <p>Ponderación: <?php echo htmlspecialchars($criterio['PORCENTAJE']); ?>%</p>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p>No hay criterios disponibles para esta actividad o proyecto.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section dashboard">
      <div class="row">
        <div class="col-12">
          <div class="card recent-sales overflow-auto">
            <div class="card-body">
              <h5 class="card-title">Evaluación de Proyectos</h5>

              <?php if ($permiso_valido): ?>
                <form method="POST">
                  <?php if (isset($_GET['error'])) {
                    switch ($_GET['error']) {
                      case 'empty_fields':
                        echo "<p id='error-message' style='color: red;'>Por favor, complete todos los campos.</p>";
                        break;
                      case 'already_rated':
                        echo "<p id='error-message' style='color: red;'>Ya has calificado este proyecto para esta actividad.</p>";
                        break;
                      case 'doesntexist':
                        echo "<p id='error-message' style='color: red;'>No puedes eliminar esa nota</p>";
                        break;
                    }
                  }
                  ?>
                  <script>
                    // Hacer que el mensaje desaparezca después de 3 segundos
                    setTimeout(function() {
                      var errorMessage = document.getElementById('error-message');
                      if (errorMessage) {
                        errorMessage.style.display = 'none';
                      }
                    }, 3000); // 3000 milisegundos = 3 segundos
                  </script>

                  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                  <input type="hidden" name="idUsuario" value="<?php echo $id_usuario; ?>">
                  <div class="row">
                    <div class="col-12">
                      <div class="mb-4">
                        <label for="projectFilter" class="form-label">Seleccione el proyecto a evaluar:</label>
                        <select id="projectFilter" name="idProyecto" class="form-select" onchange="loadCriterios()">
                          <option value="">Selecciona un Proyecto</option>
                          <?php foreach ($proyectos as $proyecto): ?>
                            <option value="<?php echo $proyecto['ID_PROYECTO']; ?>"
                              data-nombre="<?php echo htmlspecialchars($proyecto['PROYECTO']); ?>">
                              <?php echo htmlspecialchars($proyecto['PROYECTO']); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="table-responsive">
                        <table class="table table-bordered text-center" id="criteriosTable">
                          <thead>
                            <tr id="criteriosHeader">
                              <th scope="col">Proyecto</th>
                              <?php foreach ($criterios_mostrar as $criterio): ?>
                                <th scope="col"><?php echo htmlspecialchars($criterio['CRITERIO']); ?></th>
                              <?php endforeach; ?>
                            </tr>
                          </thead>
                          <tbody id="criteriosBody">
                            <tr>
                              <td><?php echo htmlspecialchars($nombreProyecto ?? ''); ?></td>
                              <?php foreach ($criterios_mostrar as $criterio): ?>
                                <td>
                                  <input type="number" name="notas[<?php echo $criterio['ID_CRITERIO']; ?>]"
                                    placeholder="Nota" class="form-control" min="0" max="10" step="0.01" required>
                                </td>
                              <?php endforeach; ?>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>

                  <button type="submit" class="btn btn-primary mt-3">Enviar Evaluación</button>
                </form>
              <?php else: ?>
                <p>No tienes permiso para evaluar este proyecto.</p>
              <?php endif; ?>

              <br>

              <h5 class="card-title">Proyectos Calificados/Pendientes</h5>
              
              <?php if ($permiso_tabla): ?>
                <div class="table-responsive">
                  <?php
                  // Preparar la consulta SQL con PDO
                  $query = "SELECT 
                            p.ID_PROYECTO,
                            p.PROYECTO, 
                            c.ID_CALIFICACION, 
                            c.ID_ACTIVIDAD,
                            nc.NOTA,
                            nc.ID_CRITERIO,
                            c.CALIFICACION AS CALIFICACION_FINAL,
                            n.COMENTARIOS
                          FROM PROYECTO p
                          LEFT JOIN CALIFICACION c ON p.ID_PROYECTO = c.ID_PROYECTO
                          LEFT JOIN NOTA_CRITERIO_CALIFICACION ncc ON c.ID_CALIFICACION = ncc.ID_CALIFICACION
                          LEFT JOIN NOTA_CRITERIO nc ON ncc.ID_NOTA_CRITERIO = nc.ID_NOTACRITERIO
                          LEFT JOIN NOTAS n ON c.ID_CALIFICACION = n.ID_CALIFICACION
                          LEFT JOIN USUARIO u ON nc.ID_USUARIO = u.ID_USUARIO
                          WHERE u.ID_ROL = 1
                          ORDER BY p.ID_PROYECTO";

                  // Ejecutar la consulta
                  $stmt = $pdo->prepare($query);
                  $stmt->execute();
                  $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  // Array para organizar los datos por proyecto
                  $proyectos_tabla = [];

                  foreach ($resultados as $row) {
                    $proyecto = $row['PROYECTO'];

                    // Inicializar el proyecto si no existe en el array
                    if (!isset($proyectos_tabla[$proyecto])) {
                      $proyectos_tabla[$proyecto] = [
                        'id_calificacion' => $row['ID_CALIFICACION'],
                        'id_proyecto' => $row['ID_PROYECTO'],
                        'id_actividad' => $row['ID_ACTIVIDAD'],
                        'notas_criterios' => [],
                        'calificacion_final' => $row['CALIFICACION_FINAL'],
                        'comentarios' => $row['COMENTARIOS']
                      ];
                    }

                    // Añadir la nota del criterio al proyecto
                    if ($row['ID_CRITERIO']) {
                      $proyectos_tabla[$proyecto]['notas_criterios'][$row['ID_CRITERIO']] = $row['NOTA'];
                    }
                  }

                  // Renderizar las filas de la tabla
                  ?>
                  <table class="table table-bordered text-center">
                    <thead>
                      <tr id="criteriosHeader">
                        <th scope="col">Proyecto</th>
                        <?php foreach ($criterios_mostrar as $criterio): ?>
                          <th scope="col"><?php echo htmlspecialchars($criterio['CRITERIO']); ?></th>
                        <?php endforeach; ?>
                        <?php if ($actividad_porcentaje !== 30): // Solo mostrar Nota Final si NO es 30% ?>
                          <th scope="col">Nota Final</th>
                        <?php endif; ?>
                        <th scope="col">Acciones</th>
                      </tr>
                    </thead>
                    <tbody id="statusTable">
                      <?php foreach ($proyectos_tabla as $nombreProyecto => $detalles): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($nombreProyecto); ?></td>

                          <!-- Mostrar las notas de los criterios -->
                          <?php foreach ($criterios_mostrar as $criterio): ?>
                            <?php
                            // Mostrar la nota si existe, de lo contrario 'N/A'
                            $nota = isset($detalles['notas_criterios'][$criterio['ID_CRITERIO']]) ? $detalles['notas_criterios'][$criterio['ID_CRITERIO']] : 'N/A';
                            ?>
                            <td><?php echo htmlspecialchars($nota); ?></td>
                          <?php endforeach; ?>

                          <!-- Mostrar la calificación final -->
                          <?php if ($actividad_porcentaje !== 30): ?>
                            <td>
                              <?php echo htmlspecialchars($detalles['calificacion_final'] ? $detalles['calificacion_final'] : 'N/A'); ?>
                            </td>
                          <?php endif; ?>

                          <!-- Botones de acción (modificar o agregar) -->
                          <td>
                            <a href="calificarAdmin.php?delete=<?php echo htmlspecialchars($detalles['id_calificacion']); ?>&id_proyecto=<?php echo htmlspecialchars($detalles['id_proyecto']); ?>&id_actividad=<?php echo htmlspecialchars($detalles['id_actividad']); ?>"
                              class="btn btn-danger btn-sm"
                              onclick="return confirm('¿Estás seguro de eliminar esta actividad?')">
                              <i class='fas fa-trash-alt'></i>
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <p>Dirigase a resultados individuales</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservados.
    </div>
  </footer><!-- End Footer -->

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.js"></script>
  <script src="assets/vendor/jquery/jquery.min.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/quill/quill.min.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

  <script>
    function loadCriterios() {
      const projectFilter = document.getElementById('projectFilter');
      const selectedOption = projectFilter.options[projectFilter.selectedIndex];
      const nombreProyecto = selectedOption.getAttribute('data-nombre');

      const criteriosBody = document.getElementById('criteriosBody');
      criteriosBody.innerHTML = ''; // Limpiar contenido anterior

      if (!nombreProyecto) {
        return; // No hacer nada si no hay proyecto seleccionado
      }

      // Mostrar los criterios con campos para notas
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${nombreProyecto}</td>
        <?php foreach ($criterios_mostrar as $criterio): ?>
          <td><input type="number" name="notas[<?php echo $criterio['ID_CRITERIO']; ?>]" placeholder="Nota" class="form-control" min="0" max="10" step="0.01" required></td>
        <?php endforeach; ?>
      `;
      criteriosBody.appendChild(row);
    }
  </script>
  
  <script>
    document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('active');
      document.getElementById('main').classList.toggle('active');
    });
  </script>
</body>
</html>