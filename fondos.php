<?php
// Conexión a la base de datos y otras configuraciones
$dsn = "mysql:host=localhost;dbname=PROYECTO_ES";
$username = "userproyect";
$password = "FGK202412345";

// Conexión a la base de datos
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
    exit();
}

// Iniciar sesión
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
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

// Consulta para obtener todos los proyectos y notas finales
try {
    $stmt = $pdo->query("
        SELECT P.ID_PROYECTO, P.PROYECTO, N.NOTA_FINAL 
        FROM PROYECTO P
        JOIN NFINAL N ON P.ID_PROYECTO = N.ID_PROYECTO
    ");
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error al obtener proyectos y notas: " . $e->getMessage();
}

// Consultar fondos disponibles
try {
    $stmt_fondos = $pdo->query("SELECT ID_PREMIO, PREMIO FROM PREMIO");
    $fondos = $stmt_fondos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error al obtener fondos: " . $e->getMessage();
}

// Consulta para verificar si ya hay asignaciones de fondos
try {
    $stmt_asignaciones = $pdo->query("
        SELECT ID_PROYECTO, ID_PREMIO
        FROM PREMIO_NFINAL
        JOIN NFINAL ON PREMIO_NFINAL.ID_NFINAL = NFINAL.ID_NFINAL
    ");
    $asignaciones_previas = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error al obtener asignaciones: " . $e->getMessage();
}

$success = false; // Añadimos una variable para saber si todo fue exitoso

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_fondos'])) {
    foreach ($_POST['proyecto'] as $id_proyecto => $id_premio) {
        $id_proyecto = intval($id_proyecto);
        $id_premio = intval($id_premio);

        // Asegúrate de que id_premio sea mayor que 0 para evitar inserciones vacías
        if ($id_premio > 0) {
            try {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO PREMIO_NFINAL (ID_PREMIO, ID_NFINAL)
                    VALUES (:id_premio, (
                        SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = :id_proyecto
                    ))
                ");
                $stmt_insert->bindParam(':id_premio', $id_premio, PDO::PARAM_INT);
                $stmt_insert->bindParam(':id_proyecto', $id_proyecto, PDO::PARAM_INT);
                $stmt_insert->execute();
                $success = true; // Si la ejecución es exitosa, establecemos $success a true
            } catch (PDOException $e) {
                echo "Error al asignar fondo al proyecto ID $id_proyecto: " . $e->getMessage();
            }
        }
    }

    // Actualizar asignaciones después de guardar
    if ($success) {
        try {
            $stmt_asignaciones = $pdo->query("
                SELECT ID_PROYECTO, ID_PREMIO
                FROM PREMIO_NFINAL
                JOIN NFINAL ON PREMIO_NFINAL.ID_NFINAL = NFINAL.ID_NFINAL
            ");
            $asignaciones_previas = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error al obtener asignaciones: " . $e->getMessage();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_fondos'])) {
    try {
        $stmt_delete = $pdo->prepare("DELETE FROM PREMIO_NFINAL WHERE ID_NFINAL IN (SELECT ID_NFINAL FROM NFINAL WHERE ID_PROYECTO = :id_proyecto)");

        foreach ($_POST['proyecto'] as $id_proyecto => $id_premio) {
            if ($id_premio > 0) {
                $stmt_delete->bindParam(':id_proyecto', $id_proyecto, PDO::PARAM_INT);
                $stmt_delete->execute();
            }
        }

        $deleted = true; // Si la ejecución es exitosa, establecemos $deleted a true
    } catch (PDOException $e) {
        echo "Error al eliminar fondos: " . $e->getMessage();
    }
}

// Consulta para obtener las asignaciones de fondos
$sql_asignaciones = "SELECT ID_PROYECTO, ID_PREMIO FROM PREMIO_NFINAL PN 
                     JOIN NFINAL N ON PN.ID_NFINAL = N.ID_NFINAL";
$stmt_asignaciones = $pdo->query($sql_asignaciones);
$asignaciones = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);

// Crear un array para almacenar las asignaciones
$asignaciones_map = [];
foreach ($asignaciones as $asignacion) {
    $asignaciones_map[$asignacion['ID_PROYECTO']] = $asignacion['ID_PREMIO'];
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
                <a class="nav-link" href="fondos.php">
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
            <h1>Resultados Finales y Fondos</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="panel_juradop.php">Inicio</a></li>
                    <li class="breadcrumb-item active">Resultados Finales y Fondos</li>
                </ol>
            </nav>
        </div><!-- End Page Title -->

        <section class="section dashboard">
            <div class="row">
                <div class="col-lg-12">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title"> Asignacion de fondos<span> | Resultados Finales</span></h5>
                                    <?php if ($success): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert" id="success-alert">
                                            ¡Fondos asignados con éxito!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                        <script>
                                            setTimeout(function() {
                                                var alertElement = document.getElementById('success-alert');
                                                if (alertElement) {
                                                    alertElement.style.display = 'none';
                                                }
                                            }, 3000); // El alert desaparecerá después de 3 segundos
                                        </script>
                                    <?php endif; ?>
                                    <?php if (isset($deleted) && $deleted): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert" id="success-alert">
                                            ¡Fondos eliminados con éxito!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                        <script>
                                            setTimeout(function() {
                                                window.location.href = 'fondos.php';
                                            }, 3000); // 3000 milisegundos = 3 segundos
                                        </script>
                                    <?php endif; ?>

                                    <form method="post">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Proyecto</th>
                                                    <th scope="col">
                                                        Nota Final
                                                        <button class="btn-modify" data-order="asc" type="button" onclick="ordenarNotas()" style="margin-left: 5px;">
                                                            <i class="fas fa-sort-amount-down"></i>
                                                        </button>
                                                    </th>
                                                    <th scope="col">Asignar Fondo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($resultados as $resultado): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($resultado['PROYECTO']); ?></td>
                                                        <td class="final-grade"><?php echo htmlspecialchars($resultado['NOTA_FINAL']); ?></td>
                                                        <td>
                                                            <div style="display: flex; align-items: center;">
                                                                <input type="hidden" name="proyecto[<?php echo $resultado['ID_PROYECTO']; ?>]" value="0">
                                                                <select name="proyecto[<?php echo $resultado['ID_PROYECTO']; ?>]"
                                                                    class="form-select fondo-select"
                                                                    aria-label="Seleccionar fondo"
                                                                    onchange="deshabilitarFondo(this)"
                                                                    <?php if (isset($asignaciones_map[$resultado['ID_PROYECTO']])) {
                                                                        echo 'disabled';
                                                                    } ?>>

                                                                    <option value="">Seleccionar fondo</option>

                                                                    <?php foreach ($fondos as $fondo): ?>
                                                                        <option value="<?php echo $fondo['ID_PREMIO']; ?>"
                                                                            <?php if (isset($asignaciones_map[$resultado['ID_PROYECTO']]) && $asignaciones_map[$resultado['ID_PROYECTO']] == $fondo['ID_PREMIO']) {
                                                                                echo 'selected'; // Mantener la selección
                                                                            } ?>>
                                                                            <?php echo htmlspecialchars($fondo['PREMIO']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>

                                                                <button class="btn btn-warning btn-sm" style="margin-left: 5px; display: none;" onclick="restoreSelection(this)" type="button">
                                                                    <i class='fas fa-edit'></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>

                                        </table>
                                        <div style="text-align: right;">
    <?php if (!empty($asignaciones_previas)): // Si ya hay asignaciones previas ?>
        <button type="submit" name="eliminar_fondos" class="btn btn-primary" onclick="return confirm('¿Estás seguro de eliminar esta asignación de fondos?');">
            Eliminar
        </button>
    <?php else: ?>
        <button type="submit" name="asignar_fondos" class="btn btn-primary">
            Guardar
        </button>
    <?php endif; ?>
    <a href="fondos.php" class="btn btn-secondary">Cancelar</a>
</div>

                                    </form>
                                </div>
                            </div>
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
    <script>
        // Función para deshabilitar visualmente el select cuando se elige una opción
        function deshabilitarFondo(selectElement) {
            if (selectElement.value !== "") {
                // Deshabilitar visualmente el select
                selectElement.disabled = true;

                // Mostrar el botón de editar
                const restoreButton = selectElement.nextElementSibling;
                restoreButton.style.display = 'inline-block';
            }
        }

        // Función para habilitar el select nuevamente
        function restoreSelection(button) {
            const selectElement = button.previousElementSibling;
            selectElement.disabled = false; // Habilitar el select
            button.style.display = 'none'; // Ocultar el botón de restaurar
        }

        // Antes de enviar el formulario, habilitar todos los selects
        document.querySelector("form").addEventListener("submit", function() {
            const selects = document.querySelectorAll(".fondo-select");
            selects.forEach(function(select) {
                select.disabled = false; // Asegurarse de que todos los selects estén habilitados antes de enviar
            });
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const selectElements = document.querySelectorAll(".fondo-select");

            // Función para deshabilitar las opciones seleccionadas en los otros selects
            function actualizarFondosDisponibles() {
                const selectedValues = Array.from(selectElements)
                    .map(select => select.value)
                    .filter(value => value !== ""); // Filtramos solo los select que tienen un valor seleccionado

                // Recorremos cada select para habilitar o deshabilitar opciones
                selectElements.forEach(select => {
                    const options = select.querySelectorAll("option");

                    options.forEach(option => {
                        if (selectedValues.includes(option.value) && option.value !== select.value) {
                            // Deshabilitar la opción si está seleccionada en otro select
                            option.disabled = true;
                        } else {
                            // Habilitar la opción si no está seleccionada
                            option.disabled = false;
                        }
                    });
                });
            }

            // Añadimos el evento change a cada select para actualizar los fondos disponibles al cambiar
            selectElements.forEach(select => {
                select.addEventListener("change", function() {
                    actualizarFondosDisponibles();
                });
            });

            // Llamamos a la función inicial para deshabilitar fondos ya seleccionados en caso de que existan selecciones iniciales
            actualizarFondosDisponibles();
        });

        document.querySelector("form").addEventListener("submit", function() {
            const selects = document.querySelectorAll(".fondo-select");
            selects.forEach(function(select) {
                select.disabled = false; // Asegúrate de que todos los selects estén habilitados antes de enviar
            });
        });
    </script>
    <script>
        document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main').classList.toggle('active');
        });
    </script>
</body>

</html>
