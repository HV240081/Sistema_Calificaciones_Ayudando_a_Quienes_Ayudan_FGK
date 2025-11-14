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

// OBTENER JURADOS DINÁMICAMENTE (ID_ROL 2 y 3) EN ORDEN ESPECÍFICO - SOLO ACTIVOS
$sql_jurados = "SELECT ID_USUARIO, NOMBRE, APELLIDO FROM USUARIO WHERE ID_ROL IN (2, 3) AND ESTADO = 1 ORDER BY 
                CASE 
                    WHEN CONCAT(NOMBRE, ' ', APELLIDO) = 'Fernando Kriete' THEN 1
                    WHEN CONCAT(NOMBRE, ' ', APELLIDO) = 'José Giammatei' THEN 2
                    WHEN CONCAT(NOMBRE, ' ', APELLIDO) = 'Alexandra Araujo' THEN 3
                    WHEN CONCAT(NOMBRE, ' ', APELLIDO) = 'Francisco Pérez' THEN 4
                    WHEN CONCAT(NOMBRE, ' ', APELLIDO) = 'José Montalvo' THEN 5
                    WHEN CONCAT(NOMBRE, ' ', APELLIDO) = 'Juana Jule' THEN 6
                    ELSE 7
                END, APELLIDO, NOMBRE";
$stmt_jurados = $pdo->prepare($sql_jurados);
$stmt_jurados->execute();
$jurados_dinamicos = $stmt_jurados->fetchAll(PDO::FETCH_ASSOC);

// Crear array de jurados en orden específico
$jurados_orden_fijo = [];
foreach ($jurados_dinamicos as $jurado) {
    $jurados_orden_fijo[] = $jurado['NOMBRE'] . ' ' . $jurado['APELLIDO'];
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

// --- DEFINICIÓN DE LA ACTIVIDAD PITCH ---
$actividad_pitch_id = 10; // Asumimos ID 10 para la Evaluación Pitch

// CONSULTA MODIFICADA: OBTIENE CALIFICACION.CALIFICACION Y LA MAPEA AL JURADO
// La nota se extrae de la tabla CALIFICACION y se vincula al JURADO que realizó los criterios
// mediante las tablas de enlace (NOTA_CRITERIO_CALIFICACION y NOTA_CRITERIO).
$query = "
    SELECT 
        CONCAT(u.NOMBRE, ' ', u.APELLIDO) AS JURADO, 
        p.PROYECTO AS PROYECTO, 
        c.CALIFICACION AS CALIFICACION, -- <--- USAMOS LA NOTA FINAL DE LA TABLA CALIFICACION
        u.ID_USUARIO AS JURADO_ID,
        p.ID_PROYECTO
    FROM CALIFICACION c 
    JOIN PROYECTO p ON p.ID_PROYECTO = c.ID_PROYECTO
    -- Hacemos el enlace a través de las notas de criterios para identificar al jurado
    JOIN NOTA_CRITERIO_CALIFICACION ncc ON c.ID_CALIFICACION = ncc.ID_CALIFICACION
    JOIN NOTA_CRITERIO nc ON ncc.ID_NOTA_CRITERIO = nc.ID_NOTACRITERIO
    JOIN USUARIO u ON u.ID_USUARIO = nc.ID_USUARIO
    
    WHERE c.ID_ACTIVIDAD = :actividad_id -- FILTRAMOS SOLO POR LA ACTIVIDAD PITCH (ID 10)
    AND u.ID_ROL IN (2, 3) 
    AND u.ESTADO = 1 -- SOLO JURADOS ACTIVOS
    GROUP BY p.PROYECTO, u.ID_USUARIO, c.CALIFICACION 
    ORDER BY p.ID_PROYECTO, u.ID_USUARIO";

// Ejecutar la consulta usando PDO
$stmt = $pdo->prepare($query);
// Pasamos el ID de la actividad como parámetro
$stmt->execute(['actividad_id' => $actividad_pitch_id]);

// Crear un array para almacenar las evaluaciones
$evaluaciones = [];

// Recorrer los resultados de calificaciones
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // La calificación_final ya es CALIFICACION.CALIFICACION
    $calificacion_final = $row['CALIFICACION'];
    
    // Agrupar los datos por proyecto y jurado
    $evaluaciones[$row['PROYECTO']][$row['JURADO']] = $calificacion_final;
}

// Obtener todos los proyectos para mostrar en la tabla
$query_proyectos = "SELECT ID_PROYECTO, PROYECTO FROM PROYECTO ORDER BY ID_PROYECTO";
$stmt_proyectos = $pdo->prepare($query_proyectos);
$stmt_proyectos->execute();
$proyectos_fijos = $stmt_proyectos->fetchAll(PDO::FETCH_ASSOC);
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
            <li class="nav-item"></li>
            <a class="nav-link collapsed" href="addactivities.php">
                <i class="bi bi-search"></i>
                <span>Actividades</span>
            </a>
            </li><!-- End Activities Page Nav -->
            <li class="nav-item">
                <a class="nav-link" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
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
                <a class="nav-link collapsed" data-bs-target="#evaluacion-nav" data-bs-toggle="collapse" href="#">
                    <i class="bi bi-check-circle"></i><span>Evaluación</span><i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="evaluacion-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <!-- Aquí se cargarán dinámicamente las actividades -->
                    <?php
                    $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD FROM ACTIVIDAD";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<li>
                            <a href="calificarAdmin.php?id_actividad=' . $row['ID_ACTIVIDAD'] . '">
                                <i class="bi bi-circle"></i><span>' . $row['NOM_ACTIVIDAD'] . '</span>
                            </a>
                        </li>';
                    }
                    ?>
                </ul>
            </li>
            
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
            </li>
            <!-- End F.A.Q Page Nav -->
            
            <!-- End Forms Nav -->
        </ul>
    </aside><!-- End Sidebar-->

    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Resultados de Evaluación</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="panel_admin.php">Panel</a></li>
                    <li class="breadcrumb-item active">Resultados Individuales</li>
                </ol>
            </nav>
        </div><!-- End Page Title -->

        <section class="section">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Resultados de Calificaciones - Evaluación Pitch</h5>
                            <p class="text-muted">Mostrando resultados de <?php echo $numero_jurados_activos; ?> jurados activos</p>
                            
                            <!-- ========== EXPLICACIÓN CÁLCULO INDIVIDUAL ========== -->
                            <div class="alert alert-info mb-4" role="alert">
                                <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Cálculo de Calificaciones Individuales</h4>
                                <hr>
                                <p class="mb-2"><strong>Fórmula para cada jurado:</strong></p>
                                <div class="ms-3">
                                    <p class="mb-1"><strong>Calificación Individual = (Suma de 6 criterios / 6) × 70%</strong></p>
                                    <ul class="mb-2">
                                        <li>Cada jurado califica los 6 criterios del pitch de 0 a 10 puntos</li>
                                        <li>Se calcula el promedio simple de los 6 criterios</li>
                                        <li>El resultado se multiplica por 0.70 (70%)</li>
                                        <li>La calificación final se escala a 2 decimales</li>
                                    </ul>
                                </div>
                                <p class="mb-0"><strong>Nota:</strong> Esta tabla muestra las calificaciones individuales de cada jurado para cada proyecto en la Evaluación Pitch.</p>
                            </div>

                            <?php if (empty($jurados_orden_fijo)): ?>
                                <p class="text-center">No hay jurados activos con evaluaciones registradas.</p>
                            <?php else: ?>
                                <table class="table table-bordered" id="resultsTable" style="font-size: 14px; width: 100%;">
                                    <thead>
                                        <tr>
                                            <th scope="col">Proyecto</th>
                                            <?php
                                            // Mostrar los nombres de TODOS los jurados ACTIVOS en el orden específico
                                            foreach ($jurados_orden_fijo as $jurado) {
                                                echo "<th scope='col'>$jurado</th>";
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Mostrar TODOS los proyectos fijos
                                        foreach ($proyectos_fijos as $proyecto_fijo) {
                                            $proyecto_nombre = $proyecto_fijo['PROYECTO'];
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($proyecto_nombre) . "</td>";

                                            // Mostrar calificaciones para CADA jurado ACTIVO en el orden específico
                                            foreach ($jurados_orden_fijo as $jurado_nombre) {
                                                // Verificar si existe calificación para este proyecto y jurado
                                                $calificacion = '0.00';
                                                if (isset($evaluaciones[$proyecto_nombre][$jurado_nombre])) {
                                                    $calificacion = $evaluaciones[$proyecto_nombre][$jurado_nombre];
                                                    // Formatear a 2 decimales si es numérico
                                                    if (is_numeric($calificacion)) {
                                                        $calificacion = number_format($calificacion, 2);
                                                    }
                                                }
                                                
                                                echo "<td>" . $calificacion . "</td>";
                                            }
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

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

    <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>
    <script>
        document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main').classList.toggle('active');
        });
    </script>

</body>

</html>