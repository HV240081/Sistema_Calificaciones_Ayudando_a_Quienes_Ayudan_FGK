<?php
// Conexión a la base de datos y otras configuraciones
$dsn = "mysql:host=localhost;dbname=PROYECTO_ES";
$username = "userproyect";
$password = "FGK202412345";

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
    exit();
}

// Iniciar sesión
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener el rol del usuario actual
$sql = "SELECT U.NOMBRE, U.APELLIDO, R.ROL 
        FROM USUARIO U 
        JOIN ROL R ON U.ID_ROL = R.ID_ROL 
        WHERE ID_USUARIO = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Nombre y rol del usuario actual
$nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
$rol = $row['ROL'];

// === PROCESAMIENTO DE FORMULARIOS ===
$success = false;
$deleted = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_fondos'])) {
    foreach ($_POST['fondos'] as $id_nfinal => $premios) {
        $stmt_delete = $pdo->prepare("DELETE FROM PREMIO_NFINAL WHERE ID_NFINAL = :id_nfinal");
        $stmt_delete->bindParam(':id_nfinal', $id_nfinal, PDO::PARAM_INT);
        $stmt_delete->execute();

        foreach ($premios as $id_premio) {
            $id_premio = intval($id_premio);
            if ($id_premio > 0) {
                $stmt_insert = $pdo->prepare("INSERT INTO PREMIO_NFINAL (ID_PREMIO, ID_NFINAL) VALUES (:id_premio, :id_nfinal)");
                $stmt_insert->bindParam(':id_premio', $id_premio, PDO::PARAM_INT);
                $stmt_insert->bindParam(':id_nfinal', $id_nfinal, PDO::PARAM_INT);
                $stmt_insert->execute();
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_fondos'])) {
    $pdo->exec("DELETE FROM PREMIO_NFINAL");
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit();
}

if (isset($_GET['success']) && $_GET['success'] == 1) $success = true;
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) $deleted = true;

// === CONSULTAS DE DATOS - APLICANDO LA LÓGICA DEL PRIMER CÓDIGO ===

// Obtener actividades
$query_actividades = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD ORDER BY ID_ACTIVIDAD";
$stmt_actividades = $pdo->prepare($query_actividades);
$stmt_actividades->execute();
$actividades = $stmt_actividades->fetchAll(PDO::FETCH_ASSOC);

// Obtener promedios por proyecto y actividad
$query_promedio = "
SELECT 
    c.ID_PROYECTO,
    c.ID_ACTIVIDAD,
    AVG(c.CALIFICACION) AS promedio_nota
FROM 
    CALIFICACION c
GROUP BY 
    c.ID_PROYECTO, c.ID_ACTIVIDAD
";
$stmt = $pdo->prepare($query_promedio);
$stmt->execute();
$notas_promedio = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crear una matriz de promedios de notas
$proyectos_promedios = [];
foreach ($notas_promedio as $nota) {
    $proyectos_promedios[$nota['ID_PROYECTO']][$nota['ID_ACTIVIDAD']] = $nota['promedio_nota'];
}

// Identificar actividad 30% y actividad 70% (pitch)
$id_actividad_30 = null;
$id_actividad_70 = null;
foreach ($actividades as $actividad) {
    if ((int)$actividad['PORCENTAJE'] === 30) {
        $id_actividad_30 = $actividad['ID_ACTIVIDAD'];
    }
    if ((int)$actividad['PORCENTAJE'] === 70 && stripos($actividad['NOM_ACTIVIDAD'], 'pitch') !== false) {
        $id_actividad_70 = $actividad['ID_ACTIVIDAD'];
    }
}
// Si no se detectó por nombre, usar la actividad 70% única
if ($id_actividad_70 === null) {
    foreach ($actividades as $actividad) {
        if ((int)$actividad['PORCENTAJE'] === 70) {
            $id_actividad_70 = $actividad['ID_ACTIVIDAD'];
            break;
        }
    }
}

// OBTENER EL NÚMERO DE JURADOS ACTIVOS
$query_jurados_activos = "
SELECT COUNT(*) as total_jurados_activos 
FROM USUARIO 
WHERE ID_ROL IN (2, 3) AND ESTADO = 1";
$stmt_jurados_activos = $pdo->prepare($query_jurados_activos);
$stmt_jurados_activos->execute();
$result_jurados_activos = $stmt_jurados_activos->fetch(PDO::FETCH_ASSOC);
$numero_jurados_activos = $result_jurados_activos['total_jurados_activos'];

// CONSULTA CORREGIDA: Obtener las notas individuales de los JURADOS ACTIVOS para el pitch
$query_notas_pitch_jurados = "
SELECT 
    p.ID_PROYECTO,
    p.PROYECTO,
    c.CALIFICACION as nota_jurado,
    u.ID_USUARIO,
    CONCAT(u.NOMBRE, ' ', u.APELLIDO) as nombre_jurado
FROM CALIFICACION c
JOIN PROYECTO p ON p.ID_PROYECTO = c.ID_PROYECTO
JOIN NOTA_CRITERIO_CALIFICACION ncc ON c.ID_CALIFICACION = ncc.ID_CALIFICACION
JOIN NOTA_CRITERIO nc ON ncc.ID_NOTA_CRITERIO = nc.ID_NOTACRITERIO
JOIN USUARIO u ON u.ID_USUARIO = nc.ID_USUARIO
WHERE c.ID_ACTIVIDAD = :id_actividad_pitch
AND u.ID_ROL IN (2, 3)  -- Solo jurados (ID_ROL 2 y 3)
AND u.ESTADO = 1  -- SOLO JURADOS ACTIVOS
GROUP BY p.ID_PROYECTO, u.ID_USUARIO, c.CALIFICACION
ORDER BY p.ID_PROYECTO, u.ID_USUARIO
";

$stmt_pitch_jurados = $pdo->prepare($query_notas_pitch_jurados);
$stmt_pitch_jurados->execute(['id_actividad_pitch' => $id_actividad_70]);
$notas_pitch_jurados = $stmt_pitch_jurados->fetchAll(PDO::FETCH_ASSOC);

// Organizar notas de jurados por proyecto
$notas_jurados_por_proyecto = [];
foreach ($notas_pitch_jurados as $nota) {
    $id_proyecto = $nota['ID_PROYECTO'];
    if (!isset($notas_jurados_por_proyecto[$id_proyecto])) {
        $notas_jurados_por_proyecto[$id_proyecto] = [];
    }
    $notas_jurados_por_proyecto[$id_proyecto][] = $nota['nota_jurado'];
}

// Obtener lista única de proyectos
$query_proyectos = "SELECT DISTINCT ID_PROYECTO, PROYECTO FROM PROYECTO ORDER BY ID_PROYECTO";
$stmt_proyectos = $pdo->prepare($query_proyectos);
$stmt_proyectos->execute();
$todos_proyectos = $stmt_proyectos->fetchAll(PDO::FETCH_ASSOC);

// Preparar arrays con valores calculados
$notas_finales_calculadas = [];

foreach ($todos_proyectos as $proyecto) {
    $id_proyecto = $proyecto['ID_PROYECTO'];

    // CALCULAR PROMEDIO DEL PITCH CON JURADOS ACTIVOS
    $valor_pitch = 0;
    
    if ($id_actividad_70 !== null && isset($notas_jurados_por_proyecto[$id_proyecto])) {
        // 1. Sumar todas las notas de los jurados ACTIVOS para este proyecto
        $suma_notas_jurados = array_sum($notas_jurados_por_proyecto[$id_proyecto]);
        
        // 2. Contar cuántos jurados ACTIVOS calificaron este proyecto
        $numero_jurados_que_calificaron = count($notas_jurados_por_proyecto[$id_proyecto]);
        
        // 3. Calcular promedio dividiendo entre el número de jurados que calificaron
        if ($numero_jurados_que_calificaron > 0) {
            $promedio_jurados = $suma_notas_jurados / $numero_jurados_que_calificaron;
        } else {
            $promedio_jurados = 0;
        }
        
        // 4. Multiplicar por 70% para obtener el valor del pitch
        $valor_pitch = $promedio_jurados * 0.70;
    }

    // Calcular valor de la primera evaluación (30%)
    $valor_30 = 0;
    if ($id_actividad_30 !== null && isset($proyectos_promedios[$id_proyecto][$id_actividad_30])) {
        $valor_30 = $proyectos_promedios[$id_proyecto][$id_actividad_30] * 0.30;
    }

    // Nota final = (promedio pitch * 0.70) + (promedio primera evaluacion * 0.30)
    $nota_final = $valor_pitch + $valor_30;
    $notas_finales_calculadas[$id_proyecto] = $nota_final;
}

// Obtener ID_NFINAL para cada proyecto y preparar resultados
$resultados = [];
foreach ($todos_proyectos as $proyecto) {
    $id_proyecto = $proyecto['ID_PROYECTO'];
    
    // Obtener ID_NFINAL para este proyecto
    $stmt_nfinal = $pdo->prepare("SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = ?");
    $stmt_nfinal->execute([$id_proyecto]);
    $nfinal_row = $stmt_nfinal->fetch(PDO::FETCH_ASSOC);
    
    $resultados[] = [
        'ID_PROYECTO' => $proyecto['ID_PROYECTO'],
        'PROYECTO' => $proyecto['PROYECTO'],
        'NOTA_FINAL' => isset($notas_finales_calculadas[$id_proyecto]) ? number_format($notas_finales_calculadas[$id_proyecto], 2) : 'N/A',
        'ID_NFINAL' => $nfinal_row ? $nfinal_row['ID_NFINAL'] : null
    ];
}

// Consultar fondos disponibles
$stmt_fondos = $pdo->query("SELECT ID_PREMIO, PREMIO FROM PREMIO");
$fondos = $stmt_fondos->fetchAll(PDO::FETCH_ASSOC);

// Consultar asignaciones previas
$stmt_asignaciones = $pdo->query("SELECT ID_NFINAL, ID_PREMIO FROM PREMIO_NFINAL");
$asignaciones_previas = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);

$asignaciones_map = [];
foreach ($asignaciones_previas as $asignacion) {
    $asignaciones_map[$asignacion['ID_NFINAL']][] = $asignacion['ID_PREMIO'];
}

// Calcular cantidad máxima de fondos asignados por proyecto (para definir columnas)
$maxFondos = 1;
foreach ($asignaciones_map as $fondosAsignados) {
    if (count($fondosAsignados) > $maxFondos) {
        $maxFondos = count($fondosAsignados);
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
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        .fondo-row { display:flex; align-items:center; margin-bottom:5px; }
        .fondo-row select { flex:1; min-width: 120px; }
        .fondo-row button { margin-left:5px; }
        .add-fondo-btn { margin-top:5px; }

        /* Estilos para tabla compacta */
        .table-compact th, 
        .table-compact td {
            padding: 0.4rem 0.3rem;
            font-size: 0.85rem;
        }

        .table-compact .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
        }

        .table-compact .form-select {
            padding: 0.2rem 0.4rem;
            font-size: 0.8rem;
        }

        /* Scroll horizontal cuando hay muchas columnas */
        .table-responsive {
            overflow-x: auto;
            max-width: 100%;
        }

        /* Sin scroll por defecto */
        .table-responsive.no-scroll {
            overflow-x: visible;
        }

        /* Con scroll cuando hay muchas columnas */
        .table-responsive.with-scroll {
            overflow-x: auto;
        }

        /* Columnas más compactas */
        .col-proyecto { min-width: 200px; max-width: 250px; }
        .col-nota { min-width: 100px; max-width: 120px; }
        .col-beneficio { min-width: 150px; max-width: 180px; }
        .col-accion { min-width: 140px; max-width: 160px; }

        /* Asegurar que la tabla no se desborde */
        #tablaFondos {
            width: auto !important;
            min-width: 100%;
            table-layout: fixed;
        }

        /* Responsive para pantallas pequeñas */
        @media (max-width: 768px) {
            .table-compact th, 
            .table-compact td {
                padding: 0.3rem 0.2rem;
                font-size: 0.8rem;
            }
            
            .col-proyecto { min-width: 150px; max-width: 200px; }
            .col-beneficio { min-width: 130px; max-width: 160px; }
            .col-accion { min-width: 120px; max-width: 140px; }
            
            .fondo-row {
                flex-direction: column;
                gap: 2px;
            }
            
            .fondo-row select {
                min-width: 100px;
            }
        }

        /* Estilo para el botón Guardar sin texto extra */
        .btn-guardar {
            position: relative;
            overflow: hidden;
        }

        .btn-guardar::after {
            content: "";
            display: none;
        }
    </style>
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
                        <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $nombre_usuario ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                        <li class="dropdown-header">
                            <h6><?php echo $nombre_usuario ?></h6>
                            <span><?php echo $rol ?></span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center" href="user-profilep.php">
                                <i class="bi bi-person"></i>
                                <span>Perfil</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
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
    </header><!-- End Header -->

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
                    include 'bdd/database.php';
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
                <ul id="components-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
                    <li>
                        <a href="resultadosindividualesp.php">
                            <i class="bi bi-circle"></i><span>Resultados Individuales</span>
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
                <a class="nav-link" href="fondos.php">
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
                <a class="nav-link collapsed" href="users-profile.php">
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
    </aside><!-- End Sidebar-->

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Resultados Finales y Fondos</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="panel_juradop.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Resultados Finales y Fondos</li>
                </ol>
            </nav>
        </div><!-- End Page Title -->

        <section class="section dashboard">
            <div class="row">
                <div class="col-lg-12">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Asignación de fondos<span> | Resultados Finales</span></h5>
                                    
                                    <?php if ($success): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            ¡Beneficios asignados con éxito!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($deleted): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            ¡Beneficios eliminados con éxito!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    <?php endif; ?>

                                    <form method="post">
                                        <div class="table-responsive <?php echo $maxFondos >= 4 ? 'with-scroll' : 'no-scroll'; ?>" id="tableContainer">
                                            <table id="tablaFondos" class="table table-bordered table-compact text-center align-middle">
                                                <thead>
                                                    <tr id="encabezado">
                                                        <th class="col-proyecto">Proyecto</th>
                                                        <th class="col-nota">Nota Final
                                                            <button class="btn btn-primary btn-sm" data-order="asc" type="button" onclick="ordenarNotas()" style="margin-left:5px;">
                                                                <i class="fas fa-sort-amount-down"></i>
                                                            </button>
                                                        </th>
                                                        <?php for ($i = 1; $i <= $maxFondos; $i++): ?>
                                                            <th class="col-beneficio">Beneficio <?php echo $i; ?></th>
                                                        <?php endfor; ?>
                                                        <th class="col-accion">Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($resultados as $resultado): ?>
                                                        <?php 
                                                            $fondosAsignados = $asignaciones_map[$resultado['ID_NFINAL']] ?? [];
                                                            $numActual = count($fondosAsignados);
                                                        ?>
                                                        <tr data-nfinal="<?php echo $resultado['ID_NFINAL']; ?>">
                                                            <td class="col-proyecto"><?php echo htmlspecialchars($resultado['PROYECTO']); ?></td>
                                                            <td class="col-nota final-grade"><?php echo htmlspecialchars($resultado['NOTA_FINAL']); ?></td>

                                                            <?php for ($i = 0; $i < $maxFondos; $i++): ?>
                                                                <td class="col-beneficio">
                                                                    <div class="fondo-row d-flex justify-content-center align-items-center gap-1">
                                                                        <select name="fondos[<?php echo $resultado['ID_NFINAL']; ?>][]" class="form-select form-select-sm">
                                                                            <option value="">Seleccionar</option>
                                                                            <?php foreach ($fondos as $fondo): ?>
                                                                                <option value="<?php echo $fondo['ID_PREMIO']; ?>" 
                                                                                    <?php echo ($i < $numActual && $fondosAsignados[$i] == $fondo['ID_PREMIO']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($fondo['PREMIO']); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <button type="button" class="btn btn-danger btn-sm remove-fondo">
                                                                            <i class="fas fa-trash"></i>
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            <?php endfor; ?>

                                                            <td class="col-accion">
                                                                <button type="button" class="btn btn-success btn-sm add-column-btn">
                                                                    <i class="fas fa-plus"></i> Agregar fondo
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="text-end mt-3">
                                            <button type="submit" name="asignar_fondos" class="btn btn-primary btn-guardar">Guardar</button>
                                            <button type="submit" name="eliminar_fondos" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar todos los fondos?')">Eliminar todos</button>
                                            <a href="fondos.php" class="btn btn-secondary">Cancelar</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
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
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const tabla = document.getElementById("tablaFondos");
        const encabezado = document.getElementById("encabezado");
        const tableContainer = document.getElementById("tableContainer");
        let currentColumns = <?php echo $maxFondos; ?>;

        // === FUNCIÓN PARA EVITAR FONDOS DUPLICADOS POR PROYECTO ===
        function actualizarOpciones(fila) {
            const selects = fila.querySelectorAll('select');
            const seleccionados = Array.from(selects)
                .map(s => s.value)
                .filter(v => v !== "");

            selects.forEach(select => {
                select.querySelectorAll('option').forEach(option => {
                    if (option.value !== "" && seleccionados.includes(option.value) && option.value !== select.value) {
                        option.disabled = true;
                    } else {
                        option.disabled = false;
                    }
                });
            });
        }

        // === AGREGAR NUEVA COLUMNA DE FONDOS A TODOS LOS PROYECTOS ===
        document.addEventListener("click", function(e) {
            if (e.target.closest(".add-column-btn")) {
                agregarNuevaColumna();
            }

            // Eliminar fondo (botón )
            if (e.target.closest(".remove-fondo")) {
                const fila = e.target.closest("tr");
                e.target.closest(".fondo-row").querySelector("select").value = "";
                actualizarOpciones(fila);
            }
        });

        // === FUNCIÓN QUE AGREGA UNA NUEVA COLUMNA CON SELECTS A CADA FILA ===
        function agregarNuevaColumna() {
            currentColumns++;
            const th = document.createElement("th");
            th.textContent = "Beneficio " + currentColumns;
            th.className = "col-beneficio";
            encabezado.insertBefore(th, encabezado.lastElementChild);

            const filas = tabla.querySelectorAll("tbody tr");
            filas.forEach(fila => {
                const idNFinal = fila.dataset.nfinal;
                const nuevaCelda = document.createElement("td");
                nuevaCelda.className = "col-beneficio";
                nuevaCelda.innerHTML = `
                    <div class="fondo-row d-flex justify-content-center align-items-center gap-1">
                        <select name="fondos[${idNFinal}][]" class="form-select form-select-sm">
                            <option value="">Seleccionar</option>
                            <?php foreach ($fondos as $fondo): ?>
                                <option value="<?php echo $fondo['ID_PREMIO']; ?>"><?php echo htmlspecialchars($fondo['PREMIO']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-danger btn-sm remove-fondo"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                fila.insertBefore(nuevaCelda, fila.querySelector(".col-accion"));

                // Ligar evento change para evitar duplicados dentro del proyecto
                const select = nuevaCelda.querySelector('select');
                select.addEventListener('change', () => actualizarOpciones(fila));

                actualizarOpciones(fila); // Inicializa la validación
            });

            // Actualizar estado del scroll
            updateTableState();
        }

        // === ACTUALIZAR ESTADO DE LA TABLA ===
        function updateTableState() {
            // Activar scroll cuando hay 4 o más columnas para mejor visualización
            if (currentColumns >= 4) {
                tableContainer.classList.remove('no-scroll');
                tableContainer.classList.add('with-scroll');
            } else {
                tableContainer.classList.remove('with-scroll');
                tableContainer.classList.add('no-scroll');
            }
        }

        // === ACTUALIZAR OPCIONES AL CAMBIAR SELECT EXISTENTE ===
        document.addEventListener('change', function(e){
            if(e.target.tagName === 'SELECT'){
                const fila = e.target.closest('tr');
                actualizarOpciones(fila);
            }
        });

        // Inicializar validaciones al cargar
        tabla.querySelectorAll('tbody tr').forEach(fila => actualizarOpciones(fila));
        updateTableState();
    });

    // Función para ordenar notas
    function ordenarNotas() {
        var button = document.querySelector('.btn-primary');
        var order = button.getAttribute('data-order');
        var table = document.querySelector('.table-bordered tbody');
        var rows = Array.from(table.querySelectorAll('tr'));

        // Filtrar filas que no sean el mensaje de "No hay datos"
        rows = rows.filter(row => !row.querySelector('td').innerText.includes('No hay datos'));

        rows.sort(function(rowA, rowB) {
            var notaA = parseFloat(rowA.querySelector('.final-grade').textContent) || 0;
            var notaB = parseFloat(rowB.querySelector('.final-grade').textContent) || 0;
            return order === 'asc' ? notaA - notaB : notaB - notaA;
        });

        table.innerHTML = "";
        rows.forEach(r => table.appendChild(r));

        if (order === 'asc') {
            button.setAttribute('data-order', 'desc');
            button.querySelector('i').classList.replace('fa-sort-amount-down', 'fa-sort-amount-up');
        } else {
            button.setAttribute('data-order', 'asc');
            button.querySelector('i').classList.replace('fa-sort-amount-up', 'fa-sort-amount-down');
        }
    }

    // Script para ocultar/mostrar el menú lateral
    document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('main').classList.toggle('active');
    });
    </script>
</body>
</html>