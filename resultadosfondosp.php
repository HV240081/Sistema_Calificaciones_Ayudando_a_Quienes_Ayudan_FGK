<?php
include 'bdd/database.php';
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del usuario
$sql = "SELECT U.NOMBRE, U.APELLIDO, R.ROL 
        FROM USUARIO U 
        JOIN ROL R ON U.ID_ROL = R.ID_ROL 
        WHERE ID_USUARIO = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
$rol = $row['ROL'];

// ===============================================
// OBTENER PROYECTOS Y NOTAS FINALES (CÁLCULO MEJORADO)
// ===============================================

// Obtener actividades
$query_actividades = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD ORDER BY ID_ACTIVIDAD";
$stmt_actividades = $pdo->prepare($query_actividades);
$stmt_actividades->execute();
$actividades = $stmt_actividades->fetchAll(PDO::FETCH_ASSOC);

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

// CONSULTA: Obtener las notas individuales de los JURADOS ACTIVOS para el pitch
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

// Obtener lista de proyectos ÚNICOS (MODIFICADO)
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

// ===============================================
// OBTENER FONDOS ASIGNADOS (PIVOTEADOS) - MODIFICADO
// ===============================================
try {
    // Primero obtener todos los proyectos con sus notas calculadas
    $proyectos = [];
    
    foreach ($todos_proyectos as $proyecto) {
        $id_proyecto = $proyecto['ID_PROYECTO'];
        $nota_calculada = isset($notas_finales_calculadas[$id_proyecto]) ? 
            number_format($notas_finales_calculadas[$id_proyecto], 2) : 'N/A';
        
        // Obtener ID_NFINAL para este proyecto
        $stmt_nfinal = $pdo->prepare("SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = ?");
        $stmt_nfinal->execute([$id_proyecto]);
        $nfinal_row = $stmt_nfinal->fetch(PDO::FETCH_ASSOC);
        
        $id_nfinal = $nfinal_row ? $nfinal_row['ID_NFINAL'] : null;
        
        // Inicializar el proyecto con fondos vacíos
        $proyectos[$id_proyecto] = [
            'PROYECTO' => $proyecto['PROYECTO'],
            'NOTA_FINAL' => $nota_calculada,
            'FONDOS' => [],
            'ID_NFINAL' => $id_nfinal
        ];
    }

    // Ahora obtener los fondos asignados para los proyectos que los tengan
    $stmt = $pdo->query("
        SELECT N.ID_NFINAL, P.ID_PROYECTO, P.PROYECTO, N.NOTA_FINAL, PR.PREMIO
        FROM NFINAL N
        JOIN PROYECTO P ON N.ID_PROYECTO = P.ID_PROYECTO
        LEFT JOIN PREMIO_NFINAL PN ON N.ID_NFINAL = PN.ID_NFINAL
        LEFT JOIN PREMIO PR ON PN.ID_PREMIO = PR.ID_PREMIO
        ORDER BY P.PROYECTO
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Asignar fondos a los proyectos correspondientes
    foreach ($rows as $r) {
        $id_proyecto = $r['ID_PROYECTO'];
        if (isset($proyectos[$id_proyecto]) && $r['PREMIO'] !== null) {
            $proyectos[$id_proyecto]['FONDOS'][] = $r['PREMIO'];
        }
    }

    // Calcular el máximo número de fondos para las columnas
    $maxFondos = 0;
    foreach ($proyectos as $proyecto) {
        $maxFondos = max($maxFondos, count($proyecto['FONDOS']));
    }

    // Si no hay fondos asignados, establecer $maxFondos a 1 para mostrar al menos una columna
    if ($maxFondos == 0) {
        $maxFondos = 1;
    }

} catch (PDOException $e) {
    echo "Error al realizar la consulta: " . $e->getMessage();
    $proyectos = [];
    $maxFondos = 1;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Fondos Asignados - Panel Jurado</title>

    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        th, td { text-align: center; vertical-align: middle; }
        .sidebar.active { width: 80px; transition: width 0.3s; }
        .main.active { margin-left: 80px; transition: margin-left 0.3s; }
        .btn-modify { 
            border: none; 
            background: none; 
            cursor: pointer; 
            margin-left: 5px;
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
                    </a><!-- End Profile Iamge Icon -->

                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                        <li class="dropdown-header">
                            <h6><?php echo $nombre_usuario ?></h6>
                            <span><?php echo $rol ?></span>
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

                    </ul><!-- End Profile Dropdown Items -->
                </li><!-- End Profile Nav -->

            </ul>
        </nav><!-- End Icons Navigation -->

    </header><!-- End Header -->

    <!-- ======= Sidebar ======= -->
    <aside id="sidebar" class="sidebar">

        <ul class="sidebar-nav" id="sidebar-nav">

            <li class="nav-item">
                <a class="nav-link collapsed" href="panel_juradop.php">
                    <i class="bi bi-grid"></i>
                    <span>Panel Jurado</span>
                </a>
            </li><!-- End Dashboard Nav -->

            <li class="nav-item">
                <a class="nav-link collapsed" data-bs-target="#evaluacion-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-check-circle"></i><span>Evaluación</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="evaluacion-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <!-- Aquí se cargarán dinámicamente las actividades -->
                    <?php
                    $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $actividades_menu = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($actividades_menu) {
                        foreach ($actividades_menu as $actividad) {
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
            </li><!-- End Evaluación Nav -->

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
            </li><!-- End Components Nav -->

            <li class="nav-item">
                <a class="nav-link collapsed" href="fondos.php">
                    <i class="bi bi-piggy-bank"></i>
                    <span>Asignar Fondos</span>
                </a>
            </li><!-- End Icons Nav -->

            <li class="nav-item">
                <a class="nav-link" href="resultadosfondosp.php">
                    <i class="bi bi-gem"></i>
                    <span>Fondos asignados</span>
                </a>
            </li><!-- End Icons Nav -->

            <li class="nav-item">
                <a class="nav-link collapsed" href="user-profilep.php">
                    <i class="bi bi-person"></i>
                    <span>Perfil</span>
                </a>
            </li><!-- End Profile Page Nav -->

            <li class="nav-item">
                <a class="nav-link collapsed" href="manual_juradop.php">
                    <i class="bi bi-question-circle"></i>
                    <span>Manual</span>
                </a>
            </li><!-- End F.A.Q Page Nav -->

        </ul>

    </aside><!-- End Sidebar-->

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Fondos asignados</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="panel_juradop.php">Inicio</a></li>
                <li class="breadcrumb-item active">Fondos asignados</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Fondos asignados <span> | Resultados Finales</span></h5>

                        <table class="table table-bordered" style="text-align: center;">
                            <thead>
                                <tr>
                                    <th scope="col">Proyecto</th>
                                    <th scope="col">
                                        Nota Final
                                        <button class="btn btn-primary btn-sm" data-order="asc" type="button" onclick="ordenarNotas()" style="margin-left:5px;">
                                            <i class="fas fa-sort-amount-down"></i>
                                        </button>
                                    </th>
                                    <?php for($i=1;$i<=$maxFondos;$i++): ?>
                                        <th scope="col">Beneficio <?php echo $i ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($proyectos as $id_proyecto => $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['PROYECTO']); ?></td>
                                    <td class="final-grade"><?php echo htmlspecialchars($p['NOTA_FINAL']); ?></td>
                                    <?php
                                    for($i=0;$i<$maxFondos;$i++):
                                        echo '<td style="text-align:center;">'.($p['FONDOS'][$i] ?? '—').'</td>';
                                    endfor;
                                    ?>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($proyectos)): ?>
                                <tr>
                                    <td colspan="<?php echo 2 + $maxFondos; ?>">No hay datos disponibles</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<footer id="footer" class="footer">
    <div class="copyright">
        &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservados.
    </div>
</footer>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/js/main.js"></script>

<script>
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

    document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('main').classList.toggle('active');
    });
</script>
</body>
</html>