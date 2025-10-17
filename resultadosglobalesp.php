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

  // Consulta SQL para obtener los proyectos y sus notas finales
  $query = "
SELECT p.ID_PROYECTO, p.PROYECTO AS proyecto, nf.NOTA_FINAL AS nota_final
FROM PROYECTO p
JOIN NFINAL nf ON p.ID_PROYECTO = nf.ID_PROYECTO
";

  // Ejecutar la consulta y obtener los resultados
  $stmt = $pdo->prepare($query);
  $stmt->execute();
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

$query_actividades = "SELECT NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD";

// Ejecutar la consulta para obtener las actividades
$stmt_actividades = $pdo->prepare($query_actividades);
$stmt_actividades->execute();
$actividades = $stmt_actividades->fetchAll(PDO::FETCH_ASSOC);


$query_promedio = "
SELECT 
    p.PROYECTO AS nombre_proyecto,
    a.NOM_ACTIVIDAD AS nombre_actividad,
    AVG(c.CALIFICACION) AS promedio_nota,
    c.ID_PROYECTO,
    c.ID_ACTIVIDAD
FROM 
    CALIFICACION c
JOIN 
    PROYECTO p ON c.ID_PROYECTO = p.ID_PROYECTO
JOIN 
    ACTIVIDAD a ON c.ID_ACTIVIDAD = a.ID_ACTIVIDAD
GROUP BY 
    c.ID_PROYECTO, c.ID_ACTIVIDAD
ORDER BY 
    p.PROYECTO, a.NOM_ACTIVIDAD;
";


// Ejecutar la consulta
$stmt = $pdo->prepare($query_promedio);
$stmt->execute();
$notas_promedio = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crear una matriz de promedios de notas para que sea más fácil de usar en el HTML
$proyectos_promedios = [];
foreach ($notas_promedio as $nota) {
  $proyectos_promedios[$nota['ID_PROYECTO']][$nota['ID_ACTIVIDAD']] = $nota['promedio_nota'];
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

        </li><!-- End Messages Nav -->

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
          include 'bdd/database.php'; // Asegúrate de tener una conexión PDO en este archivo

          $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD"; // Ajusta el nombre de la tabla y los campos según tu base de datos
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
      </li><!-- End Evaluación Nav -->

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
                      <td><?= htmlspecialchars($row['proyecto']); ?></td>

                      <!-- Mostrar el promedio de calificaciones para cada actividad de ese proyecto -->
                      <?php foreach ($actividades as $actividad): ?>
                        <td>
                          <?php
                          $id_proyecto = $row['ID_PROYECTO'];  // Asegúrate de que $row contenga ID_PROYECTO
                          $id_actividad = $actividad['ID_ACTIVIDAD'];  // Asegúrate de que $actividad contenga ID_ACTIVIDAD

                          if (isset($proyectos_promedios[$id_proyecto][$id_actividad])) {
                            // Mostrar el promedio de la calificación si existe
                            echo htmlspecialchars(number_format($proyectos_promedios[$id_proyecto][$id_actividad], 2));
                          } else {
                            // Si no hay calificación, mostrar 'N/A'
                            echo 'N/A';
                          }
                          ?>
                        </td>
                      <?php endforeach; ?>

                      <!-- Nota final del proyecto -->
                      <td><?= htmlspecialchars(number_format($row['nota_final'], 2)); ?></td>
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
        // Obtener las notas de las dos filas
        var notaA = parseFloat(rowA.querySelector('td:nth-child(2)').innerText);
        var notaB = parseFloat(rowB.querySelector('td:nth-child(2)').innerText);

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