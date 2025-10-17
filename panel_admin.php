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

<body>

  <!-- Conexión a la base de datos (PDO) -->
  <?php

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
        <a class="nav-link" href="panel_admin.php">
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
      </li><!-- End Activities Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
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
          include 'bdd/database.php'; // Asegúrate de tener una conexión PDO en este archivo

          $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD"; // Ajusta el nombre de la tabla y los campos según tu base de datos
          $stmt = $pdo->prepare($sql);
          $stmt->execute();
          $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if ($actividades) {
            foreach ($actividades as $actividad) {
              echo '<li>';
              echo '<a href="calificarAdmin.php?id=' . $actividad["ID_ACTIVIDAD"] . '">';
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
      </li><!-- End F.A.Q Page Nav -->
    </ul>

  </aside><!-- End Sidebar-->

  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Panel administrador</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_admin.php">Inicio</a></li>
          <li class="breadcrumb-item active">Panel administrador</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

        <!-- Administrar usuarios -->
        <div class="col-4">
          <a href="addusers.php" class="nav-link">
            <div class="card info-card sales-card">
              <div class="card-body">
                <h5 class="card-title">Administrar usuarios</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-person"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Usuarios</span>
                    <span class="text-muted small pt-2 ps-1">| Agregar</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <!-- Administrar proyectos -->
        <div class="col-4">
          <a href="addprojects.php" class="nav-link">
            <div class="card info-card revenue-card">
              <div class="card-body">
                <h5 class="card-title">Administrar proyectos</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-kanban"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Proyectos</span>
                    <span class="text-muted small pt-2 ps-1">| Agregar</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>


        <!-- Administrar premios -->
        <div class="col-4">
          <a href="addfunds.php" class="nav-link">
            <div class="card info-card sales-card">
              <div class="card-body">
                <h5 class="card-title">Administrar fondos</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-trophy"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Fondos</span>
                    <span class="text-muted small pt-2 ps-1">| Agregar</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <!-- Administrar actividades -->
        <div class="col-4">
          <a href="addactivities.php" class="nav-link">
            <div class="card info-card revenue-card">
              <div class="card-body">
                <h5 class="card-title">Administrar actividades</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-clipboard-check"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Actividades</span>
                    <span class="text-muted small pt-2 ps-1">| Agregar</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <!-- Resumen Individual -->
        <div class="col-4">
          <a href="adminrindividual.php" class="nav-link">
            <div class="card info-card sales-card">
              <div class="card-body">
                <h5 class="card-title">Resumen Individual</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-person-lines-fill"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Resumen</span>
                    <span class="text-muted small pt-2 ps-1">| Individual</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <!-- Resumen Grupal -->
        <div class="col-4">
          <a href="adminiglobal.php" class="nav-link">
            <div class="card info-card revenue-card">
              <div class="card-body">
                <h5 class="card-title">Resumen Grupal</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-people"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Resumen</span>
                    <span class="text-muted small pt-2 ps-1">| Grupal</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

      <!-- Resumen Especifico -->
<div class="col-4">
  <a href="admindetallado.php" class="nav-link">
    <div class="card info-card revenue-card">
      <div class="card-body">
        <h5 class="card-title">Resumen Específico</h5>
        <div class="d-flex align-items-center">
          <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
            <i class="bi bi-clipboard-data"></i> <!-- Aquí está el ícono agregado -->
          </div>
          <div class="ps-3">
            <span class="text-success small pt-1 fw-bold">Resumen</span>
            <span class="text-muted small pt-2 ps-1">| Especifico</span>
          </div>
        </div>
      </div>
    </div>
  </a>
</div>
     

        <!-- Tarjeta 5: Perfil -->
        <div class="col-4">
          <a href="users-profileadmin.php" class="nav-link">
            <div class="card info-card sales-card">
              <div class="card-body">
                <h5 class="card-title">Perfil</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-person"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Perfil</span>
                    <span class="text-muted small pt-2 ps-1"> | Modificar perfil</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
        </div>

        <!-- Manual -->
        <div class="col-4">
          <a href="manual_admin.php" class="nav-link">
            <div class="card info-card revenue-card">
              <div class="card-body">
                <h5 class="card-title">Manual</h5>
                <div class="d-flex align-items-center">
                  <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                    <i class="bi bi-search"></i>
                  </div>
                  <div class="ps-3">
                    <span class="text-success small pt-1 fw-bold">Manual</span>
                    <span class="text-muted small pt-2 ps-1">| Administrador</span>
                  </div>
                </div>
              </div>
            </div>
          </a>
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


</body>

</html>