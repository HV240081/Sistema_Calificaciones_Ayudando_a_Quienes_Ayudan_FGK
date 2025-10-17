<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Panel Administrador</title>

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
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        /* Alinear los botones a la izquierda */
        .text-center {
            text-align: left !important;
        }
    </style>

</head>

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

    // Generar token CSRF
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Mensajes de alerta
    $alertMessage = '';
    $alertType = '';

    // Procesar eliminación de un fondo
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];

        // Eliminar fondo de la base de datos
        $sql = "DELETE FROM PREMIO WHERE ID_PREMIO = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            $_SESSION['alertMessage'] = "¡Fondo eliminado con éxito!";
            $_SESSION['alertType'] = 'danger';
        } else {
            $_SESSION['alertMessage'] = "Error al eliminar el fondo.";
            $_SESSION['alertType'] = 'danger';
        }

        // Redirigir para limpiar el formulario y mostrar el mensaje
        header("Location: addfunds.php");
        exit();
    }

    // Procesar la inserción o actualización del formulario
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['update'])) {
            // Actualizar un fondo existente
            $id = $_POST['id'];
            $premio = $_POST['awardTitle'];
            $descripcion = $_POST['awardDescription'];

            $sql = "UPDATE PREMIO SET PREMIO = :premio, DESCRIPCION = :descripcion WHERE ID_PREMIO = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':premio', $premio);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':id', $id);

            if ($stmt->execute()) {
                $_SESSION['alertMessage'] = "¡Fondo actualizado con éxito!";
                $_SESSION['alertType'] = 'warning';
            } else {
                $_SESSION['alertMessage'] = "Error al actualizar el fondo.";
                $_SESSION['alertType'] = 'danger';
            }

            // Redirigir para limpiar el formulario y mostrar el mensaje
            header("Location: addfunds.php");
            exit();
        } elseif (isset($_POST['save'])) {
            // Verificar si el fondo ya existe
            $premio = $_POST['awardTitle'];
            $descripcion = $_POST['awardDescription'];

            $sql = "SELECT * FROM PREMIO WHERE PREMIO = :premio";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':premio', $premio);
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $_SESSION['alertMessage'] = "¡Error! Ya existe este fondo.";
                $_SESSION['alertType'] = 'danger';
            } else {
                // Insertar un nuevo fondo
                $sql = "INSERT INTO PREMIO (PREMIO, DESCRIPCION) VALUES (:premio, :descripcion)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':premio', $premio);
                $stmt->bindParam(':descripcion', $descripcion);

                if ($stmt->execute()) {
                    $_SESSION['alertMessage'] = "¡Fondo agregado con éxito!";
                    $_SESSION['alertType'] = 'success';
                } else {
                    $_SESSION['alertMessage'] = "Error al agregar el fondo.";
                    $_SESSION['alertType'] = 'danger';
                }
            }

            // Redirigir para limpiar el formulario y mostrar el mensaje
            header("Location: addfunds.php");
            exit();
        }
    }

    // Mostrar alertas
    if (isset($_SESSION['alertMessage'])) {
        $alertMessage = $_SESSION['alertMessage'];
        $alertType = $_SESSION['alertType'];
        unset($_SESSION['alertMessage']);
        unset($_SESSION['alertType']);
    }

    // Obtener premios para mostrarlos en la tabla
    $sql = "SELECT * FROM PREMIO";
    $stmt = $pdo->query($sql);
    $premios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cargar da tos de un fondo para editar
    $editData = [];
    if (isset($_GET['edit'])) {
        $id = $_GET['edit'];
        $sql = "SELECT * FROM PREMIO WHERE ID_PREMIO = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    ?>

    <!-- Barra de Navegación -->
    <header id="header" class="header fixed-top d-flex align-items-center">
        <div class="d-flex align-items-center justify-content-between">
            <a href="panel_admin.php" class="logo d-flex align-items-center">
                <img src="assets/img/logo.png" alt="" style="width: 100px; height: auto;">
            </a>
            <i class="bi bi-list toggle-sidebar-btn"></i>
        </div>

        <nav class="header-nav ms-auto">
            <ul class="d-flex align-items-center">
                <li class="nav-item dropdown pe-3">
                    <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                        <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $nombre_usuario ?></span>
                    </a>
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
                    </ul>
                </li>
            </ul>
        </nav>
    </header>

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
                <a class="nav-link" href="addfunds.php">
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

    <!-- Formulario y Tabla -->
    <main id="main" class="main">
        <div class="pagetitle">
            <h1>Administrar Fondos</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="panel_admin.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Administrar Fondos</li>
                </ol>
            </nav>
        </div>

        <section class="section dashboard">
            <div class="row">
                <!-- Formulario de Agregar/Editar Fondo -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo isset($editData['ID_PREMIO']) ? 'Editar Fondo' : 'Agregar Fondo'; ?></h5>

                            <!-- Mensaje de Alerta -->
                            <?php if ($alertMessage): ?>
                                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert" id="alertMessage">
                                    <?php echo $alertMessage; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <?php if (isset($editData['ID_PREMIO'])): ?>
                                    <input type="hidden" name="id" value="<?php echo $editData['ID_PREMIO']; ?>">
                                <?php endif; ?>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="awardTitle" class="form-label">Nombre del fondo</label>
                                        <input type="text" class="form-control" name="awardTitle" id="awardTitle" value="<?php echo isset($editData['PREMIO']) ? $editData['PREMIO'] : ''; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="awardDescription" class="form-label">Descripción</label>
                                        <input type="text" class="form-control" name="awardDescription" id="awardDescription" value="<?php echo isset($editData['DESCRIPCION']) ? $editData['DESCRIPCION'] : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <button type="submit" name="<?php echo isset($editData['ID_PREMIO']) ? 'update' : 'save'; ?>" class="btn btn-primary">
                                        <?php echo isset($editData['ID_PREMIO']) ? 'Actualizar' : 'Guardar'; ?>
                                    </button>
                                    <a href="addfunds.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Fondos -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Lista de Fondos</h5>

                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th scope="col">Nombre del fondo</th>
                                        <th scope="col">Descripción</th>
                                        <th scope="col">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($premios) > 0): ?>
                                        <?php foreach ($premios as $premio): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($premio['PREMIO']); ?></td>
                                                <td><?php echo htmlspecialchars($premio['DESCRIPCION']); ?></td>
                                                <td>
                                                    <a href="addfunds.php?edit=<?php echo htmlspecialchars($premio['ID_PREMIO']); ?>" class="btn btn-sm btn-warning">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                    <a href="addfunds.php?delete=<?php echo htmlspecialchars($premio['ID_PREMIO']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este fondo?');">
                                                        <i class="fa fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No hay fondos disponibles</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>

            </div>
        </section>
    </main>

      <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservados.
    </div>
  </footer><!-- End Footer -->

    <!-- Scripts -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        // Ocultar la alerta después de 3 segundos
        setTimeout(function() {
            var alert = document.getElementById('alertMessage');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 3000);
    </script>
    <script>
  document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('main').classList.toggle('active');
  });
</script>

</body>

</html>