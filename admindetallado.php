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


// Consulta para obtener las evaluaciones incluyendo actividades y la calificación final
$query = "
    SELECT CONCAT(u.NOMBRE, ' ', u.APELLIDO) AS JURADO, 
           p.PROYECTO AS PROYECTO, 
           a.NOM_ACTIVIDAD AS ACTIVIDAD, 
           a.PORCENTAJE AS PORCENTAJE,
           c.CALIFICACION AS CALIFICACION_ACTIVIDAD, 
           n.CALIFICACION AS CALIFICACION_FINAL
    FROM NOTAS n
    JOIN USUARIO u ON u.ID_USUARIO = n.ID_USUARIO
    JOIN PROYECTO p ON p.ID_PROYECTO = n.ID_PROYECTO
    JOIN CALIFICACION c ON c.ID_CALIFICACION = n.ID_CALIFICACION
    JOIN ACTIVIDAD a ON a.ID_ACTIVIDAD = c.ID_ACTIVIDAD
    ORDER BY p.ID_PROYECTO, u.ID_USUARIO, a.ID_ACTIVIDAD";

// Ejecutar la consulta usando PDO
$stmt = $pdo->prepare($query);
$stmt->execute();

// Crear arrays para almacenar las evaluaciones y las actividades
$evaluaciones = [];
$jurados = []; // Para almacenar los nombres de los jurados
$actividades = []; // Para almacenar las actividades y sus porcentajes

// Recorrer los resultados
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Agrupar los datos por proyecto y actividad
    $evaluaciones[$row['JURADO']][$row['PROYECTO']]['actividades'][$row['ACTIVIDAD']] = $row['CALIFICACION_ACTIVIDAD'];
    
    // Agregar la calificación final del proyecto
    $evaluaciones[$row['JURADO']][$row['PROYECTO']]['calificacion_final'] = $row['CALIFICACION_FINAL'];
    
    // Agregar la actividad al array de actividades (si no existe)
    if (!in_array($row['ACTIVIDAD'], $actividades)) {
        $actividades[$row['ACTIVIDAD']] = $row['PORCENTAJE'];
    }

    // Agregar el nombre del jurado al array de jurados (si no existe)
    if (!in_array($row['JURADO'], $jurados)) {
        $jurados[] = $row['JURADO'];
    }
}

// Consulta para obtener las actividades con id_permiso = 2, sus calificaciones y porcentajes
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

// Crear arrays para almacenar las actividades con permiso y sus calificaciones
$actividades_permiso = [];
while ($row = $stmt_actividades_permiso->fetch(PDO::FETCH_ASSOC)) {
    // Guardar las actividades, porcentajes y sus calificaciones por proyecto
    $actividades_permiso[$row['PROYECTO']][$row['ACTIVIDAD']] = [
        'calificacion' => $row['CALIFICACION_ACTIVIDAD'],
        'porcentaje' => $row['PORCENTAJE']
    ];
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
                <li class="breadcrumb-item active">Resultados de Evaluación</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Resultados por Proyecto</h5>

                        <?php
                        // Recorrer cada jurado para crear una tabla individual
                        foreach ($jurados as $jurado) {
                            echo "<h5 style='font-weight: bold; margin-bottom: 15px;'>$jurado</h5>"; // Nombre del jurado en negrita y con margen
                            echo "<div class='table-responsive' style='max-width: 1000px;'>"; // Asegura que la tabla sea responsive y limita el ancho
                            echo "<table class='table table-bordered' style='font-size: 0.9rem;'>"; // Tamaño de fuente más pequeño
                            echo "<thead><tr><th scope='col'>Proyecto</th>";
                            
                            // Mostrar el nombre de las actividades con su porcentaje en el encabezado
                            foreach ($actividades as $actividad => $porcentaje) {
                                echo "<th scope='col'>{$actividad} ({$porcentaje}%)</th>";
                            }
                            
                            // Agregar columnas de actividades con id_permiso = 2 si hay resultados, con su porcentaje
                            if (!empty($actividades_permiso)) {
                                // Agregar el nombre y porcentaje de cada actividad con permiso 2
                                foreach (array_keys(current($actividades_permiso)) as $actividad_permiso) {
                                    $porcentaje_permiso = current($actividades_permiso)[$actividad_permiso]['porcentaje'];
                                    echo "<th scope='col'>{$actividad_permiso} ({$porcentaje_permiso}%)</th>";
                                }
                            }

                            // Agregar una columna para la calificación final
                            echo "<th scope='col'>Calificación Final</th>";
                            echo "</tr></thead>";
                            echo "<tbody>";

                            // Mostrar las calificaciones para cada proyecto y actividad evaluada por este jurado
                            if (isset($evaluaciones[$jurado])) {
                                foreach ($evaluaciones[$jurado] as $proyecto => $notas) {
                                    echo "<tr>";
                                    echo "<td>$proyecto</td>";

                                    // Mostrar las calificaciones por actividad
                                    foreach ($actividades as $actividad => $porcentaje) {
                                        echo "<td>" . (isset($notas['actividades'][$actividad]) ? $notas['actividades'][$actividad] : 'N/A') . "</td>";
                                    }

                                    // Mostrar las calificaciones para actividades con id_permiso = 2 si las hay
                                    if (isset($actividades_permiso[$proyecto])) {
                                        foreach ($actividades_permiso[$proyecto] as $actividad_permiso => $datos_permiso) {
                                            echo "<td>{$datos_permiso['calificacion']}</td>";
                                        }
                                    } else {
                                        // Si no hay calificaciones para las actividades con permiso, mostrar N/A
                                        foreach (array_keys($actividades_permiso) as $actividad_permiso) {
                                            echo "<td>N/A</td>";
                                        }
                                    }

                                    // Mostrar la calificación final
                                    echo "<td>" . (isset($notas['calificacion_final']) ? $notas['calificacion_final'] : 'N/A') . "</td>";

                                    echo "</tr>";
                                }
                            } else {
                                // Si el jurado no tiene calificaciones, mostrar un mensaje
                                echo "<tr><td colspan='" . (count($actividades) + count($actividades_permiso) + 2) . "'>No hay calificaciones disponibles para este jurado.</td></tr>";
                            }

                            echo "</tbody></table>";
                            echo "</div>"; // Cerrar el div de table-responsive
                            echo "<br>"; // Espacio entre el nombre del jurado y la siguiente tabla
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
