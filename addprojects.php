<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
  header("Location: index.php");
  exit();
}

$servername = "localhost";
$username = "userproyect";
$password = "FGK202412345";
$dbname = "PROYECTO_ES";

// Crear la conexión MySQLi
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}

// Obtener el ID de usuario de la sesión
$id_usuario = $_SESSION['id_usuario'];

// Consulta para obtener nombre y rol del usuario
$sql = "SELECT U.NOMBRE, U.APELLIDO, R.ROL FROM USUARIO U JOIN ROL R ON U.ID_ROL = R.ID_ROL WHERE U.ID_USUARIO = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario); // "i" indica que el parámetro es un entero
$stmt->execute();
$result = $stmt->get_result();

// Verificar si se obtuvo un resultado
if ($row = $result->fetch_assoc()) {
  // Nombre y rol del usuario actual
  $nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
  $rol = $row['ROL'];
} else {
  // Manejar el caso en que no se encuentre el usuario
  $nombre_usuario = "Desconocido";
  $rol = "Desconocido";
}


// Lógica para guardar un nuevo proyecto
if (isset($_POST['add_project'])) {
  $projectName = $_POST['projectName'];
  $description = $_POST['description'];

  // Verificar si el proyecto ya existe
  $checkSql = "SELECT * FROM PROYECTO WHERE PROYECTO = '$projectName'";
  $checkResult = $conn->query($checkSql);

  if ($checkResult->num_rows > 0) {
    // Si ya existe un proyecto con el mismo nombre, muestra un mensaje de error
    $_SESSION['message'] = '¡Error! Ya existe un proyecto con el mismo nombre.';
    $_SESSION['alert-type'] = 'danger';
  } else {
    // Si no existe, insertar el nuevo proyecto
    $sql = "INSERT INTO PROYECTO (PROYECTO, DESCRIPCION) VALUES ('$projectName', '$description')";

    if ($conn->query($sql) === TRUE) {
      $_SESSION['message'] = '¡Proyecto agregado con éxito!';
      $_SESSION['alert-type'] = 'success';
    } else {
      $_SESSION['message'] = 'Error: ' . $conn->error;
      $_SESSION['alert-type'] = 'danger';
    }
  }

  header("Location: addprojects.php");
  exit();
}

// Lógica para actualizar un proyecto
if (isset($_POST['update_project'])) {
  $id = $_POST['id'];
  $projectName = $_POST['projectName'];
  $description = $_POST['description'];

  // Verificar si el proyecto ya existe para otro ID
  $checkSql = "SELECT * FROM PROYECTO WHERE PROYECTO = '$projectName' AND ID_PROYECTO != $id";
  $checkResult = $conn->query($checkSql);

  if ($checkResult->num_rows > 0) {
    // Si ya existe un proyecto con el mismo nombre para otro ID, muestra un mensaje de error
    $_SESSION['message'] = '¡Error! Ya existe otro proyecto con el mismo nombre.';
    $_SESSION['alert-type'] = 'danger';
  } else {
    // Si no existe, actualizar el proyecto
    $sql = "UPDATE PROYECTO SET PROYECTO='$projectName', DESCRIPCION='$description' WHERE ID_PROYECTO=$id";

    if ($conn->query($sql) === TRUE) {
      $_SESSION['message'] = '¡Proyecto actualizado con éxito!';
      $_SESSION['alert-type'] = 'warning';
    } else {
      $_SESSION['message'] = 'Error: ' . $conn->error;
      $_SESSION['alert-type'] = 'danger';
    }
  }

  header("Location: addprojects.php");
  exit();
}

// Lógica para eliminar un proyecto
if (isset($_GET['delete_id'])) {
  $id = $_GET['delete_id'];

  $sql = "DELETE FROM PROYECTO WHERE ID_PROYECTO=$id";

  if ($conn->query($sql) === TRUE) {
    $_SESSION['message'] = '¡Proyecto eliminado con éxito!';
    $_SESSION['alert-type'] = 'danger';
  } else {
    $_SESSION['message'] = 'Error: ' . $conn->error;
    $_SESSION['alert-type'] = 'danger';
  }

  header("Location: addprojects.php");
  exit();
}

// Lógica para obtener los datos del proyecto a editar
$editProject = null;
if (isset($_GET['edit_id'])) {
  $id = $_GET['edit_id'];
  $sql = "SELECT * FROM PROYECTO WHERE ID_PROYECTO=$id";
  $result = $conn->query($sql);
  $editProject = $result->fetch_assoc();
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
        <a class="nav-link collapsed" href="addusers.php">
          <i class="bi bi-person"></i>
          <span>Usuarios</span>
        </a>
      </li><!-- End Profile Page Nav -->

      <li class="nav-item">
        <a class="nav-link" href="addprojects.php">
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
      <h1>Administrar Proyectos</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="panel_admin.php">Inicio</a></li>
          <li class="breadcrumb-item active">Administrar Proyectos</li>
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

        <!-- Formulario de Agregar Proyecto -->
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title"><?php echo $editProject ? 'Editar Proyecto' : 'Agregar Proyecto'; ?></h5>

              <!-- Formulario de Agregar o Editar Proyecto -->
              <form method="POST" action="">
                <!-- Mostrar notificación si existe -->
                <?php if (isset($_SESSION['message'])): ?>
                  <div id="autoDismissAlert" class="alert alert-<?php echo $_SESSION['alert-type']; ?> alert-dismissible fade show" role="alert">
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

                <?php if ($editProject): ?>
                  <input type="hidden" name="id" value="<?php echo $editProject['ID_PROYECTO']; ?>">
                <?php endif; ?>

                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="projectName" class="form-label">Nombre del Proyecto</label>
                    <input type="text" class="form-control" id="projectName" name="projectName" value="<?php echo $editProject ? $editProject['PROYECTO'] : ''; ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label for="description" class="form-label">Descripción</label>
                    <input type="text" class="form-control" id="description" name="description" value="<?php echo $editProject ? $editProject['DESCRIPCION'] : ''; ?>" maxlength="100" required>
                  </div>
                </div>
                <?php if ($editProject): ?>
                  <button type="submit" name="update_project" class="btn btn-primary">Actualizar</button>
                <?php else: ?>
                  <button type="submit" name="add_project" class="btn btn-primary">Guardar</button>
                <?php endif; ?>
                <a href="addprojects.php" class="btn btn-secondary">Cancelar</a>
              </form>
            </div>
          </div>
        </div>
        <!-- End Formulario de Agregar Proyecto -->

        <!-- Tabla de Proyectos -->
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Lista de Proyectos</h5>
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Nombre del Proyecto</th>
                    <th>Descripción</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $sql = "SELECT ID_PROYECTO, PROYECTO, DESCRIPCION FROM PROYECTO";
                  $result = $conn->query($sql);

                  if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                      echo "<tr>";
                      echo "<td>" . $row["PROYECTO"] . "</td>";
                      echo "<td>" . $row["DESCRIPCION"] . "</td>";
                      echo "<td>
                            <a href='addprojects.php?edit_id=" . $row["ID_PROYECTO"] . "' class='btn btn-warning btn-sm'
' ><i class='fas fa-edit'></i></a>
                            <a href='addprojects.php?delete_id=" . $row["ID_PROYECTO"] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"¿Estás seguro de que deseas eliminar este proyecto?\")'><i class='fas fa-trash-alt'></i></a>
                          </td>";
                      echo "</tr>";
                    }
                  } else {
                    echo "<tr><td colspan='3'>No hay proyectos disponibles</td></tr>";
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <!-- End Tabla de Proyectos -->

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