<?php
// Iniciar la sesión
session_start();
if (!isset($_SESSION['id_usuario'])) {
  header("Location: index.php");
  exit();
}

// Configuración de la conexión PDO
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

// CONSULTA MEJORADA: Obtener TODAS las notas individuales de los JURADOS ACTIVOS para el pitch
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

// Organizar notas de jurados por proyecto - CALCULAR PROMEDIO REAL (MISMA LÓGICA DEL SEGUNDO CÓDIGO)
$promedios_pitch_por_proyecto = [];
foreach ($notas_pitch_jurados as $nota) {
    $id_proyecto = $nota['ID_PROYECTO'];
    if (!isset($promedios_pitch_por_proyecto[$id_proyecto])) {
        $promedios_pitch_por_proyecto[$id_proyecto] = [
            'suma_notas' => 0,
            'cantidad_jurados' => 0,
            'promedio' => 0
        ];
    }
    $promedios_pitch_por_proyecto[$id_proyecto]['suma_notas'] += $nota['nota_jurado'];
    $promedios_pitch_por_proyecto[$id_proyecto]['cantidad_jurados']++;
}

// Calcular promedios finales
foreach ($promedios_pitch_por_proyecto as $id_proyecto => $datos) {
    if ($datos['cantidad_jurados'] > 0) {
        $promedios_pitch_por_proyecto[$id_proyecto]['promedio'] = 
            $datos['suma_notas'] / $datos['cantidad_jurados'];
    }
}

// Obtener promedios por proyecto y actividad (para otras actividades)
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

// Obtener lista de proyectos
$query_proyectos = "SELECT ID_PROYECTO, PROYECTO FROM PROYECTO ORDER BY ID_PROYECTO";
$stmt_proyectos = $pdo->prepare($query_proyectos);
$stmt_proyectos->execute();
$todos_proyectos = $stmt_proyectos->fetchAll(PDO::FETCH_ASSOC);

// Preparar arrays con valores calculados (MISMA LÓGICA DEL SEGUNDO CÓDIGO)
$proyectos_valores = [];
$notas_finales_calculadas = [];

foreach ($todos_proyectos as $proyecto) {
    $id_proyecto = $proyecto['ID_PROYECTO'];

    // Inicializar suma total para nota final
    $suma_ponderada_total = 0;

    // Calcular valores para mostrar en tabla y para nota final
    foreach ($actividades as $actividad) {
        $id_actividad = $actividad['ID_ACTIVIDAD'];
        $porcentaje = (int)$actividad['PORCENTAJE'];

        if ($id_actividad == $id_actividad_70) {
            // Para el pitch: Usar el promedio calculado de TODOS los jurados activos
            if (isset($promedios_pitch_por_proyecto[$id_proyecto])) {
                $valor_pitch = $promedios_pitch_por_proyecto[$id_proyecto]['promedio'];
                $proyectos_valores[$id_proyecto][$id_actividad] = $valor_pitch;
                $suma_ponderada_total += $valor_pitch;
            } else {
                $proyectos_valores[$id_proyecto][$id_actividad] = 0;
                // No sumar nada si no hay calificaciones
            }
            
        } elseif ($id_actividad == $id_actividad_30) {
            // Para la primera evaluación (30%): Aplicar ponderación aquí
            if (isset($proyectos_promedios[$id_proyecto][$id_actividad])) {
                $valor_30 = $proyectos_promedios[$id_proyecto][$id_actividad] * ($porcentaje / 100);
                $proyectos_valores[$id_proyecto][$id_actividad] = $valor_30;
                $suma_ponderada_total += $valor_30;
            } else {
                $proyectos_valores[$id_proyecto][$id_actividad] = 0;
                // No sumar nada si no hay calificaciones
            }
        } else {
            // Otras actividades (si las hay)
            if (isset($proyectos_promedios[$id_proyecto][$id_actividad])) {
                $valor_ponderado = $proyectos_promedios[$id_proyecto][$id_actividad] * ($porcentaje / 100);
                $proyectos_valores[$id_proyecto][$id_actividad] = $valor_ponderado;
                $suma_ponderada_total += $valor_ponderado;
            } else {
                $proyectos_valores[$id_proyecto][$id_actividad] = 0;
                // No sumar nada si no hay calificaciones
            }
        }
    }

    // La nota final es la suma de todos los valores ponderados
    $notas_finales_calculadas[$id_proyecto] = $suma_ponderada_total;
}

$results = $todos_proyectos;
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
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo htmlspecialchars($nombre_usuario); ?></span>
          </a><!-- End Profile Iamge Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6><?php echo htmlspecialchars($nombre_usuario); ?></h6>
              <span><?php echo htmlspecialchars($rol); ?></span>
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
        <a class="nav-link collapsed" href="panel_jurado.php">
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
          include 'bdd/database.php'; // Asegúrate de tener una conexión PDO en este archivo

          $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD"; // Ajusta el nombre de la tabla y los campos según tu base de datos
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
          <i class="bi bi-menu-button-wide"></i><span>Resultados</span><i class="bi bi-chevron-down ms-auto"></i>
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
        <a class="nav-link" href="manual_jurado.php">
          <i class="bi bi-question-circle"></i>
          <span>Manual</span>
        </a>
      </li><!-- End F.A.Q Page Nav -->

    </ul>

  </aside><!-- End Sidebar-->
  <main id="main" class="main">
    <div class="pagetitle">
      <h1>Resultados Globales</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_juradop.php">Inicio</a></li>
          <li class="breadcrumb-item active">Resultados Globales</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">
        <div class="col-lg-12">
          <div class="card recent-sales overflow-auto">
            <div class="card-body">
              <h5 class="card-title">Resultados Finales <span>| Globales</span></h5>
              <p class="text-muted">Cálculos basados en <?php echo $numero_jurados_activos; ?> jurados activos</p>
              
              <!-- Información de cálculo mejorada (IGUAL AL SEGUNDO CÓDIGO) -->
              <div class="alert alert-info mb-3 py-3" role="alert" style="font-size: 0.9rem;">
                <div class="d-flex align-items-center mb-2">
                  <i class="bi bi-info-circle me-2"></i>
                  <strong class="me-2">Cálculo Detallado - Resultados Globales:</strong>
                </div>
                <div class="ms-3">
                  <div class="mb-2">
                    <strong>Para cada proyecto:</strong>
                  </div>
                  
                  <div class="mb-1">
                    <strong>1. Evaluación Pitch (70%):</strong>
                  </div>
                  <div class="ms-3 mb-2">
                    <small>• Se toman TODAS las calificaciones de jurados activos</small><br>
                    <small>• Se calcula el promedio: (Suma de notas) / (Número de jurados que calificaron)</small><br>
                    <small>• La nota ya viene ponderada al 70% desde el sistema de calificación</small><br>
                    <small><em>Fórmula: Promedio(notas_jurados_activos)</em></small>
                  </div>
                  
                  <div class="mb-1">
                    <strong>2. Primera Evaluación (30%):</strong>
                  </div>
                  <div class="ms-3 mb-2">
                    <small>• Se toma el promedio de calificaciones de esa actividad</small><br>
                    <small>• Se aplica la ponderación del 30%</small><br>
                    <small><em>Fórmula: Promedio actividad × 0.30</em></small>
                  </div>
                  
                  <div class="mb-1">
                    <strong>3. Nota Final:</strong>
                  </div>
                  <div class="ms-3">
                    <small>• Suma del Pitch (promedio ponderado) + Primera Evaluación (ponderada)</small><br>
                    <small><em>Fórmula: Promedio Pitch + (Promedio Primera Evaluación × 0.30)</em></small>
                  </div>

                </div>
              </div>

              <table class="table table-bordered">
                <thead>
                  <tr>
                    <td scope="col"><b>Proyecto</b></td>

                    <!-- Mostrar actividades como encabezado de la tabla -->
                    <?php foreach ($actividades as $actividad): ?>
                      <td scope="col"><b><?= htmlspecialchars($actividad['NOM_ACTIVIDAD']); ?> (<?= htmlspecialchars($actividad['PORCENTAJE']); ?>%)</b></td>
                    <?php endforeach; ?>

                    <th scope="col">Nota Final
                      <button class="btn-modify" data-order="asc" type="button" onclick="ordenarNotas()" style="margin-left: 5px;">
                        <i class="fas fa-sort-amount-down"></i>
                      </button>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($results as $row): ?>
                    <tr>
                      <!-- Mostrar el nombre del proyecto -->
                      <td><?= htmlspecialchars($row['PROYECTO']); ?></td>

                      <!-- Mostrar el promedio de calificaciones para cada actividad de ese proyecto -->
                      <?php foreach ($actividades as $actividad): ?>
                        <td>
                          <?php
                          $id_proyecto = $row['ID_PROYECTO'];
                          $id_actividad = $actividad['ID_ACTIVIDAD'];

                          if (isset($proyectos_valores[$id_proyecto][$id_actividad]) && $proyectos_valores[$id_proyecto][$id_actividad] !== null) {
                            // Mostrar el valor calculado (ya ponderado por porcentaje)
                            echo htmlspecialchars(number_format($proyectos_valores[$id_proyecto][$id_actividad], 2));
                          } else {
                            // Si no hay calificación, mostrar '0.00'
                            echo '0.00';
                          }
                          ?>
                        </td>
                      <?php endforeach; ?>

                      <!-- Nota final del proyecto -->
                      <td>
                        <?php
                        $id_proyecto = $row['ID_PROYECTO'];
                        if (isset($notas_finales_calculadas[$id_proyecto])) {
                            echo htmlspecialchars(number_format($notas_finales_calculadas[$id_proyecto], 2));
                        } else {
                            echo '0.00';
                        }
                        ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

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

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.js"></script>
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

  <script>
    function ordenarNotas() {
      // Obtener el botón y el estado actual de orden
      var button = document.querySelector('.btn-modify');
      var order = button.getAttribute('data-order');

      // Obtener la tabla y las filas
      var table = document.querySelector('.table-bordered tbody');
      var rows = Array.from(table.querySelectorAll('tr'));

      // Función de comparación para ordenar
      rows.sort(function(rowA, rowB) {
        // Obtener las notas de las dos filas (última columna - Nota Final)
        var notaA = parseFloat(rowA.querySelector('td:last-child').innerText) || 0;
        var notaB = parseFloat(rowB.querySelector('td:last-child').innerText) || 0;

        // Comparar según el orden actual
        if (order === 'asc') {
          return notaA - notaB; // Orden ascendente
        } else {
          return notaB - notaA; // Orden descendente
        }
      });

      // Remover las filas existentes y agregar las ordenadas
      table.innerHTML = "";
      rows.forEach(function(row) {
        table.appendChild(row);
      });

      // Cambiar el estado de orden y el icono en el botón
      if (order === 'asc') {
        button.setAttribute('data-order', 'desc');
        button.querySelector('i').classList.remove('fa-sort-amount-down');
        button.querySelector('i').classList.add('fa-sort-amount-up');
      } else {
        button.setAttribute('data-order', 'asc');
        button.querySelector('i').classList.remove('fa-sort-amount-up');
        button.querySelector('i').classList.add('fa-sort-amount-down');
      }
    }
  </script>

</body>

</html>