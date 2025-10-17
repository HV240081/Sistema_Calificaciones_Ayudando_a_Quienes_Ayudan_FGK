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

<!-- Custom Styles for Cards and Buttons -->
<style>
  /* Card styles */
  .card {
    margin-bottom: 30px;
    border: none;
    border-radius: 5px;
    box-shadow: 0px 0 30px rgba(1, 41, 112, 0.1);
    width: 100%;
  }

  .card-header,
  .card-footer {
    border-color: #ebeef4;
    background-color: #fff;
    color: #798eb3;
    padding: 15px;
  }

  .card-title {
    padding: 20px 0 15px 0;
    font-size: 18px;
    font-weight: 500;
    color: #012970;
    font-family: "Poppins", sans-serif;
  }

  .card-body {
    padding: 20px;
  }

  /* Responsive layout for cards */
  @media (min-width: 768px) {
    .card-deck {
      display: flex;
      justify-content: space-between;
    }

    .card-deck .card {
      flex: 1;
      margin-right: 20px;
    }

    .card-deck .card:last-child {
      margin-right: 0;
    }
  }

  /* Button styles */
  .action-btn {
    width: 100%;
    /* Full width */
    font-size: 16px;
    font-weight: 600;
    border-radius: 5px;
    padding: 10px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
  }

  .action-btn:hover {
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
  }

  .action-btn:focus {
    outline: none;
  }
</style>

<body>

  <!-- Conexión a la base de datos (PDO) -->
  <?php
  session_start(); // Inicia la sesión al principio del archivo PHP

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
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->


    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">

        </li><!-- End Messages Nav -->

        <li class="nav-item dropdown pe-3">

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
        <a class="nav-link" href="user-profilep.php">
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
      <h1>Perfil</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_juradop.php">Inicio</a></li>
          <li class="breadcrumb-item active">Perfil</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section profile">
      <div class="row">
        <!-- Cards Deck -->
        <div class="col-xl-12 card-deck">
          <!-- Primera Card -->
          <div class="card">
            <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
              <!-- Ícono de perfil -->
              <i class="bi bi-person" style="font-size: 48px; margin-bottom: 10px;"></i>
              <h6><?php echo $nombre_usuario ?></h6>
              <span><?php echo $rol ?></span>
            </div>
          </div>


          <!-- Segunda Card -->
          <div class="card">
            <div class="card-body pt-3">
              <!-- Bordered Tabs -->
              <ul class="nav nav-tabs nav-tabs-bordered">
                <li class="nav-item">
                  <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview">Acciones</button>
                </li>
              </ul>

              <div class="tab-content pt-2">
                <!-- Botones uno debajo del otro -->
                <div class="d-flex flex-column">
                  <!-- Botón para volver al panel -->
                  <button class="btn btn-primary action-btn" onclick="window.location.href='panel_juradop.php'">Volver al Panel</button>

                  <!-- Botón para rechazar con una función de confirmación -->
                  <button class="btn btn-secondary action-btn" onclick="rechazarAccion()">Cerrar Sesión</button>
                </div>
              </div><!-- End Profile Overview -->

              <!-- Script para manejar la acción del botón 'Rechazar' -->
              <script>
                function rechazarAccion() {
                  if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                    // Aquí puedes redirigir a otra página o realizar cualquier acción.
                    window.location.href = 'index.php';
                  }
                }
              </script>


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
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.js"></script>
  <script src="assets/vendor/jquery/jquery.min.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/quill/quill.min.js"></script>

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