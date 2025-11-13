<?php
// Iniciar la sesión
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

// Conexión a la base de datos y otras configuraciones
$dsn = "mysql:host=localhost;dbname=PROYECTO_ES";
$username = "userproyect";
$password = "FGK202412345";

try {
    // Crear la conexión PDO
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener el rol del usuario actual
$sql = "SELECT U.NOMBRE, U.APELLIDO, R.ROL FROM USUARIO U JOIN ROL R ON U.ID_ROL = R.ID_ROL WHERE ID_USUARIO = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

// Nombre y rol del usuario actual
$nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
$rol = $row['ROL'];

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Consulta para obtener las evaluaciones de jurados activos
$query = "
    SELECT CONCAT(u.NOMBRE, ' ', u.APELLIDO) AS JURADO, 
           p.PROYECTO AS PROYECTO, 
           a.NOM_ACTIVIDAD AS ACTIVIDAD, 
           a.PORCENTAJE AS PORCENTAJE,
           c.CALIFICACION AS CALIFICACION_ACTIVIDAD
    FROM NOTAS n
    JOIN USUARIO u ON u.ID_USUARIO = n.ID_USUARIO
    JOIN PROYECTO p ON p.ID_PROYECTO = n.ID_PROYECTO
    JOIN CALIFICACION c ON c.ID_CALIFICACION = n.ID_CALIFICACION
    JOIN ACTIVIDAD a ON a.ID_ACTIVIDAD = c.ID_ACTIVIDAD
    WHERE u.ESTADO = 1  -- SOLO JURADOS ACTIVOS
    ORDER BY p.ID_PROYECTO, u.ID_USUARIO, a.ID_ACTIVIDAD";

// Ejecutar la consulta usando PDO
$stmt = $pdo->prepare($query);
$stmt->execute();

// Crear arrays para almacenar las evaluaciones
$evaluaciones = [];
$jurados = []; // Para almacenar los nombres de los jurados

// Recorrer los resultados
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Agrupar los datos por jurado y proyecto
    $evaluaciones[$row['JURADO']][$row['PROYECTO']][$row['ACTIVIDAD']] = [
        'calificacion_bruta' => $row['CALIFICACION_ACTIVIDAD'],
        'porcentaje' => $row['PORCENTAJE']
    ];

    // Agregar el nombre del jurado al array de jurados (si no existe)
    if (!in_array($row['JURADO'], $jurados)) {
        $jurados[] = $row['JURADO'];
    }
}

// Consulta para obtener las actividades con id_permiso = 2
$query_actividades_permiso = "
    SELECT p.PROYECTO AS PROYECTO, 
           a.NOM_ACTIVIDAD AS ACTIVIDAD, 
           a.PORCENTAJE AS PORCENTAJE,
           c.CALIFICACION AS CALIFICACION_ACTIVIDAD
    FROM CALIFICACION c
    JOIN PROYECTO p ON p.ID_PROYECTO = c.ID_PROYECTO
    JOIN ACTIVIDAD a ON a.ID_ACTIVIDAD = c.ID_ACTIVIDAD
    WHERE a.ID_PERMISO = 2
    ORDER BY p.ID_PROYECTO, a.ID_ACTIVIDAD";

// Ejecutar la consulta
$stmt_actividades_permiso = $pdo->prepare($query_actividades_permiso);
$stmt_actividades_permiso->execute();

// Crear arrays para almacenar las actividades con permiso
$actividades_permiso = [];
while ($row = $stmt_actividades_permiso->fetch(PDO::FETCH_ASSOC)) {
    $actividades_permiso[$row['PROYECTO']][$row['ACTIVIDAD']] = [
        'calificacion_bruta' => $row['CALIFICACION_ACTIVIDAD'],
        'porcentaje' => $row['PORCENTAJE']
    ];
}

// Calcular la calificación final para cada jurado y proyecto
foreach ($evaluaciones as $jurado => $proyectos) {
    foreach ($proyectos as $proyecto => $actividades) {
        $nota_pitch = 0;
        $nota_primera_fase = 0;
        
        // Buscar las notas del Pitch y Primera Fase
        foreach ($actividades as $actividad => $datos) {
            if (stripos($actividad, 'pitch') !== false || $datos['porcentaje'] == 70) {
                $nota_pitch = $datos['calificacion_bruta'];
            }
            if (stripos($actividad, 'PRIMERA FASE') !== false || $datos['porcentaje'] == 30) {
                $nota_primera_fase = $datos['calificacion_bruta'];
            }
        }
        
        // Buscar también en actividades con permiso
        if (isset($actividades_permiso[$proyecto])) {
            foreach ($actividades_permiso[$proyecto] as $actividad_permiso => $datos_permiso) {
                if (stripos($actividad_permiso, 'pitch') !== false || $datos_permiso['porcentaje'] == 70) {
                    $nota_pitch = $datos_permiso['calificacion_bruta'];
                }
                if (stripos($actividad_permiso, 'PRIMERA FASE') !== false || $datos_permiso['porcentaje'] == 30) {
                    $nota_primera_fase = $datos_permiso['calificacion_bruta'];
                }
            }
        }
        
        // Calcular ponderaciones - PRIMERA FASE se multiplica por 30%
        $pitch_ponderado = $nota_pitch;
        $primera_fase_ponderada = $nota_primera_fase * 0.30;
        $calificacion_final = $pitch_ponderado + $primera_fase_ponderada;
        
        // Guardar los resultados
        $evaluaciones[$jurado][$proyecto]['calculo_final'] = [
            'nota_pitch' => $nota_pitch,
            'nota_primera_fase' => $nota_primera_fase,
            'primera_fase_ponderada' => $primera_fase_ponderada,
            'calificacion_final' => $calificacion_final
        ];
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

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
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
                                <span>Cerrar Sesión</span>
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
                    <?php
                    $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD FROM ACTIVIDAD";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<li>
                            <a href="calificarp.php?id_actividad=' . $row['ID_ACTIVIDAD'] . '">
                                <i class="bi bi-circle"></i><span>' . $row['NOM_ACTIVIDAD'] . '</span>
                            </a>
                        </li>';
                    }
                    ?>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
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
                <a class="nav-link collapsed" href="resultadosfondosp.php">
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
            </li>
        </ul>
    </aside><!-- End Sidebar-->

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Resultados de Evaluación</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="panel_juradop.php">Panel</a></li>
                    <li class="breadcrumb-item active">Resultados Específicos</li>
                </ol>
            </nav>
        </div><!-- End Page Title -->

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Resultados por Proyecto - Detallados</h5>
                            <p class="text-muted">Mostrando resultados de <?php echo $numero_jurados_activos; ?> jurados activos</p>
                            
                            <!-- ========== EXPLICACIÓN DETALLADA ========== -->
                            <div class="alert alert-info mb-4 py-3" role="alert" style="font-size: 0.9rem;">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong class="me-2">Cálculo Detallado - Resultados Específicos por Jurado:</strong>
                                </div>
                                <div class="ms-3">
                                    <div class="mb-2">
                                        <strong>Para cada jurado y proyecto:</strong>
                                    </div>
                                    
                                    <div class="mb-1">
                                        <strong>1. Evaluación Pitch (70%):</strong>
                                    </div>
                                    <div class="ms-3 mb-2">
                                        <small>• Se toma la calificación bruta que el jurado asignó al pitch</small><br>
                                        <small>• La nota ya viene ponderada al 70% desde el sistema</small><br>
                                        <small><em>Valor: Calificación bruta del pitch (ya ponderada)</em></small>
                                    </div>
                                    
                                    <div class="mb-1">
                                        <strong>2. Primera Evaluación (30%):</strong>
                                    </div>
                                    <div class="ms-3 mb-2">
                                        <small>• Se toma la calificación bruta de la primera fase</small><br>
                                        <small>• Se multiplica por 0.30 (30%) para obtener el valor ponderado</small><br>
                                        <small><em>Fórmula: Calificación primera fase × 0.30</em></small>
                                    </div>
                                    
                                    <div class="mb-1">
                                        <strong>3. Nota Final por Jurado:</strong>
                                    </div>
                                    <div class="ms-3 mb-2">
                                        <small>• Suma directa del Pitch + Primera Fase ponderada</small><br>
                                        <small>• Representa la evaluación completa de CADA jurado individualmente</small><br>
                                        <small><em>Fórmula: Pitch + (Primera Fase × 30%)</em></small>
                                    </div>
                                </div>
                            </div>

                            <?php
                            if (empty($jurados)) {
                                echo "<p class='text-center'>No hay jurados activos con evaluaciones registradas.</p>";
                            } else {
                                // Recorrer cada jurado para crear una tabla individual
                                foreach ($jurados as $jurado) {
                                    echo "<h5 style='margin-bottom: 15px;'>$jurado</h5>";
                                    echo "<div class='table-responsive' style='max-width: 1200px;'>";
                                    echo "<table class='table table-bordered' style='font-size: 0.85rem;'>";
                                    echo "<thead><tr><th scope='col'>Proyecto</th>";
                                    echo "<th scope='col'>Nota Pitch</th>";
                                    echo "<th scope='col'>Nota 1ra Fase</th>";
                                    echo "<th scope='col'>1ra Fase × 30%</th>";
                                    echo "<th scope='col'>Calificación Final</th>";
                                    echo "</tr></thead>";
                                    echo "<tbody>";

                                    // Mostrar las calificaciones para cada proyecto evaluado por este jurado
                                    if (isset($evaluaciones[$jurado])) {
                                        foreach ($evaluaciones[$jurado] as $proyecto => $datos) {
                                            if (isset($datos['calculo_final'])) {
                                                $calculo = $datos['calculo_final'];
                                                echo "<tr>";
                                                echo "<td>$proyecto</td>";
                                                echo "<td>" . number_format($calculo['nota_pitch'], 2) . "</td>";
                                                echo "<td>" . number_format($calculo['nota_primera_fase'], 2) . "</td>";
                                                echo "<td>" . number_format($calculo['primera_fase_ponderada'], 2) . "</td>";
                                                echo "<td><strong>" . number_format($calculo['calificacion_final'], 2) . "</strong></td>";
                                                echo "</tr>";
                                            }
                                        }
                                    } else {
                                        echo "<tr><td colspan='5'>No hay calificaciones disponibles para este jurado.</td></tr>";
                                    }

                                    echo "</tbody></table>";
                                    echo "</div>";
                                    echo "<br>";
                                }
                            }
                            ?>

                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main><!-- End #main -->

    <!-- ======= Footer ======= -->
    <footer id="footer" class="footer">
        <div class="copyright">
            &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservados.
        </div>
    </footer><!-- End Footer -->

    <!-- Vendor JS Files -->
    <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/chart.js/chart.umd.js"></script>
    <script src="assets/vendor/echarts/echarts.min.js"></script>
    <script src="assets/vendor/quill/quill.min.js"></script>
    <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
    <script src="assets/vendor/tinymce/tinymce.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main').classList.toggle('active');
        });
    </script>

    <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>

</body>

</html>