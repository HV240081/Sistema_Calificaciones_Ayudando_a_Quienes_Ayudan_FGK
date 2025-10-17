<?php
// Conectar a la base de datos
require 'bdd/database.php';

// Verificar que la sesión está iniciada y contiene un ID de usuario
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener el nombre y rol del usuario actual
$sql = "SELECT U.NOMBRE, U.APELLIDO, R.ROL FROM USUARIO U JOIN ROL R ON U.ID_ROL = R.ID_ROL WHERE U.ID_USUARIO = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);



if ($row) {
    $nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
    $rol = $row['ROL'];
} else {
    $nombre_usuario = 'Invitado';
    $rol = 'Desconocido';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}





// Manejar la inserción de la actividad
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activityId = $_POST['activityId'] ?? null;
    $activityName = $_POST['activityName'];
    $activityDescription = $_POST['activityDescription'];
    $activityPercentage = $_POST['activityPercentage'];
    $permissions = $_POST['permissions'];
    $criteriaNames = $_POST['criteriaName'] ?? [];
    $criteriaDescriptions = $_POST['criteriaDescription'] ?? [];
    $criteriaPercentages = $_POST['criteriaPercentage'] ?? [];

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error: Token CSRF inválido.");
    }

    // Validar la suma de los porcentajes de los criterios
    $totalCriteriaPercentage = 0;
    foreach ($criteriaPercentages as $percentage) {
        if (!empty($percentage)) {
            $totalCriteriaPercentage += $percentage;
        }
    }

    // Validar la suma de porcentajes de todas las actividades
    $totalActivitiesPercentage = 0;
    $stmtActivities = $pdo->query("SELECT PORCENTAJE FROM ACTIVIDAD");
    while ($row = $stmtActivities->fetch(PDO::FETCH_ASSOC)) {
        $totalActivitiesPercentage += $row['PORCENTAJE'];
    }

    if ($activityId) {
        // Si estás editando, resta el porcentaje actual de la actividad antes de sumar el nuevo porcentaje
        $stmt = $pdo->prepare("SELECT PORCENTAJE FROM ACTIVIDAD WHERE ID_ACTIVIDAD = ?");
        $stmt->execute([$activityId]);
        $currentActivityPercentage = $stmt->fetchColumn();
        $totalActivitiesPercentage -= $currentActivityPercentage; // Resta el porcentaje actual
    }

    // Verificar que el porcentaje total de actividades no exceda el 100%
    if ($totalActivitiesPercentage + $activityPercentage > 100) {
        $_SESSION['message'] = "La suma de los porcentajes de todas las actividades no puede exceder el 100%.";
        $_SESSION['alert-type'] = 'danger';
    }
    // Verificar que el porcentaje total de criterios esté entre 99.9% y 100.1%
    elseif (abs($totalCriteriaPercentage - 100) > 0.1) {
        $_SESSION['message'] = "La suma de los porcentajes de los criterios debe ser 100% (con un margen de error de 0.1%).";
        $_SESSION['alert-type'] = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            if ($activityId) {
                // Actualizar la actividad
                $stmt = $pdo->prepare("UPDATE ACTIVIDAD SET NOM_ACTIVIDAD = ?, DESCRIPCION = ?, PORCENTAJE = ?, ID_PERMISO = ? WHERE ID_ACTIVIDAD = ?");
                $stmt->execute([$activityName, $activityDescription, $activityPercentage, $permissions, $activityId]);

                // Eliminar los criterios existentes para esta actividad
                $stmt = $pdo->prepare("DELETE FROM CRITERIO WHERE ID_ACTIVIDAD = ?");
                $stmt->execute([$activityId]);

                // Mensaje de éxito para actualización
                $_SESSION['message'] = "¡Actividad y criterios actualizados con éxito!";
                $_SESSION['alert-type'] = 'warning';
            } else {
                // Insertar una nueva actividad
                $stmt = $pdo->prepare("INSERT INTO ACTIVIDAD (NOM_ACTIVIDAD, DESCRIPCION, PORCENTAJE, ID_PERMISO) VALUES (?, ?, ?, ?)");
                $stmt->execute([$activityName, $activityDescription, $activityPercentage, $permissions]);

                // Obtener el ID de la actividad recién creada
                $activityId = $pdo->lastInsertId();

                // Mensaje de éxito para inserción
                $_SESSION['message'] = "¡Actividad y criterios agregado con éxito!";
                $_SESSION['alert-type'] = 'success';
            }

            // Insertar los nuevos criterios
            foreach ($criteriaNames as $index => $name) {
                if (!empty($name) && !empty($criteriaDescriptions[$index]) && !empty($criteriaPercentages[$index])) {
                    $stmt = $pdo->prepare("INSERT INTO CRITERIO (ID_ACTIVIDAD, CRITERIO, DESCRIPCION, PORCENTAJE) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$activityId, $name, $criteriaDescriptions[$index], $criteriaPercentages[$index]]);
                }
            }

            $pdo->commit();
            header("Location: addactivities.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = "Error al guardar la actividad y criterios: " . $e->getMessage();
            $_SESSION['alert-type'] = 'danger';
        }
    }
}

// Obtener la lista de actividades y criterios
$activities = [];

try {
    $query = "SELECT * FROM ACTIVIDAD";
    $stmt = $pdo->query($query);
} catch (PDOException $e) {
    echo "Error en la consulta: " . $e->getMessage();
}



while ($row = $stmt->fetch()) {
    $activityId = $row['ID_ACTIVIDAD'];
    $stmtCriteria = $pdo->prepare("SELECT * FROM CRITERIO WHERE ID_ACTIVIDAD = ?");
    $stmtCriteria->execute([$activityId]);
    $criteria = $stmtCriteria->fetchAll();

    $activities[] = [
        'activity' => $row,
        'criteria' => $criteria
    ];
}


if (isset($_GET['delete'])) {
    $activityId = $_GET['delete'];
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM CRITERIO WHERE ID_ACTIVIDAD = ?");
        $stmt->execute([$activityId]);

        $stmt = $pdo->prepare("DELETE FROM ACTIVIDAD WHERE ID_ACTIVIDAD = ?");
        $stmt->execute([$activityId]);

        $pdo->commit();
        $_SESSION['message'] = "¡Actividad eliminada con éxito!";
        $_SESSION['alert-type'] = 'success';
        header("Location: addactivities.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error al eliminar la actividad: " . $e->getMessage();
        $_SESSION['alert-type'] = 'danger';
    }
}

$criteria = [];
if (isset($_GET['edit'])) {
    $activityId = $_GET['edit'];

    $stmt = $pdo->prepare("SELECT * FROM ACTIVIDAD WHERE ID_ACTIVIDAD = ?");
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtCriteria = $pdo->prepare("SELECT * FROM CRITERIO WHERE ID_ACTIVIDAD = ?");
    $stmtCriteria->execute([$activityId]);
    $criteria = $stmtCriteria->fetchAll(PDO::FETCH_ASSOC);
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
</head>

<body>

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
            <a class="nav-link" href="addactivities.php">
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
            <h1>Administrar Actividades</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="panel_admin.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Administrar Actividades</li>
                </ol>
            </nav>
        </div>
        <section class="section dashboard">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">

                            <?php if (isset($_GET['edit']) && !empty($criteria)): ?>
                                <h5 class="card-title">Editar Actividad</h5>
                            <?php else: ?>
                                <h5 class="card-title">Agregar Actividad</h5>
                            <?php endif; ?>

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

                            <form method="post" id="activityForm" action="addactivities.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="activityId"
                                    value="<?= htmlspecialchars($activity['ID_ACTIVIDAD'] ?? '') ?>">
                                <!-- Información de la actividad -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="activityName" class="form-label">Nombre de la actividad</label>
                                        <input type="text" class="form-control" id="activityName" name="activityName"
                                            value="<?= htmlspecialchars($activity['NOM_ACTIVIDAD'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="activityDescription" class="form-label">Descripción</label>
                                        <input type="text" class="form-control" id="activityDescription"
                                            name="activityDescription"
                                            value="<?= htmlspecialchars($activity['DESCRIPCION'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="activityPercentage" class="form-label">Porcentaje</label>
                                        <input type="number" class="form-control" id="activityPercentage"
                                            name="activityPercentage"
                                            value="<?= htmlspecialchars($activity['PORCENTAJE'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="permissions" class="form-label">Permisos</label>
                                        <select class="form-select" id="permissions" name="permissions" required>
                                            <option value="1" <?= ($activity['ID_PERMISO'] ?? '') == '1' ? 'selected' : '' ?>>Jurado</option>
                                            <option value="2" <?= ($activity['ID_PERMISO'] ?? '') == '2' ? 'selected' : '' ?>>Administrador</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- Contenedor de criterios -->
                                <div id="formulariocriterio"
                                    class="<?= isset($_GET['edit']) && !empty($criteria) ? '' : 'hidden' ?>">
                                    <h5><?= isset($_GET['edit']) && !empty($criteria) ? 'Actualizar Criterios' : 'Agregar Criterios' ?>
                                    </h5>
                                    <div id="criteriaContainer">
                                        <?php foreach ($criteria as $criterion): ?>
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="criteriaName[]"
                                                        value="<?= htmlspecialchars($criterion['CRITERIO']) ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="criteriaDescription[]"
                                                        value="<?= htmlspecialchars($criterion['DESCRIPCION']) ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="number" class="form-control" name="criteriaPercentage[]"
                                                        value="<?= htmlspecialchars($criterion['PORCENTAJE']) ?>" step="0.01">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-secondary" id="addCriteriaBtn">Agregar Criterio</button>
                                </div>
                                <!-- Botón para guardar o actualizar -->
                                <div class="mt-3">
                                    <?php if (isset($_GET['edit']) && !empty($criteria)): ?>
                                        <button type="submit" class="btn btn-primary">Actualizar Actividad</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-primary">Guardar</button>
                                    <?php endif; ?>
                                    <a href="addactivities.php" class="btn btn-secondary">Cancelar</a>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Lista de Actividades</h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Porcentaje</th>
                                        <th>Permisos</th>
                                        <th>Criterios</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($activity['activity']['NOM_ACTIVIDAD']) ?></td>
                                            <td><?= htmlspecialchars($activity['activity']['DESCRIPCION']) ?></td>
                                            <td><?= htmlspecialchars($activity['activity']['PORCENTAJE']) ?></td>
                                            <td><?= htmlspecialchars($activity['activity']['ID_PERMISO']) ?></td>
                                            <td>

                                                <?php foreach ($activity['criteria'] as $criterion): ?>
                                                    <?= htmlspecialchars($criterion['CRITERIO']) ?> -
                                                    (<?= htmlspecialchars($criterion['PORCENTAJE']) ?>%)
                                                    <br>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <a href="addactivities.php?edit=<?= htmlspecialchars($activity['activity']['ID_ACTIVIDAD']) ?>"
                                                    class="btn btn-warning btn-sm">
                                                    <i class='fas fa-edit'></i>
                                                </a>
                                                <a href="addactivities.php?delete=<?= htmlspecialchars($activity['activity']['ID_ACTIVIDAD']) ?>"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('¿Estás seguro de eliminar esta actividad?')">
                                                    <i class='fas fa-trash-alt'></i>
                                                </a>
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
    </main>

      <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservados.
    </div>
  </footer><!-- End Footer -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const criteriaContainer = document.getElementById('formulariocriterio');
            const addCriteriaBtn = document.getElementById('addCriteriaBtn');
            const activityForm = document.getElementById('activityForm');



            if (criteriaContainer && addCriteriaBtn && activityForm) {
                // Mostrar automáticamente el contenedor si estamos en modo edición
                if (criteriaContainer.querySelectorAll('input').length > 0) {
                    criteriaContainer.classList.remove('hidden');
                }

                // Agregar criterio al hacer clic en el botón
                addCriteriaBtn.addEventListener('click', function() {
                    const newCriteria = `
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="criteriaName[]" placeholder="Nombre del criterio">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="criteriaDescription[]" placeholder="Descripción">
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control criteria-percentage" name="criteriaPercentage[]" placeholder="Porcentaje" step="0.01">
                    </div>
                </div>`;

                    // Agregar el nuevo criterio al contenedor
                    document.getElementById('criteriaContainer').insertAdjacentHTML('beforeend', newCriteria);

                    // Mostrar el contenedor de criterios si estaba oculto
                    criteriaContainer.classList.remove('hidden');
                });

                // Validar el total de porcentajes antes de enviar el formulario
                activityForm.addEventListener('submit', function(event) {
                    if (!validateTotalPercentage()) {
                        event.preventDefault(); // Prevenir el envío del formulario si el total no es 100%
                    }
                });
            }
        });
    </script>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
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