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

  <!-- Conexión a la base de datos (PDO) -->
  <?php

  session_start();
  if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../ index.php");
    exit();
  }

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

  $id_usuario = $_SESSION['id_usuario'];

  // Obtener el rol del usuario actual
  $sql = "SELECT U.NOMBRE, U.APELLIDO, R.ROL FROM USUARIO U JOIN ROL R ON U.ID_ROL = R.ID_ROL WHERE ID_USUARIO = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$id_usuario]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  // Nombre y rol del usuario actual
  $nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
  $rol = $row['ROL'];

  ?>

  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="panel_juradop.php" class="logo d-flex align-items-center">

        <img src="assets/img/logo.png" alt="" style="width: 100px; height: auto;">

        <img src="../assets/img/logo.png" alt="" style="width: 100px; height: auto;">

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
        <a class="nav-link" href="manual_juradop.php">
          <i class="bi bi-question-circle"></i>
          <span>Manual</span>
        </a>
      </li><!-- End F.A.Q Page Nav -->

    </ul>

  </aside><!-- End Sidebar-->

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Manual Jurado</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_juradop.php">Inicio</a></li>
          <li class="breadcrumb-item active">Manual Jurado</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

        <!-- Left side columns -->
        <div class="col-lg-12">
          <div class="row">
            <div class="container mt-4">
              <div class="row">
                <div class="col-md-12">
                  <!-- Tarjeta para la Previsualización del PDF -->
                  <div class="card">
                    <div class="card-body">
                      <h5 class="card-title">Manual de Jurado</h5>

                      <!-- Previsualización del PDF -->
                      <div class="embed-responsive embed-responsive-16by9">
                        <iframe src="manuales/manual_juradop.pdf" class="embed-responsive-item" width="100%"
                          height="500px"></iframe>
                      </div>

                      <!-- Botón de descarga -->
                      <div class="mt-3">
                        <a href="manuales/manual_juradop.pdf" download class="btn btn-primary" target="_blank">
                          <i class="bi bi-download"></i> Descargar PDF
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div><!-- End Left side columns -->

  </main><!-- End #main -->

    <!-- ======= Footer ======= -->
    <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservados.
    </div>
  </footer><!-- End Footer -->

  <!-- Vendor JS Files -->
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.js"></script>
  <script src="../assets/vendor/jquery/jquery.min.js"></script>
  <script src="../assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="../assets/vendor/quill/quill.min.js"></script>

  <!-- Template Main JS File -->
  <script src="../assets/js/main.js"></script>
  <script>
  document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('main').classList.toggle('active');
  });
</script>

</body>

</html>