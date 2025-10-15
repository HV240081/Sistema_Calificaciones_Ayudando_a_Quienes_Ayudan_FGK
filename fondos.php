<?php
// === CONEXIÓN Y CONFIGURACIÓN DE SESIÓN ===
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

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// === DATOS DEL USUARIO ===
$sql = "SELECT U.NOMBRE, U.APELLIDO, R.ROL 
        FROM USUARIO U 
        JOIN ROL R ON U.ID_ROL = R.ID_ROL 
        WHERE ID_USUARIO = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_usuario]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$nombre_usuario = $row['NOMBRE'] . ' ' . $row['APELLIDO'];
$rol = $row['ROL'];

// === PROCESAMIENTO DE FORMULARIOS ===
$success = false;
$deleted = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_fondos'])) {
    foreach ($_POST['fondos'] as $id_nfinal => $premios) {
        $stmt_delete = $pdo->prepare("DELETE FROM PREMIO_NFINAL WHERE ID_NFINAL = :id_nfinal");
        $stmt_delete->bindParam(':id_nfinal', $id_nfinal, PDO::PARAM_INT);
        $stmt_delete->execute();

        foreach ($premios as $id_premio) {
            $id_premio = intval($id_premio);
            if ($id_premio > 0) {
                $stmt_insert = $pdo->prepare("INSERT INTO PREMIO_NFINAL (ID_PREMIO, ID_NFINAL) VALUES (:id_premio, :id_nfinal)");
                $stmt_insert->bindParam(':id_premio', $id_premio, PDO::PARAM_INT);
                $stmt_insert->bindParam(':id_nfinal', $id_nfinal, PDO::PARAM_INT);
                $stmt_insert->execute();
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_fondos'])) {
    $pdo->exec("DELETE FROM PREMIO_NFINAL");
    header("Location: " . $_SERVER['PHP_SELF'] . "?deleted=1");
    exit();
}

if (isset($_GET['success']) && $_GET['success'] == 1) $success = true;
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) $deleted = true;

// === CONSULTAS DE DATOS ===
$stmt = $pdo->query("
    SELECT P.ID_PROYECTO, P.PROYECTO, N.NOTA_FINAL, N.ID_NFINAL
    FROM PROYECTO P
    JOIN NFINAL N ON P.ID_PROYECTO = N.ID_PROYECTO
");
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt_fondos = $pdo->query("SELECT ID_PREMIO, PREMIO FROM PREMIO");
$fondos = $stmt_fondos->fetchAll(PDO::FETCH_ASSOC);

$stmt_asignaciones = $pdo->query("SELECT ID_NFINAL, ID_PREMIO FROM PREMIO_NFINAL");
$asignaciones_previas = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);

$asignaciones_map = [];
foreach ($asignaciones_previas as $asignacion) {
    $asignaciones_map[$asignacion['ID_NFINAL']][] = $asignacion['ID_PREMIO'];
}

// Calcular cantidad máxima de fondos asignados por proyecto (para definir columnas)
$maxFondos = 1;
foreach ($asignaciones_map as $fondosAsignados) {
    if (count($fondosAsignados) > $maxFondos) {
        $maxFondos = count($fondosAsignados);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">

<title>Panel Jurado</title>
<link href="assets/img/favicon.png" rel="icon">
<link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

<!-- Google Fonts -->
<link href="https://fonts.gstatic.com" rel="preconnect">
<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Nunito:300,400,600,700|Poppins:300,400,500,600,700" rel="stylesheet">

<!-- Vendor CSS Files -->
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
<link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
<link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
<link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
<link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<!-- Template Main CSS File -->
<link href="assets/css/style.css" rel="stylesheet">

<style>
.fondo-row { display:flex; align-items:center; margin-bottom:5px; }
.fondo-row select { flex:1; }
.fondo-row button { margin-left:5px; }
.add-fondo-btn { margin-top:5px; }
</style>
</head>

<body>

<!-- ======= Header ======= -->
<header id="header" class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between">
        <a href="panel_juradop.php" class="logo d-flex align-items-center">
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
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="user-profilep.php">
                            <i class="bi bi-person"></i>
                            <span>Perfil</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="index.php">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>Cerrar Sesion</span>
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
            <a class="nav-link collapsed" href="panel_juradop.php">
                <i class="bi bi-grid"></i>
                <span>Panel Jurado</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#evaluacion-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-check-circle"></i><span>Evaluación</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="evaluacion-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                <?php
                $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if ($actividades) {
                    foreach ($actividades as $actividad) {
                        echo '<li><a href="calificarp.php?id='.$actividad["ID_ACTIVIDAD"].'">';
                        echo '<i class="bi bi-circle"></i><span>'.$actividad["NOM_ACTIVIDAD"].' ('.$actividad["PORCENTAJE"].'%)</span></a></li>';
                    }
                } else {
                    echo '<li><a href="#"><span>No hay actividades disponibles</span></a></li>';
                }
                ?>
            </ul>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
                <i class="bi bi-menu-button-wide"></i><span>Resultados</span><i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <ul id="components-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                <li><a href="resultadosindividualesp.php"><i class="bi bi-circle"></i><span>Resultados Individuales</span></a></li>
                <li><a href="resultadosdetalladosp.php"><i class="bi bi-circle"></i><span>Resultados Específicos</span></a></li>
                <li><a href="resultadosglobalesp.php"><i class="bi bi-circle"></i><span>Resultados Globales</span></a></li>
            </ul>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="fondos.php">
                <i class="bi bi-piggy-bank"></i>
                <span>Asignar Fondos</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="resultadosfondosp.php">
                <i class="bi bi-gem"></i>
                <span>Fondos asignados</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="user-profilep.php">
                <i class="bi bi-person"></i>
                <span>Perfil</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link collapsed" href="manual_juradop.php">
                <i class="bi bi-question-circle"></i>
                <span>Manual</span>
            </a>
        </li>
    </ul>
</aside>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Resultados Finales y Fondos</h1>
    </div>
    <section class="section dashboard">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Asignación de fondos | Resultados Finales</h5>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        ¡Beneficios asignados con éxito!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($deleted): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        ¡Beneficios eliminados con éxito!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="table-responsive">
                        <table id="tablaFondos" class="table table-bordered text-center align-middle">
                            <thead>
                                <tr id="encabezado">
                                    <th>Proyecto</th>
                                    <th>Nota Final</th>
                                    <?php for ($i = 1; $i <= $maxFondos; $i++): ?>
                                        <th>Beneficio <?php echo $i; ?></th>
                                    <?php endfor; ?>
                                    <th>Acción de Benefico</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $resultado): ?>
                                    <?php 
                                        $fondosAsignados = $asignaciones_map[$resultado['ID_NFINAL']] ?? [];
                                        $numActual = count($fondosAsignados);
                                    ?>
                                    <tr data-nfinal="<?php echo $resultado['ID_NFINAL']; ?>">
                                        <td><?php echo htmlspecialchars($resultado['PROYECTO']); ?></td>
                                        <td><?php echo htmlspecialchars($resultado['NOTA_FINAL']); ?></td>

                                        <?php for ($i = 0; $i < $maxFondos; $i++): ?>
                                            <td>
                                                <div class="fondo-row d-flex justify-content-center align-items-center gap-2 mb-2">
                                                    <select name="fondos[<?php echo $resultado['ID_NFINAL']; ?>][]" class="form-select w-auto">
                                                        <option value="">Seleccionar Beneficio</option>
                                                        <?php foreach ($fondos as $fondo): ?>
                                                            <option value="<?php echo $fondo['ID_PREMIO']; ?>" 
                                                                <?php echo ($i < $numActual && $fondosAsignados[$i] == $fondo['ID_PREMIO']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($fondo['PREMIO']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="button" class="btn btn-danger btn-sm remove-fondo">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endfor; ?>

                                        <td class="accion-fondo">
                                            <button type="button" class="btn btn-success btn-sm add-column-btn">
                                                <i class="fas fa-plus"></i> Agregar nuevo Beneficio
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" name="asignar_fondos" class="btn btn-primary">Guardar</button>
                        <button type="submit" name="eliminar_fondos" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar todos los fondos?')">Eliminar todos</button>
                        <a href="fondos.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<footer id="footer" class="footer">
    <div class="copyright">
        &copy; Copyright <strong><span>Ayudando a quienes ayudan</span></strong>. Todos los derechos reservados.
    </div>
</footer>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/js/main.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const tabla = document.getElementById("tablaFondos");
    const encabezado = document.getElementById("encabezado");

    // === FUNCIÓN PARA EVITAR FONDOS DUPLICADOS POR PROYECTO ===
    function actualizarOpciones(fila) {
        const selects = fila.querySelectorAll('select');
        const seleccionados = Array.from(selects)
            .map(s => s.value)
            .filter(v => v !== "");

        selects.forEach(select => {
            select.querySelectorAll('option').forEach(option => {
                if (option.value !== "" && seleccionados.includes(option.value) && option.value !== select.value) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        });
    }

    // === AGREGAR NUEVA COLUMNA DE FONDOS A TODOS LOS PROYECTOS ===
    document.addEventListener("click", function(e) {
        if (e.target.closest(".add-column-btn")) {
            agregarNuevaColumna();
        }

        // Eliminar fondo (botón )
        if (e.target.closest(".remove-fondo")) {
            const fila = e.target.closest("tr");
            e.target.closest(".fondo-row").querySelector("select").value = "";
            actualizarOpciones(fila);
        }
    });

    // === FUNCIÓN QUE AGREGA UNA NUEVA COLUMNA CON SELECTS A CADA FILA ===
    function agregarNuevaColumna() {
        const th = document.createElement("th");
        const numCols = encabezado.querySelectorAll("th").length - 3; // menos proyecto, nota, acción
        th.textContent = "Beneficio" + (numCols + 1);
        encabezado.insertBefore(th, encabezado.lastElementChild);

        const filas = tabla.querySelectorAll("tbody tr");
        filas.forEach(fila => {
            const idNFinal = fila.dataset.nfinal;
            const nuevaCelda = document.createElement("td");
            nuevaCelda.innerHTML = `
                <div class="fondo-row d-flex justify-content-center align-items-center gap-2 mb-2">
                    <select name="fondos[${idNFinal}][]" class="form-select w-auto">
                        <option value="">Seleccionar fondo</option>
                        <?php foreach ($fondos as $fondo): ?>
                            <option value="<?php echo $fondo['ID_PREMIO']; ?>"><?php echo htmlspecialchars($fondo['PREMIO']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-danger btn-sm remove-fondo"><i class="fas fa-trash"></i></button>
                </div>
            `;
            fila.insertBefore(nuevaCelda, fila.querySelector(".accion-fondo"));

            // Ligar evento change para evitar duplicados dentro del proyecto
            const select = nuevaCelda.querySelector('select');
            select.addEventListener('change', () => actualizarOpciones(fila));

            actualizarOpciones(fila); // Inicializa la validación
        });
    }

    // === ACTUALIZAR OPCIONES AL CAMBIAR SELECT EXISTENTE ===
    document.addEventListener('change', function(e){
        if(e.target.tagName === 'SELECT'){
            const fila = e.target.closest('tr');
            actualizarOpciones(fila);
        }
    });

    // Inicializar validaciones al cargar
    tabla.querySelectorAll('tbody tr').forEach(fila => actualizarOpciones(fila));
});

// Script para ocultar/mostrar el menú lateral 
document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('main').classList.toggle('active');
});
</script>

</body>
</html>