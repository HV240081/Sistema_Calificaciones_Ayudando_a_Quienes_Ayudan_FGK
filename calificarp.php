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
$id_rol_usuario = $row['ID_ROL'];

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

// Verifica si se ha recibido un ID de actividad en la URL o en el POST
if (isset($_GET['id'])) {
    $id_actividad = $_GET['id'];
} elseif (isset($_POST['id_actividad'])) {
    $id_actividad = $_POST['id_actividad'];
}

// Si tenemos un ID de actividad, obtener los criterios y permisos
if ($id_actividad) {
    // Consulta para obtener los criterios y el permiso relacionados con la actividad
    $sql = "SELECT C.ID_CRITERIO, C.CRITERIO, C.DESCRIPCION, C.PORCENTAJE, A.ID_PERMISO 
          FROM CRITERIO C 
          JOIN ACTIVIDAD A ON A.ID_ACTIVIDAD = C.ID_ACTIVIDAD
          WHERE C.ID_ACTIVIDAD = :ID_ACTIVIDAD";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['ID_ACTIVIDAD' => $id_actividad]);
    $criterios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener información adicional de la actividad
    $actividad_actual = null;
    $es_primera_fase = false;
    $es_pitch_70 = false;
    
    try {
        $stmtAct = $pdo->prepare("SELECT NOM_ACTIVIDAD, PORCENTAJE, ID_PERMISO FROM ACTIVIDAD WHERE ID_ACTIVIDAD = :ID");
        $stmtAct->execute(['ID' => $id_actividad]);
        $actividad_actual = $stmtAct->fetch(PDO::FETCH_ASSOC);
        
        if ($actividad_actual) {
            $nombre_act = isset($actividad_actual['NOM_ACTIVIDAD']) ? mb_strtolower($actividad_actual['NOM_ACTIVIDAD']) : '';
            $porcentaje_act = isset($actividad_actual['PORCENTAJE']) ? floatval($actividad_actual['PORCENTAJE']) : 0.0;
            $permiso_actividad = $actividad_actual['ID_PERMISO'];
            
            $es_primera_fase = (strpos($nombre_act, 'primera') !== false);
            $es_pitch_70 = ((strpos($nombre_act, 'pitch') !== false) && ($porcentaje_act >= 69.5));
        }
    } catch (Exception $e) { 
        error_log("Error al obtener actividad: " . $e->getMessage());
    }

    // Si no obtuvimos el permiso de la actividad principal, intentar de los criterios
    if (empty($permiso_actividad) && !empty($criterios)) {
        $permiso_actividad = $criterios[0]['ID_PERMISO'];
    }

    // Filtrar criterios a mostrar en tabla (solo primera fase -> Presentación general del Proyecto)
    $criterios_mostrar = $criterios;
    if ($es_primera_fase) {
        $criterios_mostrar = array_values(array_filter($criterios, function($c){
            return isset($c['CRITERIO']) && mb_strtolower($c['CRITERIO']) === mb_strtolower('Presentación general del Proyecto');
        }));
    }
    
    // Mapear por nombre para la tabla del 70%
    $mapa_ids = [];
    foreach ($criterios as $c) {
        if (isset($c['CRITERIO']) && isset($c['ID_CRITERIO'])) {
            $mapa_ids[mb_strtolower($c['CRITERIO'])] = $c['ID_CRITERIO'];
        }
    }
}

// LÓGICA DE PERMISOS MEJORADA
$permiso_valido = false;
$permiso_tabla = false;

if ($permiso_actividad && $id_rol_usuario) {
    // Jurado Principal (ID_ROL = 3) y Jurado Regular (ID_ROL = 2) pueden calificar actividades con permiso de jurado (ID_PERMISO = 1)
    if ($permiso_actividad == 1 && ($id_rol_usuario == 2 || $id_rol_usuario == 3)) {
        $permiso_valido = true;
        $permiso_tabla = true;
    }
    // Administrador (ID_ROL = 1) puede calificar actividades con permiso de administrador (ID_PERMISO = 2)
    elseif ($permiso_actividad == 2 && $id_rol_usuario == 1) {
        $permiso_valido = true;
        $permiso_tabla = false;
    }
}

// Procesar el envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error: Token CSRF inválido.");
    }

    $id_usuario = $_POST['idUsuario'];
    $id_proyecto = $_POST['idProyecto'];
    $notas = $_POST['notas'];
    $comentario = isset($_POST['comentario']) && !empty($_POST['comentario']) ? $_POST['comentario'] : '';
    $id_nota_criterios = [];
    $total_calificacion_proyecto = 0;
    $total_porcentaje_proyecto = 0;

    // Validar campos requeridos
    if (empty($id_usuario) || empty($id_proyecto) || empty($notas)) {
        header('Location: calificarp.php?id=' . $id_actividad . '&error=empty_fields');
        exit;
    }

    // Verificar si el usuario ya calificó este proyecto
    $sql = "SELECT COUNT(*) FROM NOTA_CRITERIO NC
          JOIN NOTA_CRITERIO_CALIFICACION NCC ON NC.ID_NOTACRITERIO = NCC.ID_NOTA_CRITERIO
          JOIN CALIFICACION C ON NCC.ID_CALIFICACION = C.ID_CALIFICACION
          WHERE NC.ID_USUARIO = :ID_USUARIO 
          AND C.ID_PROYECTO = :ID_PROYECTO";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['ID_USUARIO' => $id_usuario, 'ID_PROYECTO' => $id_proyecto]);
    $alreadyRated = $stmt->fetchColumn();

    if ($alreadyRated > 0) {
        header('Location: calificarp.php?id=' . $id_actividad . '&error=already_rated');
        exit;
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Verificar si es pitch 70%
        $es_pitch_70_post = false;
        if (isset($id_actividad)) {
            $stmtAct = $pdo->prepare("SELECT NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD WHERE ID_ACTIVIDAD = :ID");
            $stmtAct->execute(['ID' => $id_actividad]);
            $actividad_actual = $stmtAct->fetch(PDO::FETCH_ASSOC);
            if ($actividad_actual) {
                $nombre_act = isset($actividad_actual['NOM_ACTIVIDAD']) ? mb_strtolower($actividad_actual['NOM_ACTIVIDAD']) : '';
                $porcentaje_act = isset($actividad_actual['PORCENTAJE']) ? floatval($actividad_actual['PORCENTAJE']) : 0.0;
                $es_pitch_70_post = ((strpos($nombre_act, 'pitch') !== false) && ($porcentaje_act >= 69.5));
            }
        }

        // Procesar notas
        foreach ($notas as $id_criterio => $nota) {
            // Obtener el porcentaje del criterio
            $sql = "SELECT PORCENTAJE FROM CRITERIO WHERE ID_CRITERIO = :ID_CRITERIO";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['ID_CRITERIO' => $id_criterio]);
            $porcentaje = $stmt->fetchColumn();

            // Calcular la nota ponderada
            $nota_ponderada = $nota * ($porcentaje / 100);
            $total_calificacion_proyecto += $nota_ponderada;
            $total_porcentaje_proyecto += $porcentaje;

            // Insertar la nota en la tabla NOTA_CRITERIO
            $sql = "INSERT INTO NOTA_CRITERIO (ID_USUARIO, ID_CRITERIO, NOTA) VALUES (:ID_USUARIO, :ID_CRITERIO, :NOTA)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'ID_USUARIO' => $id_usuario,
                'ID_CRITERIO' => $id_criterio,
                'NOTA' => $nota
            ]);

            $id_nota_criterios[] = $pdo->lastInsertId();
        }

        // Calcular promedio de actividad
        $promedio_actividad = ($total_porcentaje_proyecto > 0) ? ($total_calificacion_proyecto / $total_porcentaje_proyecto) * 100 : 0;

        // Aplicar el 70% al pitch si corresponde
        if ($es_pitch_70_post) {
            $promedio_actividad = $promedio_actividad * 0.70;
        }

        // Insertar la calificación global del proyecto
        $sql = "INSERT INTO CALIFICACION (ID_ACTIVIDAD, CALIFICACION, ID_PROYECTO) VALUES (:ID_ACTIVIDAD, :CALIFICACION, :ID_PROYECTO)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'ID_ACTIVIDAD' => $id_actividad,
            'CALIFICACION' => $promedio_actividad,
            'ID_PROYECTO' => $id_proyecto
        ]);

        $id_calificacion = $pdo->lastInsertId();

        // Relacionar cada nota de NOTA_CRITERIO con la calificación global
        foreach ($id_nota_criterios as $id_nota_criterio) {
            $sql = "INSERT INTO NOTA_CRITERIO_CALIFICACION (ID_NOTA_CRITERIO, ID_CALIFICACION) VALUES (:ID_NOTA_CRITERIO, :ID_CALIFICACION)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'ID_NOTA_CRITERIO' => $id_nota_criterio,
                'ID_CALIFICACION' => $id_calificacion
            ]);
        }

        // Lógica específica para jurados principales (ID_ROL = 3)
        if ($id_rol_usuario == 3) {
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
               JOIN NOTA_CRITERIO_CALIFICACION NCC ON C.ID_CALIFICACION = NCC.ID_CALIFICACION
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

            // Calcular la calificación final
            $calificacion_final = ($promedio_admin + $promedio_jurado);

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

            $id_nota = $pdo->lastInsertId();

            // Calcular suma de calificaciones
            $sql_suma_calificaciones = "SELECT SUM(CALIFICACION) AS SUMA_CALIFICACIONES, COUNT(*) AS CANTIDAD_USUARIOS
                                FROM NOTAS  
                                WHERE ID_PROYECTO = :ID_PROYECTO";
            $stmt_suma = $pdo->prepare($sql_suma_calificaciones);
            $stmt_suma->execute(['ID_PROYECTO' => $id_proyecto]);
            $result_suma = $stmt_suma->fetch(PDO::FETCH_ASSOC);
            
            $suma_calificaciones = $result_suma['SUMA_CALIFICACIONES'] ? $result_suma['SUMA_CALIFICACIONES'] : 0;
            $cantidad_usuarios = $result_suma['CANTIDAD_USUARIOS'] ? $result_suma['CANTIDAD_USUARIOS'] : 1;

            // Calcular el promedio
            $promedio_final = $suma_calificaciones / $cantidad_usuarios;

            // Verificar si ya existe un registro en NFinal para este proyecto
            $sql_verificar_nfinal = "SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = :ID_PROYECTO";
            $stmt_verificar = $pdo->prepare($sql_verificar_nfinal);
            $stmt_verificar->execute(['ID_PROYECTO' => $id_proyecto]);
            $id_nfinal = $stmt_verificar->fetchColumn();

            if ($id_nfinal) {
                // Actualizar registro existente
                $sql_actualizar_nfinal = "UPDATE NFINAL SET NOTA_FINAL = :NOTA_FINAL WHERE ID_NFINAL = :ID_NFINAL";
                $stmt_actualizar = $pdo->prepare($sql_actualizar_nfinal);
                $stmt_actualizar->execute(['NOTA_FINAL' => $promedio_final, 'ID_NFINAL' => $id_nfinal]);
            } else {
                // Insertar nuevo registro
                $sql_insertar_nfinal = "INSERT INTO NFINAL (ID_PROYECTO, ID_ACTIVIDAD, NOTA_FINAL) VALUES (:ID_PROYECTO, :ID_ACTIVIDAD, :NOTA_FINAL)";
                $stmt_insertar = $pdo->prepare($sql_insertar_nfinal);
                $stmt_insertar->execute(['ID_PROYECTO' => $id_proyecto, 'ID_ACTIVIDAD' => $id_actividad, 'NOTA_FINAL' => $promedio_final]);
                $id_nfinal = $pdo->lastInsertId();
            }

            // Insertar en la tabla intermedia notas_nfinal
            $sql_insertar_intermedia = "INSERT INTO NOTAS_NFINAL (ID_NOTA, ID_NFINAL) VALUES (:ID_NOTA, :ID_NFINAL)";
            $stmt_intermedia = $pdo->prepare($sql_insertar_intermedia);
            $stmt_intermedia->execute(['ID_NOTA' => $id_nota, 'ID_NFINAL' => $id_nfinal]);
        }

        $pdo->commit();
        header('Location: calificarp.php?id=' . $id_actividad . '&success=1');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al calificar: " . $e->getMessage());
    }
}

// Lógica para eliminar calificaciones
if (isset($_GET['delete']) && isset($_GET['id_proyecto']) && isset($_GET['id_actividad'])) {
    $id_calificacion = $_GET['delete'];
    $id_proyecto = $_GET['id_proyecto'];
    $id_actividad = $_GET['id_actividad'];

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
            $datos_notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);

            // Para jurados principales, obtener notas individuales
            $datos_notas_individuales = null;
            if ($id_rol_usuario == 3) {
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

            // Eliminar notas individuales si corresponde (para jurados principales)
            if ($id_rol_usuario == 3 && $datos_notas_individuales) {
                // Eliminar relación de notas finales
                $sql_delete_notas_relacion = "DELETE FROM NOTAS_NFINAL WHERE ID_NOTA = :ID_NOTAS";
                $stmt_delete_notas_relacion = $pdo->prepare($sql_delete_notas_relacion);
                $stmt_delete_notas_relacion->execute(['ID_NOTAS' => $datos_notas_individuales['ID_NOTAS']]);

                // Eliminar notas individuales
                $sql_delete_notas_individuales = "DELETE FROM NOTAS WHERE ID_NOTAS = :ID_NOTAS";
                $stmt_delete_notas_individuales = $pdo->prepare($sql_delete_notas_individuales);
                $stmt_delete_notas_individuales->execute(['ID_NOTAS' => $datos_notas_individuales['ID_NOTAS']]);
            }

            // Borrar calificación
            $sql_delete_notas_calificacion = "DELETE FROM CALIFICACION WHERE ID_CALIFICACION = :ID_CALIFICACION";
            $stmt_delete_notas_calificacion = $pdo->prepare($sql_delete_notas_calificacion);
            $stmt_delete_notas_calificacion->execute(['ID_CALIFICACION' => $datos_notas_id['ID_CALIFICACION']]);

            // Recalcular promedios si es jurado principal
            if ($id_rol_usuario == 3) {
                $sql_suma_calificaciones = "SELECT SUM(CALIFICACION) AS SUMA_CALIFICACIONES, COUNT(*) AS CANTIDAD_USUARIOS FROM NOTAS WHERE ID_PROYECTO = :ID_PROYECTO";
                $stmt_suma = $pdo->prepare($sql_suma_calificaciones);
                $stmt_suma->execute(['ID_PROYECTO' => $id_proyecto]);
                $result_suma = $stmt_suma->fetch(PDO::FETCH_ASSOC);
                
                $suma_calificaciones = $result_suma['SUMA_CALIFICACIONES'] ?? 0;
                $cantidad_usuarios = $result_suma['CANTIDAD_USUARIOS'] ?? 1;

                if ($cantidad_usuarios > 0) {
                    $promedio_final = $suma_calificaciones / $cantidad_usuarios;

                    // Verificar si ya existe un registro en NFinal
                    $sql_verificar_nfinal = "SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = :ID_PROYECTO AND ID_ACTIVIDAD = :ID_ACTIVIDAD";
                    $stmt_verificar = $pdo->prepare($sql_verificar_nfinal);
                    $stmt_verificar->execute(['ID_PROYECTO' => $id_proyecto, 'ID_ACTIVIDAD' => $id_actividad]);
                    $id_nfinal = $stmt_verificar->fetchColumn();

                    if ($id_nfinal) {
                        $sql_actualizar_nfinal = "UPDATE NFINAL SET NOTA_FINAL = :NOTA_FINAL WHERE ID_NFINAL = :ID_NFINAL";
                        $stmt_actualizar = $pdo->prepare($sql_actualizar_nfinal);
                        $stmt_actualizar->execute(['NOTA_FINAL' => $promedio_final, 'ID_NFINAL' => $id_nfinal]);
                    } else {
                        $sql_insertar_nfinal = "INSERT INTO NFINAL (ID_PROYECTO, ID_ACTIVIDAD, NOTA_FINAL) VALUES (:ID_PROYECTO, :ID_ACTIVIDAD, :NOTA_FINAL)";
                        $stmt_insertar = $pdo->prepare($sql_insertar_nfinal);
                        $stmt_insertar->execute(['ID_PROYECTO' => $id_proyecto, 'ID_ACTIVIDAD' => $id_actividad, 'NOTA_FINAL' => $promedio_final]);
                    }
                } else {
                    $sql_delete_notas_final = "DELETE FROM NFINAL WHERE ID_PROYECTO = :ID_PROYECTO";
                    $stmt_delete_notas_final = $pdo->prepare($sql_delete_notas_final);
                    $stmt_delete_notas_final->execute(['ID_PROYECTO' => $id_proyecto]);
                }
            }

            $pdo->commit();
            header('Location: calificarp.php?id=' . $id_actividad . '&success=1');
            exit;
        } else {
            header('Location: calificarp.php?id=' . $id_actividad . '&error=doesntexist');
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
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

    <title>Panel Jurado</title>
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
    <link href="assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>

<body>

  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
      <a href="panel_juradop.php" class="logo d-flex align-items-center">
        <img src="assets/img/logo.png" alt="" style="width: 100px; height: auto;">
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">
        <li class="nav-item dropdown pe-3">
          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $nombre_usuario; ?></span>
          </a>

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6><?php echo $nombre_usuario; ?></h6>
              <span><?php echo $rol; ?></span>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center" href="user-profilep.php">
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
          </ul>
        </li>
      </ul>
    </nav>
  </header>

  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-item">
        <a class="nav-link collapsed" href="panel_juradop.php">
          <i class="bi bi-grid"></i>
          <span>Panel Jurado</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#evaluacion-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-check-circle"></i><span>Evaluación</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="evaluacion-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
          <?php
          $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD";
          $stmt = $pdo->prepare($sql);
          $stmt->execute();
          $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if ($actividades) {
            foreach ($actividades as $actividad) {
              echo '<li>';
              echo '<a href="calificarp.php?id=' . $actividad["ID_ACTIVIDAD"] . '">';
              echo '<i class="bi bi-circle"></i><span>' . $actividad["NOM_ACTIVIDAD"] . ' (' . $actividad["PORCENTAJE"] . '%)</span>';
              echo '</a>';
              echo '</li>';
            }
          } else {
            echo '<li><a href="#"><span>No hay actividades disponibles</span></a></li>';
          }
          ?>
        </ul>
      </li>

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-menu-button-wide"></i><span>Resultados</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="components-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
          <li>
            <a href="resultadosindividualesp.php">
              <i class="bi bi-circle"></i><span>Resultados Individuales</span>
            </a>
          </li>
          <li>
            <a href="resultadosdetalladosp.php">
              <i class="bi bi-circle"></i><span>Resultados Específicos</span>
            </a>
          </li>
          <li>
            <a href="resultadosglobalesp.php">
              <i class="bi bi-circle"></i><span>Resultados Globales</span>
            </a>
          </li>
        </ul>
      </li>

      <li class="nav-item">
        <a class="nav-link collapsed" href="fondos.php">
          <i class="bi bi-piggy-bank"></i>
          <span>Asignar Fondos</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link collapsed" href="resultadosfondosp.php">
          <i class="bi bi-gem"></i>
          <span>Fondos asignados</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link collapsed" href="user-profilep.php">
          <i class="bi bi-person"></i>
          <span>Perfil</span>
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link collapsed" href="manual_juradop.php">
          <i class="bi bi-question-circle"></i>
          <span>Manual</span>
        </a>
      </li>
    </ul>
  </aside>

  <main id="main" class="main">
    <div class="pagetitle">
      <h1>Calificaciones</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_juradop.php">Inicio</a></li>
          <li class="breadcrumb-item active">Calificaciones</li>
        </ol>
      </nav>
    </div>

    <!-- Rúbrica de criterio -->
    <section class="section dashboard">
      <div class="row">
        <div class="col-12">
          <div class="card recent-sales overflow-auto">
            <div class="card-body">
              <h5 class="card-title">Rúbrica de criterio <span></span></h5>
              <div class="row">
                <?php if ($es_primera_fase): ?>
                  <!-- Sin rúbrica en la primera evaluación (30%) -->
                  <p>Evaluación inicial - Presentación general del proyecto</p>
                <?php elseif ($es_pitch_70): ?>
                  <?php 
                    $rubrica_pitch = [
                      'Impacto Económico',
                      'Impacto Social', 
                      'Impacto Ambiental',
                      'Sostenibilidad del Proyecto',
                      'Crecimiento Potencial',
                      'Innovación',
                      'Promedio del Proyecto'
                    ];
                  ?>
                  <?php foreach ($rubrica_pitch as $nombre): ?>
                    <div class="col-md-4 d-flex">
                      <div class="card h-100">
                        <div class="card-body">
                          <h5 class="card-title"><?php echo htmlspecialchars($nombre); ?></h5>
                          <p>Ponderación: 14.3%</p>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <?php if ($criterios): ?>
                    <?php foreach ($criterios_mostrar as $criterio): ?>
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
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Evaluación de Proyectos -->
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
                    setTimeout(function() {
                      var errorMessage = document.getElementById('error-message');
                      if (errorMessage) {
                        errorMessage.style.display = 'none';
                      }
                    }, 3000);
                  </script>

                  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                  <input type="hidden" name="idUsuario" value="<?php echo $id_usuario; ?>">
                  <input type="hidden" name="id_actividad" value="<?php echo $id_actividad; ?>">
                  
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
                            <?php if ($es_pitch_70): ?>
                              <tr>
                                <th scope="col">Proyecto</th>
                                <th scope="col">Financiero</th>
                                <th scope="col">Social</th>
                                <th scope="col">Ambiental</th>
                                <th scope="col">Sostenibilidad</th>
                                <th scope="col">Crecimiento</th>
                                <th scope="col">Innovación</th>
                                <th scope="col">Promedio</th>
                              </tr>
                            <?php else: ?>
                              <tr id="criteriosHeader">
                                <th scope="col">Proyecto</th>
                                <?php foreach ($criterios_mostrar as $criterio): ?>
                                  <th scope="col"><?php echo htmlspecialchars($criterio['CRITERIO']); ?></th>
                                <?php endforeach; ?>
                                <?php if (!$es_primera_fase): ?>
                                  <th scope="col">Comentario</th>
                                <?php endif; ?>
                              </tr>
                            <?php endif; ?>
                          </thead>
                          <tbody id="criteriosBody">
                            <?php if ($es_pitch_70): ?>
                              <tr>
                                <td><?php echo htmlspecialchars($nombreProyecto ?? ''); ?></td>
                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Económico')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Social')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Ambiental')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Sostenibilidad del Proyecto')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Crecimiento Potencial')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Innovación')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Promedio del Proyecto')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                              </tr>
                            <?php else: ?>
                              <tr>
                                <td><?php echo htmlspecialchars($nombreProyecto ?? ''); ?></td>
                                <?php foreach ($criterios_mostrar as $criterio): ?>
                                  <td>
                                    <input type="number" name="notas[<?php echo $criterio['ID_CRITERIO']; ?>]" placeholder="Nota" class="form-control" min="0" max="10" step="0.01">
                                  </td>
                                <?php endforeach; ?>
                                <?php if (!$es_primera_fase): ?>
                                  <td><input type="text" name="comentario" placeholder="Comentario" class="form-control"></td>
                                <?php endif; ?>
                              </tr>
                            <?php endif; ?>
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
                  // Consulta para obtener proyectos calificados
                  $query = "SELECT p.ID_PROYECTO, p.PROYECTO, c.ID_CALIFICACION, c.ID_ACTIVIDAD, c.CALIFICACION
                            FROM PROYECTO p
                            LEFT JOIN CALIFICACION c ON p.ID_PROYECTO = c.ID_PROYECTO AND c.ID_ACTIVIDAD = :id_actividad
                            ORDER BY p.ID_PROYECTO";
                  $stmt = $pdo->prepare($query);
                  $stmt->execute(['id_actividad' => $id_actividad]);
                  $proyectos_calificados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                  ?>
                  
                  <table class="table table-bordered text-center">
                    <thead>
                      <tr>
                        <th>Proyecto</th>
                        <th>Estado</th>
                        <th>Calificación</th>
                        <th>Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($proyectos_calificados as $proyecto): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($proyecto['PROYECTO']); ?></td>
                          <td>
                            <?php if ($proyecto['ID_CALIFICACION']): ?>
                              <span class="badge bg-success">Calificado</span>
                            <?php else: ?>
                              <span class="badge bg-warning">Pendiente</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($proyecto['CALIFICACION']): ?>
                              <?php echo number_format($proyecto['CALIFICACION'], 2); ?>
                            <?php else: ?>
                              N/A
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($proyecto['ID_CALIFICACION']): ?>
                              <a href="calificarp.php?delete=<?php echo $proyecto['ID_CALIFICACION']; ?>&id_proyecto=<?php echo $proyecto['ID_PROYECTO']; ?>&id_actividad=<?php echo $id_actividad; ?>" 
                                 class="btn btn-danger btn-sm"
                                 onclick="return confirm('¿Estás seguro de eliminar esta calificación?')">
                                <i class="fas fa-trash"></i> Eliminar
                              </a>
                            <?php else: ?>
                              <span class="text-muted">-</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p>No tienes permisos para ver esta tabla.</p>
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
  </footer>

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
      criteriosBody.innerHTML = '';

      if (!nombreProyecto) {
        return;
      }

      const esPitch70 = <?php echo $es_pitch_70 ? 'true' : 'false'; ?>;
      const esPrimeraFase = <?php echo $es_primera_fase ? 'true' : 'false'; ?>;
      
      if (esPitch70) {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${nombreProyecto}</td>
          <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Económico')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
          <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Social')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
          <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Ambiental')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
          <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Sostenibilidad del Proyecto')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
          <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Crecimiento Potencial')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
          <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Innovación')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
          <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Promedio del Proyecto')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
        `;
        criteriosBody.appendChild(row);
      } else {
        const row = document.createElement('tr');
        let html = `<td>${nombreProyecto}</td>`;
        
        <?php foreach ($criterios_mostrar as $criterio): ?>
          html += `<td><input type="number" name="notas[<?php echo $criterio['ID_CRITERIO']; ?>]" placeholder="Nota" class="form-control" min="0" max="10" step="0.01" required></td>`;
        <?php endforeach; ?>
        
        <?php if (!$es_primera_fase): ?>
          html += `<td><input type="text" name="comentario" placeholder="Comentario" class="form-control"></td>`;
        <?php endif; ?>
        
        row.innerHTML = html;
        criteriosBody.appendChild(row);
      }
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