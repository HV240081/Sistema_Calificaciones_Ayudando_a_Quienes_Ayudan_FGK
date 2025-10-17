<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

require 'bdd/database.php';

$id_usuario = $_SESSION['id_usuario'];

$id_usuario_modificar = isset($_GET['modificar']) ? (int) $_GET['modificar'] : null;

// Obtener los detalles del usuario a modificar
if ($id_usuario_modificar) {
  $sql = "SELECT U.ID_USUARIO, U.NOMBRE, U.APELLIDO, LOWER(CONCAT(U.NOMBRE, '_', U.APELLIDO)) AS USERNAME, U.CONTRASENA, R.ID_ROL 
              FROM USUARIO U 
              JOIN ROL R ON U.ID_ROL = R.ID_ROL 
              WHERE ID_USUARIO = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$id_usuario_modificar]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
  $usuario = null;
}

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

// Procesar la solicitud POST para agregar o actualizar un usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $id_usuario_post = $_POST['id_usuario'];
  $firstName = $_POST['firstName'];
  $lastName = $_POST['lastName'];
  $username = strtolower($firstName . '_' . $lastName);
  $password = $_POST['password'] ?? 'proyecto_'; // Contraseña predefinida al crear
  $role = $_POST['role'];


  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Error: Token CSRF inválido.");
  }

  // Validar que el nombre de usuario no esté en uso
  $query = "SELECT COUNT(*) FROM USUARIO WHERE LOWER(CONCAT(NOMBRE, '_', APELLIDO)) = LOWER(?) AND ID_USUARIO != ?";
  $stmt = $pdo->prepare($query);
  $stmt->execute([$username, $id_usuario_post]);
  if ($stmt->fetchColumn() > 0) {
    $_SESSION['message'] = '¡Error! Ya existe un usuario con el mismo nombre';
    $_SESSION['alert-type'] = 'danger';
  } else {
    // Actualizar usuario
    if ($id_usuario_post) {
      $query = "UPDATE USUARIO SET NOMBRE = ?, APELLIDO = ?, CONTRASENA = ?, ID_ROL = ? WHERE ID_USUARIO = ?";
      $stmt = $pdo->prepare($query);
      if ($stmt->execute([$firstName, $lastName, $password, $role, $id_usuario_post])) {
        $_SESSION['message'] = '¡Usuario actualizado con éxito!';
        $_SESSION['alert-type'] = 'warning';
      } else {
        $_SESSION['message'] = 'Error: ' . $stmt->errorInfo()[2];
        $_SESSION['alert-type'] = 'danger';
      }
    } else {
      // Agregar nuevo usuario
      $query = "INSERT INTO USUARIO (NOMBRE, APELLIDO, CONTRASENA, TEMPORAL, ID_ROL) VALUES (?, ?, ?, 1, ?)";
      $stmt = $pdo->prepare($query);
      if ($stmt->execute([$firstName, $lastName, $password, $role])) {
        $_SESSION['message'] = '¡Usuario agregado con éxito!';
        $_SESSION['alert-type'] = 'success';
      } else {
        $_SESSION['message'] = 'Error: ' . $stmt->errorInfo()[2];
        $_SESSION['alert-type'] = 'danger';
      }
    }
  }
}

// Eliminar usuario
if (isset($_GET['delete'])) {
  $id_usuario_delete = $_GET['delete'];
  $query = "DELETE FROM USUARIO WHERE ID_USUARIO = ?";
  $stmt = $pdo->prepare($query);
  if ($stmt->execute([$id_usuario_delete])) {
    $_SESSION['message'] = '¡Usuario eliminado con éxito!';
    $_SESSION['alert-type'] = 'danger';
  } else {
    $_SESSION['message'] = 'Error: ' . $stmt->errorInfo()[2];
    $_SESSION['alert-type'] = 'danger';
  }
}

// Obtener todos los usuarios
$query = "SELECT U.ID_USUARIO, U.NOMBRE, U.APELLIDO, LOWER(CONCAT(U.NOMBRE, '_', U.APELLIDO)) AS USERNAME, U.CONTRASENA, R.ROL 
              FROM USUARIO U 
              JOIN ROL R ON U.ID_ROL = R.ID_ROL";
$stmt = $pdo->query($query);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdo = null;
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

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
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $nombre_usuario; ?></span>
          </a><!-- End Profile Iamge Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6><?php echo $nombre_usuario; ?></h6>
              <span><?php echo $rol; ?></span>
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
    <a class="nav-link collapsed" href="panel_admin.php">
      <i class="bi bi-grid"></i>
      <span>Panel Administrador</span>
    </a>
  </li><!-- End Dashboard Nav -->

  <li class="nav-item">
    <a class="nav-link" href="addusers.php">
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
      <h1>Administrar Usuarios</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_admin.php">Inicio</a></li>
          <li class="breadcrumb-item active">Administrar Usuarios</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

        <!-- Formulario de Agregar Usuario -->
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Agregar Usuario</h5>
              <form id="userForm" action="addusers.php" method="post">

                <!--Alertas de validaciones-->
                <?php if (isset($_SESSION['message'])): ?>
                  <div id="autoDismissAlert"
                    class="alert alert-<?php echo $_SESSION['alert-type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                  <?php
                  // Limpiar el mensaje después de mostrarlo
                  unset($_SESSION['message']);
                  unset($_SESSION['alert-type']);
                  ?>
                  <!-- JavaScript para hacer que la alerta desaparezca después de unos segundos -->
                  <script>
                    setTimeout(function() {
                      var alertElement = document.getElementById('autoDismissAlert');
                      if (alertElement) {
                        var bsAlert = new bootstrap.Alert(alertElement);
                        bsAlert.close();
                      }
                    }, 3000); // 3000 milisegundos = 3 segundos
                  </script>
                <?php endif; ?>

                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id_usuario" value="<?php echo $usuario ? $usuario['ID_USUARIO'] : ''; ?>">

                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="firstName" class="form-label">Nombre</label>
                    <input type="text" class="form-control" id="firstName" name="firstName"
                      value="<?php echo $usuario ? $usuario['NOMBRE'] : ''; ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label for="lastName" class="form-label">Apellido</label>
                    <input type="text" class="form-control" id="lastName" name="lastName"
                      value="<?php echo $usuario ? $usuario['APELLIDO'] : ''; ?>" required>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="username" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" id="username" name="username"
                      value="<?php echo $usuario ? $usuario['USERNAME'] : ''; ?>" required readonly>
                  </div>
                  <div class="col-md-6">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="text" class="form-control" id="password" name="password"
                      value="<?php echo $usuario ? $usuario['CONTRASENA'] : 'proyecto_'; ?>" required readonly>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-12">
                    <label for="role" class="form-label">Categoría</label>
                    <select id="role" name="role" class="form-select" required>
                      <option value="" disabled>Selecciona una categoría</option>
                      <option value="3" <?php echo $usuario && $usuario['ID_ROL'] == 3 ? 'selected' : ''; ?>>Jurado principal
                      </option>
                      <option value="2" <?php echo $usuario && $usuario['ID_ROL'] == 2 ? 'selected' : ''; ?>>Jurado
                      </option>
                      <option value="1" <?php echo $usuario && $usuario['ID_ROL'] == 1 ? 'selected' : ''; ?>>Administrador
                      </option>
                    </select>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="addusers.php" class="btn btn-secondary">Cancelar</a>
              </form>
            </div>
          </div>
        </div>
        <!-- End Formulario de Agregar Usuario -->

        <!-- Tabla de Usuarios -->
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Usuarios Registrados</h5>
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Nombre de Usuario</th>
                    <th>Contraseña</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($usuario['NOMBRE']); ?></td>
                      <td><?php echo htmlspecialchars($usuario['APELLIDO']); ?></td>
                      <td><?php echo htmlspecialchars($usuario['USERNAME']); ?></td>
                      <td><?php echo htmlspecialchars($usuario['CONTRASENA']) ?></td>
                      <td><?php echo htmlspecialchars($usuario['ROL']); ?></td>
                      <td>
                        <a href="addusers.php?modificar=<?php echo htmlspecialchars($usuario['ID_USUARIO']); ?>"
                          class="btn btn-warning btn-sm"><i class='fas fa-edit'></i></a>
                        <a href="addusers.php?delete=<?php echo htmlspecialchars($usuario['ID_USUARIO']); ?>"
                          class="btn btn-danger btn-sm"
                          onclick="return confirm('¿Estás seguro de eliminar este usuario?')"><i
                            class='fas fa-trash-alt'></i></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- End Tabla de Usuarios -->

      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservador
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