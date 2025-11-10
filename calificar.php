<?php

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}
require 'bdd/database.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// ========== VERIFICAR SI EL JURADO ESTÁ ACTIVO ==========
$sql_estado = "SELECT ESTADO FROM USUARIO WHERE ID_USUARIO = ?";
$stmt_estado = $pdo->prepare($sql_estado);
$stmt_estado->execute([$id_usuario]);
$estado_usuario = $stmt_estado->fetchColumn();

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

    // === Inserción: obtener información de la actividad y preparar filtros ===
    $actividad_actual = null;
    $es_primera_fase = false;
    $es_pitch_70 = false;
    try {
        $stmtAct = $pdo->prepare("SELECT NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD WHERE ID_ACTIVIDAD = :ID");
        $stmtAct->execute(['ID' => $id_actividad]);
        $actividad_actual = $stmtAct->fetch(PDO::FETCH_ASSOC);
        if ($actividad_actual) {
            $nombre_act = isset($actividad_actual['NOM_ACTIVIDAD']) ? mb_strtolower($actividad_actual['NOM_ACTIVIDAD']) : '';
            $porcentaje_act = isset($actividad_actual['PORCENTAJE']) ? floatval($actividad_actual['PORCENTAJE']) : 0.0;
            $es_primera_fase = (strpos($nombre_act, 'primera') !== false);
            $es_pitch_70 = ((strpos($nombre_act, 'pitch') !== false) && ($porcentaje_act >= 69.5));
        }
    } catch (Exception $e) { /* silencioso */ }

    // Filtrar criterios a mostrar en tabla (solo primera fase -> Presentación general del Proyecto)
    $criterios_mostrar = $criterios;
    if ($es_primera_fase) {
        $criterios_mostrar = array_values(array_filter($criterios, function($c){
            return isset($c['CRITERIO']) && mb_strtolower($c['CRITERIO']) === mb_strtolower('Presentación general del Proyecto');
        }));
    }
    // === Fin inserción ===
    // Mapear por nombre para la tabla del 70%
    $mapa_ids = [];
    foreach ($criterios as $c) {
        if (isset($c['CRITERIO']) && isset($c['ID_CRITERIO'])) {
            $mapa_ids[mb_strtolower($c['CRITERIO'])] = $c['ID_CRITERIO'];
        }
    }

    // Obtener el permiso de la actividad
    if (!empty($criterios)) {
        $permiso_actividad = $criterios[0]['ID_PERMISO'];
    }
}

$permiso_tabla = false;
if ($permiso_actividad === 1 && ($id_rol_usuario === 2 || $id_rol_usuario === 3)) {
    $permiso_tabla = true;
} elseif ($permiso_actividad === 2 && ($id_rol_usuario === 2 || $id_rol_usuario === 3)) {
    $permiso_tabla = false;
}

$permiso_valido = false;
if ($permiso_actividad === 1 && $id_rol_usuario === 2) {
    $permiso_valido = true;
} elseif ($permiso_actividad === 2 && $id_rol_usuario === 1) {
    $permiso_valido = true;
}

// ========== FUNCIÓN PARA OBTENER NOTA DEL 30% ==========
function obtenerNota30($pdo, $id_proyecto) {
    $sql_actividad_30 = "SELECT ID_ACTIVIDAD FROM ACTIVIDAD WHERE PORCENTAJE <= 30 AND NOM_ACTIVIDAD LIKE '%primera%' LIMIT 1";
    $stmt_actividad_30 = $pdo->prepare($sql_actividad_30);
    $stmt_actividad_30->execute();
    $actividad_30 = $stmt_actividad_30->fetch(PDO::FETCH_ASSOC);
    
    if ($actividad_30) {
        $sql_nota_30 = "SELECT CALIFICACION FROM CALIFICACION 
                        WHERE ID_PROYECTO = :ID_PROYECTO 
                        AND ID_ACTIVIDAD = :ID_ACTIVIDAD_30";
        $stmt_nota_30 = $pdo->prepare($sql_nota_30);
        $stmt_nota_30->execute([
            'ID_PROYECTO' => $id_proyecto,
            'ID_ACTIVIDAD_30' => $actividad_30['ID_ACTIVIDAD']
        ]);
        $nota_30_result = $stmt_nota_30->fetch(PDO::FETCH_ASSOC);
        return $nota_30_result ? floatval($nota_30_result['CALIFICACION']) : 0;
    }
    return 0;
}

// Procesar el envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ========== VERIFICAR SI EL JURADO SIGUE ACTIVO ANTES DE PROCESAR EL VOTO ==========
    $sql_estado_post = "SELECT ESTADO FROM USUARIO WHERE ID_USUARIO = ?";
    $stmt_estado_post = $pdo->prepare($sql_estado_post);
    $stmt_estado_post->execute([$id_usuario]);
    $estado_usuario_post = $stmt_estado_post->fetchColumn();

    if ($estado_usuario_post == 0) {
        $_SESSION['message'] = 'Jurado desactivado. No puedes calificar en este momento.';
        $_SESSION['alert-type'] = 'danger';
        header("Location: calificar.php?id=" . $id_actividad);
        exit();
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
        header('Location: calificar.php?id=' . $id_actividad . '&error=empty_fields');
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
        header('Location: calificar.php?id=' . $id_actividad . '&error=already_rated');
        exit;
    }

    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error: Token CSRF inválido.");
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

        if ($es_pitch_70_post) {
            // Lógica específica para pitch 70% - Ahora con 6 criterios (16.6% cada uno)
            foreach ($notas as $id_criterio => $nota) {
                // Para pitch 70%, cada criterio vale 16.6% (100% / 6 criterios)
                $porcentaje = 16.6;

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
            
            // Aplicar el 70% al pitch
            $promedio_actividad = $promedio_actividad * 0.70;

        } else {
            // Lógica normal para otras actividades
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
        }

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
            // ========== OBTENER NOTA DEL 30% ==========
            $nota_30 = obtenerNota30($pdo, $id_proyecto);

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

            // ========== CALIFICACIÓN FINAL (SOLO 30%) ==========
            // Usar solo la nota del 30%, no combinar con el 70%
            $calificacion_final = $nota_30;

            // ========== GUARDAR EN TABLA NOTAS ==========
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

            // ========== CÁLCULO DE PROMEDIO FINAL ENTRE USUARIOS ==========
            $sql_suma_calificaciones = "SELECT SUM(CALIFICACION) AS SUMA_CALIFICACIONES, COUNT(*) AS CANTIDAD_USUARIOS
                                FROM NOTAS  
                                WHERE ID_PROYECTO = :ID_PROYECTO";
            $stmt_suma = $pdo->prepare($sql_suma_calificaciones);
            $stmt_suma->execute(['ID_PROYECTO' => $id_proyecto]);

            $result_suma = $stmt_suma->fetch(PDO::FETCH_ASSOC);
            $suma_calificaciones = $result_suma['SUMA_CALIFICACIONES'] ? $result_suma['SUMA_CALIFICACIONES'] : 0;
            $cantidad_usuarios = $result_suma['CANTIDAD_USUARIOS'] ? $result_suma['CANTIDAD_USUARIOS'] : 1;

            // ========== PROMEDIO FINAL DEL PROYECTO ==========
            $promedio_final = $suma_calificaciones / $cantidad_usuarios;

            // ========== GUARDAR EN TABLA NFINAL ==========
            $sql_verificar_nfinal = "SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = :ID_PROYECTO";
            $stmt_verificar = $pdo->prepare($sql_verificar_nfinal);
            $stmt_verificar->execute(['ID_PROYECTO' => $id_proyecto]);

            $id_nfinal = $stmt_verificar->fetchColumn();

            if ($id_nfinal) {
                $sql_actualizar_nfinal = "UPDATE NFINAL SET NOTA_FINAL = :NOTA_FINAL WHERE ID_NFINAL = :ID_NFINAL";
                $stmt_actualizar = $pdo->prepare($sql_actualizar_nfinal);
                $stmt_actualizar->execute(['NOTA_FINAL' => $promedio_final, 'ID_NFINAL' => $id_nfinal]);
            } else {
                $sql_insertar_nfinal = "INSERT INTO NFINAL (ID_PROYECTO, ID_ACTIVIDAD, NOTA_FINAL) VALUES (:ID_PROYECTO, :ID_ACTIVIDAD, :NOTA_FINAL)";
                $stmt_insertar = $pdo->prepare($sql_insertar_nfinal);
                $stmt_insertar->execute([
                    'ID_PROYECTO' => $id_proyecto, 
                    'ID_ACTIVIDAD' => $id_actividad, 
                    'NOTA_FINAL' => $promedio_final
                ]);

                $id_nfinal = $pdo->lastInsertId();
            }

            // Insertar en la tabla intermedia notas_nfinal
            $sql_insertar_intermedia = "INSERT INTO NOTAS_NFINAL (ID_NOTA, ID_NFINAL) VALUES (:ID_NOTA, :ID_NFINAL)";
            $stmt_intermedia = $pdo->prepare($sql_insertar_intermedia);
            $stmt_intermedia->execute(['ID_NOTA' => $id_nota, 'ID_NFINAL' => $id_nfinal]);
        }

        // Redirigir al ID de actividad
        $pdo->commit();
        header('Location: calificar.php?id=' . $id_actividad . '&success=1');
    } catch (Exception $e) {
        // En caso de error, revertir la transacción
        $pdo->rollBack();
        die("Error al calificar: " . $e->getMessage());
    }
}

// Lógica para eliminar calificaciones
if (isset($_GET['delete']) && isset($_GET['id_proyecto']) && isset($_GET['id_actividad'])) {
    // ========== VERIFICAR SI EL JURADO ESTÁ ACTIVO ANTES DE ELIMINAR ==========
    $sql_estado_delete = "SELECT ESTADO FROM USUARIO WHERE ID_USUARIO = ?";
    $stmt_estado_delete = $pdo->prepare($sql_estado_delete);
    $stmt_estado_delete->execute([$id_usuario]);
    $estado_usuario_delete = $stmt_estado_delete->fetchColumn();

    if ($estado_usuario_delete == 0) {
        $_SESSION['message'] = 'Jurado desactivado. No puedes eliminar calificaciones.';
        $_SESSION['alert-type'] = 'danger';
        header("Location: calificar.php?id=" . $id_actividad);
        exit();
    }

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
            header('Location: calificar.php?id=' . $id_actividad . '&success=1');
            exit;
        } else {
            // Si no se encontró la calificación
            header('Location: calificar.php?id=' . $id_actividad . '&error=doesntexist');
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

// ========== CONSULTA PARA OBTENER TODOS LOS PROYECTOS (CALIFICADOS Y PENDIENTES) ==========
$query_todos_proyectos = "SELECT ID_PROYECTO, PROYECTO FROM PROYECTO ORDER BY ID_PROYECTO";
$stmt_todos_proyectos = $pdo->prepare($query_todos_proyectos);
$stmt_todos_proyectos->execute();
$todos_proyectos = $stmt_todos_proyectos->fetchAll(PDO::FETCH_ASSOC);

// Obtener proyectos calificados por este usuario para esta actividad
$proyectos_calificados = [];
if ($id_actividad) {
    $query_calificados = "
        SELECT DISTINCT p.ID_PROYECTO, p.PROYECTO
        FROM PROYECTO p
        JOIN CALIFICACION c ON p.ID_PROYECTO = c.ID_PROYECTO
        JOIN NOTA_CRITERIO_CALIFICACION ncc ON c.ID_CALIFICACION = ncc.ID_CALIFICACION
        JOIN NOTA_CRITERIO nc ON ncc.ID_NOTA_CRITERIO = nc.ID_NOTACRITERIO
        WHERE nc.ID_USUARIO = :id_usuario AND c.ID_ACTIVIDAD = :id_actividad";

    $stmt_calificados = $pdo->prepare($query_calificados);
    $stmt_calificados->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
    $stmt_calificados->bindParam(':id_actividad', $id_actividad, PDO::PARAM_INT);
    $stmt_calificados->execute();
    $proyectos_calificados = $stmt_calificados->fetchAll(PDO::FETCH_ASSOC);
}

// ========== NUEVA CONSULTA: OBTENER NOTAS DE LA TABLA CALIFICACION PARA LA PRIMERA FASE ==========
$notas_calificacion_primera_fase = [];
if ($es_primera_fase) {
    $query_notas_calificacion = "
        SELECT p.ID_PROYECTO, p.PROYECTO, c.CALIFICACION 
        FROM PROYECTO p 
        LEFT JOIN CALIFICACION c ON p.ID_PROYECTO = c.ID_PROYECTO AND c.ID_ACTIVIDAD = :id_actividad 
        ORDER BY p.ID_PROYECTO";
    
    $stmt_notas_calificacion = $pdo->prepare($query_notas_calificacion);
    $stmt_notas_calificacion->bindParam(':id_actividad', $id_actividad, PDO::PARAM_INT);
    $stmt_notas_calificacion->execute();
    $notas_calificacion_primera_fase = $stmt_notas_calificacion->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener detalles completos de las calificaciones para los proyectos calificados
$detalles_calificaciones = [];
if ($permiso_tabla && !empty($ids_calificados)) {
    $placeholders = str_repeat('?,', count($ids_calificados) - 1) . '?';
    $query_detalles = "
        SELECT 
            p.ID_PROYECTO,
            p.PROYECTO, 
            c.ID_CALIFICACION, 
            c.ID_ACTIVIDAD,
            nc.NOTA,
            nc.ID_CRITERIO,
            c.CALIFICACION AS CALIFICACION_FINAL,
            n.COMENTARIOS
        FROM PROYECTO p
        JOIN CALIFICACION c ON p.ID_PROYECTO = c.ID_PROYECTO
        JOIN NOTA_CRITERIO_CALIFICACION ncc ON c.ID_CALIFICACION = ncc.ID_CALIFICACION
        JOIN NOTA_CRITERIO nc ON ncc.ID_NOTA_CRITERIO = nc.ID_NOTACRITERIO
        LEFT JOIN NOTAS n ON c.ID_CALIFICACION = n.ID_CALIFICACION
        WHERE nc.ID_USUARIO = ? AND p.ID_PROYECTO IN ($placeholders)
        AND c.ID_ACTIVIDAD = ?  -- FILTRO CLAVE AÑADIDO
        ORDER BY p.ID_PROYECTO";

    $params = array_merge([$id_usuario], $ids_calificados, [$id_actividad]); // Agregar id_actividad
    $stmt_detalles = $pdo->prepare($query_detalles);
    $stmt_detalles->execute($params);
    $resultados_detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

    // Organizar los datos por proyecto
    foreach ($resultados_detalles as $row) {
        $proyecto_id = $row['ID_PROYECTO'];
        if (!isset($detalles_calificaciones[$proyecto_id])) {
            $detalles_calificaciones[$proyecto_id] = [
                'id_calificacion' => $row['ID_CALIFICACION'],
                'id_proyecto' => $row['ID_PROYECTO'],
                'proyecto_nombre' => $row['PROYECTO'],
                'id_actividad' => $row['ID_ACTIVIDAD'],
                'notas_criterios' => [],
                'calificacion_final' => $row['CALIFICACION_FINAL'],
                'comentarios' => $row['COMENTARIOS']
            ];
        }
        if ($row['ID_CRITERIO']) {
            $detalles_calificaciones[$proyecto_id]['notas_criterios'][$row['ID_CRITERIO']] = $row['NOTA'];
        }
    }
}

// Crear array de IDs de proyectos calificados para verificación rápida
$ids_calificados = array_column($proyectos_calificados, 'ID_PROYECTO');

// Obtener detalles completos de las calificaciones para los proyectos calificados
$detalles_calificaciones = [];
if ($permiso_tabla && !empty($ids_calificados)) {
    $placeholders = str_repeat('?,', count($ids_calificados) - 1) . '?';
    $query_detalles = "
        SELECT 
            p.ID_PROYECTO,
            p.PROYECTO, 
            c.ID_CALIFICACION, 
            c.ID_ACTIVIDAD,
            nc.NOTA,
            nc.ID_CRITERIO,
            c.CALIFICACION AS CALIFICACION_FINAL,
            n.COMENTARIOS
        FROM PROYECTO p
        JOIN CALIFICACION c ON p.ID_PROYECTO = c.ID_PROYECTO
        JOIN NOTA_CRITERIO_CALIFICACION ncc ON c.ID_CALIFICACION = ncc.ID_CALIFICACION
        JOIN NOTA_CRITERIO nc ON ncc.ID_NOTA_CRITERIO = nc.ID_NOTACRITERIO
        LEFT JOIN NOTAS n ON c.ID_CALIFICACION = n.ID_CALIFICACION
        WHERE nc.ID_USUARIO = ? AND p.ID_PROYECTO IN ($placeholders)
        ORDER BY p.ID_PROYECTO";

    $params = array_merge([$id_usuario], $ids_calificados);
    $stmt_detalles = $pdo->prepare($query_detalles);
    $stmt_detalles->execute($params);
    $resultados_detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

    // Organizar los datos por proyecto
    foreach ($resultados_detalles as $row) {
        $proyecto_id = $row['ID_PROYECTO'];
        if (!isset($detalles_calificaciones[$proyecto_id])) {
            $detalles_calificaciones[$proyecto_id] = [
                'id_calificacion' => $row['ID_CALIFICACION'],
                'id_proyecto' => $row['ID_PROYECTO'],
                'proyecto_nombre' => $row['PROYECTO'],
                'id_actividad' => $row['ID_ACTIVIDAD'],
                'notas_criterios' => [],
                'calificacion_final' => $row['CALIFICACION_FINAL'],
                'comentarios' => $row['COMENTARIOS']
            ];
        }
        if ($row['ID_CRITERIO']) {
            $detalles_calificaciones[$proyecto_id]['notas_criterios'][$row['ID_CRITERIO']] = $row['NOTA'];
        }
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
            <a href="panel_jurado.php" class="logo d-flex align-items-center">
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
                            <br>
                            
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="users-profile.php">
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
                <a class="nav-link collapsed" href="panel_jurado.php">
                    <i class="bi bi-grid"></i>
                    <span>Panel Jurado</span>
                </a>
            </li><!-- End Dashboard Nav -->

            <li class="nav-item">
                <a class="nav-link" data-bs-target="#evaluacion-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-check-circle"></i><span>Evaluación</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="evaluacion-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <?php
                    include 'bdd/database.php';
                    
                    $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($actividades) {
                        foreach ($actividades as $actividad) {
                            echo '<li>';
                            echo '<a href="calificar.php?id=' . $actividad["ID_ACTIVIDAD"] . '">';
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
                <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-menu-button-wide"></i><span>Resultados</span><i
                        class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="components-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                    <li>
                        <a href="resultadosindividuales.php">
                            <i class="bi bi-circle"></i><span>Resultados Individuales</span>
                        </a>
                    </li>
                    <li>
                        <a href="resultadosglobales.php">
                            <i class="bi bi-circle"></i><span>Resultados Globales</span>
                        </a>
                    </li>

                </ul>
            </li><!-- End Components Nav -->

            <li class="nav-item">
                <a class="nav-link collapsed" href="resultadosfondos.php">
                    <i class="bi bi-gem"></i>
                    <span>Fondos asignados</span>
                </a>

            </li><!-- End Icons Nav -->

            <li class="nav-item">
                <a class="nav-link collapsed" href="users-profile.php">
                    <i class="bi bi-person"></i>
                    <span>Perfil</span>
                </a>
            </li><!-- End Profile Page Nav -->

            <li class="nav-item">
                <a class="nav-link collapsed" href="manual_jurado.php">
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
                    <li class="breadcrumb-item"><a href="panel_jurado.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Calificaciones</li>
                </ol>
            </nav>
        </div><!-- End Page Title -->

        <!-- ========== ALERTA DE ESTADO DEL JURADO ========== -->
        <?php if ($estado_usuario == 0): ?>
            <div id="alertaEstado" class="alert alert-warning alert-auto-dismiss" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Jurado Inactivo:</strong> No puedes calificar en estos momentos.
            </div>
        <?php else: ?>
            <div id="alertaEstado" class="alert alert-success alert-auto-dismiss" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <strong>Jurado Activo:</strong> Puedes calificar proyectos normalmente.
            </div>
        <?php endif; ?>

        <!-- Inicio de la rúbrica del criterio -->
        <section class="section dashboard">
            <div class="row">
                <div class="col-12">
                    <div class="card recent-sales overflow-auto">
                        <div class="card-body">
                            <h5 class="card-title">Rúbrica de criterio <span></span></h5>

                            <div class="rubrica-cards">
                                <?php if ($es_primera_fase): ?>
                                    <?php /* Sin rúbrica en la primera evaluación (30%) */ ?>
                                    <?php elseif ($es_pitch_70): ?>
                                    <?php 
                                         $rubrica_pitch = [
                                            'Impacto Económico',
                                            'Impacto Social',
                                            'Impacto Ambiental',
                                            'Sostenibilidad Financiera',
                                            'Crecimiento Potencial',
                                            'Innovación'
                                        ];
                                    ?>
                                    <?php foreach ($rubrica_pitch as $nombre): ?>
                                        <div class="col-md-4 d-flex">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($nombre); ?></h5>
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
                                                        <h5 class="card-title">
                                                            <?php echo htmlspecialchars($criterio['CRITERIO']); ?>
                                                        </h5>
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

        <!-- ========== SECCIÓN DE EVALUACIÓN DE PROYECTOS (SOLO PARA JURADOS ACTIVOS) ========== -->
        <?php if ($estado_usuario == 1): ?>
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
                                        setTimeout(function () {
                                            var errorMessage = document.getElementById('error-message');
                                            if (errorMessage) {
                                                errorMessage.style.display = 'none';
                                            }
                                        }, 3000);
                                    </script>

                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="idUsuario" value="<?php echo $id_usuario; ?>">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-4">
                                                <label for="projectFilter" class="form-label">Seleccione el proyecto a
                                                    evaluar:</label>
                                                <select id="projectFilter" name="idProyecto" class="form-select"
                                                    onchange="loadCriterios()">
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
                                                                <th scope="col">Sostenibilidad Financiera</th>
                                                                <th scope="col">Crecimiento Potencial</th>
                                                                <th scope="col">Innovación</th>
                                                            </tr>
                                                        <?php else: ?>
                                                            <tr id="criteriosHeader">
                                                                <th scope="col">Proyecto</th>
                                                                <?php foreach ($criterios_mostrar as $criterio): ?>
                                                                    <th scope="col"><?php echo htmlspecialchars($criterio['CRITERIO']); ?></th>
                                                                <?php endforeach; ?>
                                                                <?php if (!$es_primera_fase): ?><?php endif; ?>
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
                                                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Sostenibilidad Financiera')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Crecimiento Potencial')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
                                                                <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Innovación')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01"></td>
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
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="section dashboard">
            <div class="row">
                <div class="col-12">
                    <div class="card recent-sales overflow-auto">
                        <div class="card-body">
                            <h5 class="card-title">Proyectos Calificados/Pendientes</h5>
                            <!-- ========== EXPLICACIÓN CÁLCULO NOTA FINAL PITCH ========== -->
                                <div class="alert alert-info" role="alert">
                                    <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Cálculo de la Nota Final - Pitch</h4>
                                    <hr>
                                    <p class="mb-0">
                                        <strong>Fórmula:</strong> (Suma de las 6 notas individuales / cantidad de criterios) × 70%<br>
                                        <strong>Detalle:</strong> Cada criterio se califica de 0 a 10 puntos. El promedio simple de los 6 criterios 
                                        se multiplica por 0.70 para obtener la calificación final del pitch, que representa el 70% de la evaluación total.
                                    </p>
                                </div>
                            <?php if ($permiso_tabla): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center">
                                        <thead>
                                            <?php if ($es_pitch_70): ?>
                                                <tr>
                                                    <th scope="col">Proyecto</th>
                                                    <th scope="col">Financiero</th>
                                                    <th scope="col">Social</th>
                                                    <th scope="col">Ambiental</th>
                                                    <th scope="col">Sostenibilidad Financiera</th>
                                                    <th scope="col">Crecimiento Potencial</th>
                                                    <th scope="col">Innovación</th>
                                                    <th scope="col">Nota Final (70%)</th>
                                                    <?php if ($estado_usuario == 1): ?>
                                                        <th scope="col">Acciones</th>
                                                    <?php endif; ?>
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
                                                    <?php if ($estado_usuario == 1): ?>
                                                        <th scope="col">Acciones</th>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endif; ?>
                                        </thead>
                                        <tbody>
                                            <?php if ($es_primera_fase): ?>
                                                <!-- ========== TABLA ESPECIAL PARA PRIMERA FASE (30%) ========== -->
                                                <?php foreach ($notas_calificacion_primera_fase as $nota_proyecto): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($nota_proyecto['PROYECTO']); ?></td>
                                                        <td>
                                                            <?php 
                                                            // Mostrar la calificación de la tabla CALIFICACION
                                                            echo $nota_proyecto['CALIFICACION'] !== null ? 
                                                                number_format(floatval($nota_proyecto['CALIFICACION']), 2) : '0.00';
                                                            ?>
                                                        </td>
                                                        <?php 
                                                        // Para las columnas restantes de criterios (si hay más de uno)
                                                        for ($i = 1; $i < count($criterios_mostrar); $i++): 
                                                        ?>
                                                            <td>0.00</td>
                                                        <?php endfor; ?>
                                                        <?php if (!$es_primera_fase): ?>
                                                            <td>Sin comentario</td>
                                                        <?php endif; ?>
                                                        <?php if ($estado_usuario == 1): ?>
                                                            <td>
                                                                <?php if ($nota_proyecto['CALIFICACION'] !== null): ?>
                                                                    <a href="calificar.php?delete=<?php echo urlencode($detalles_calificaciones[$nota_proyecto['ID_PROYECTO']]['id_calificacion'] ?? ''); ?>&id_proyecto=<?php echo urlencode($nota_proyecto['ID_PROYECTO']); ?>&id_actividad=<?php echo urlencode($id_actividad); ?>"
                                                                    class="btn btn-danger btn-sm" title="Eliminar evaluación"
                                                                    onclick="return confirm('¿Seguro que deseas eliminar esta evaluación?');">
                                                                        <i class="fas fa-trash"></i>
                                                                    </a>
                                                                <?php else: ?>
                                                                    -
                                                                <?php endif; ?>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <!-- ========== TABLA NORMAL PARA OTRAS ACTIVIDADES ========== -->
                                                <?php foreach ($todos_proyectos as $proyecto): ?>
                                                    <?php 
                                                    $esta_calificado = in_array($proyecto['ID_PROYECTO'], $ids_calificados);
                                                    $detalles = isset($detalles_calificaciones[$proyecto['ID_PROYECTO']]) ? $detalles_calificaciones[$proyecto['ID_PROYECTO']] : null;
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($proyecto['PROYECTO']); ?></td>
                                                        
                                                        <?php if ($es_pitch_70): ?>
                                                            <?php if ($esta_calificado && $detalles): ?>
                                                                <?php
                                                                    $nombres70 = [
                                                                        'Impacto Económico',
                                                                        'Impacto Social',
                                                                        'Impacto Ambiental',
                                                                        'Sostenibilidad Financiera',
                                                                        'Crecimiento Potencial',
                                                                        'Innovación'
                                                                    ];
                                                                    $vals = [];
                                                                ?>
                                                                <?php foreach ($nombres70 as $nn): ?>
                                                                    <?php 
                                                                        $idc = $mapa_ids[mb_strtolower($nn)] ?? null;
                                                                        $nota = ($idc && isset($detalles['notas_criterios'][$idc])) ? $detalles['notas_criterios'][$idc] : '0.00';
                                                                        if ($nota !== '0.00') { 
                                                                            $vals[] = floatval($nota); 
                                                                        }
                                                                    ?>
                                                                    <td><?php echo htmlspecialchars($nota); ?></td>
                                                                <?php endforeach; ?>
                                                                
                                                                <!-- Columna de Nota Final (70% del promedio) -->
                                                                <td>
                                                                    <?php
                                                                    if (count($vals) === 6) {
                                                                        // Calcular el promedio de los 6 criterios y aplicar 70%
                                                                        $promedio_simple = array_sum($vals) / count($vals);
                                                                        $nota_final_70 = $promedio_simple * 0.70;
                                                                        echo number_format($nota_final_70, 2);
                                                                    } else {
                                                                        echo '0.00';
                                                                    }
                                                                    ?>
                                                                </td>
                                                                
                                                                <?php if ($estado_usuario == 1): ?>
                                                                    <td>
                                                                        <a href="calificar.php?delete=<?php echo urlencode($detalles['id_calificacion']); ?>&id_proyecto=<?php echo urlencode($detalles['id_proyecto']); ?>&id_actividad=<?php echo urlencode($detalles['id_actividad']); ?>"
                                                                        class="btn btn-danger btn-sm" title="Eliminar evaluación"
                                                                        onclick="return confirm('¿Seguro que deseas eliminar esta evaluación?');">
                                                                            <i class="fas fa-trash"></i>
                                                                        </a>
                                                                    </td>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <!-- Proyecto pendiente -->
                                                                <?php for ($i = 0; $i < 6; $i++): ?>
                                                                    <td>0.00</td>
                                                                <?php endfor; ?>
                                                                <td>0.00</td>
                                                                <?php if ($estado_usuario == 1): ?>
                                                                    <td>-</td>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <!-- Actividades normales -->
                                                            <?php if ($esta_calificado && $detalles): ?>
                                                                <!-- Mostrar las notas de los criterios para proyectos calificados -->
                                                                <?php foreach ($criterios_mostrar as $criterio): ?>
                                                                    <?php
                                                                    $nota = isset($detalles['notas_criterios'][$criterio['ID_CRITERIO']]) ? $detalles['notas_criterios'][$criterio['ID_CRITERIO']] : '0.00';
                                                                    ?>
                                                                    <td><?php echo htmlspecialchars($nota); ?></td>
                                                                <?php endforeach; ?>

                                                                <!-- Mostrar los comentarios -->
                                                                <?php if (!$es_primera_fase): ?>
                                                                    <td><?php echo htmlspecialchars($detalles['comentarios'] ?? 'Sin comentario'); ?></td>
                                                                <?php endif; ?>

                                                                <!-- Botones de acción -->
                                                                <?php if ($estado_usuario == 1): ?>
                                                                    <td>
                                                                        <a href="calificar.php?delete=<?php echo htmlspecialchars($detalles['id_calificacion']); ?>&id_proyecto=<?php echo htmlspecialchars($detalles['id_proyecto']); ?>&id_actividad=<?php echo htmlspecialchars($detalles['id_actividad']); ?>"
                                                                            class="btn btn-danger btn-sm"
                                                                            onclick="return confirm('¿Estás seguro de eliminar esta evaluación?')">
                                                                            <i class='fas fa-trash-alt'></i>
                                                                        </a>
                                                                    </td>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <!-- Proyecto pendiente - mostrar 0.00 -->
                                                                <?php foreach ($criterios_mostrar as $criterio): ?>
                                                                    <td>0.00</td>
                                                                <?php endforeach; ?>
                                                                <?php if (!$es_primera_fase): ?>
                                                                    <td>Sin comentario</td>
                                                                <?php endif; ?>
                                                                <?php if ($estado_usuario == 1): ?>
                                                                    <td>-</td>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center">
                                        <thead>
                                            <tr id="criteriosHeader">
                                                <th scope="col">Proyecto</th>
                                                <?php foreach ($criterios_mostrar as $criterio): ?>
                                                    <th scope="col"><?php echo htmlspecialchars($criterio['CRITERIO']); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody id="statusTable">
                                            <?php if ($es_primera_fase): ?>
                                                <!-- ========== TABLA ESPECIAL PARA PRIMERA FASE (30%) ========== -->
                                                <?php foreach ($notas_calificacion_primera_fase as $nota_proyecto): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($nota_proyecto['PROYECTO']); ?></td>
                                                        <td>
                                                            <?php 
                                                            // Mostrar la calificación de la tabla CALIFICACION
                                                            echo $nota_proyecto['CALIFICACION'] !== null ? 
                                                                number_format(floatval($nota_proyecto['CALIFICACION']), 2) : '0.00';
                                                            ?>
                                                        </td>
                                                        <?php 
                                                        // Para las columnas restantes de criterios (si hay más de uno)
                                                        for ($i = 1; $i < count($criterios_mostrar); $i++): 
                                                        ?>
                                                            <td>0.00</td>
                                                        <?php endfor; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <!-- ========== TABLA NORMAL PARA OTRAS ACTIVIDADES ========== -->
                                                <?php foreach ($todos_proyectos as $proyecto): ?>
                                                    <?php 
                                                    $esta_calificado = in_array($proyecto['ID_PROYECTO'], $ids_calificados);
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($proyecto['PROYECTO']); ?></td>
                                                        
                                                        <!-- Mostrar las notas de los criterios o 0.00 -->
                                                        <?php if ($esta_calificado && isset($detalles_calificaciones[$proyecto['ID_PROYECTO']])): ?>
                                                            <?php $detalles = $detalles_calificaciones[$proyecto['ID_PROYECTO']]; ?>
                                                            <?php foreach ($criterios_mostrar as $criterio): ?>
                                                                <?php
                                                                $nota = isset($detalles['notas_criterios'][$criterio['ID_CRITERIO']]) ? $detalles['notas_criterios'][$criterio['ID_CRITERIO']] : '0.00';
                                                                ?>
                                                                <td><?php echo htmlspecialchars($nota); ?></td>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <?php foreach ($criterios_mostrar as $criterio): ?>
                                                                <td>0.00</td>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
            criteriosBody.innerHTML = '';

            if (!nombreProyecto) {
                return;
            }

            const esPitch70 = <?php echo $es_pitch_70 ? 'true' : 'false'; ?>;
            
            if (esPitch70) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${nombreProyecto}</td>
                    <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Económico')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
                    <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Social')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
                    <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Impacto Ambiental')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
                    <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Sostenibilidad Financiera')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
                    <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Crecimiento Potencial')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
                    <td><input type="number" name="notas[<?php echo $mapa_ids[mb_strtolower('Innovación')] ?? 0; ?>]" class="form-control" min="0" max="10" step="0.01" required></td>
                `;
                criteriosBody.appendChild(row);
            } else {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${nombreProyecto}</td>
                    <?php foreach ($criterios_mostrar as $criterio): ?>
                        <td><input type="number" name="notas[<?php echo $criterio['ID_CRITERIO']; ?>]" placeholder="Nota" class="form-control" min="0" max="10" step="0.01" required></td>
                    <?php endforeach; ?>
                    <?php if (!$es_primera_fase): ?>
                        <td><input type="text" name="comentario" placeholder="Comentario" class="form-control"></td>
                    <?php endif; ?>
                `;
                criteriosBody.appendChild(row);
            }
        }

        // Ocultar automáticamente la alerta después de 3 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alerta = document.getElementById('alertaEstado');
            if (alerta) {
                setTimeout(function() {
                    alerta.style.display = 'none';
                }, 3000);
            }
        });
    </script>
    <script>
        document.querySelector('.toggle-sidebar-btn').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main').classList.toggle('active');
        });
    </script>

</body>

</html>