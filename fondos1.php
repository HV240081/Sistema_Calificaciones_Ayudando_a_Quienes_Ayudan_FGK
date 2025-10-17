<?php
$dsn = "mysql:host=localhost;dbname=mi_bd;charset=utf8";
$username = "root";
$password = "";

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
        SELECT P.ID_PROYECTO, P.PROYECTO, N.NOTA_FINAL, N.ID_NFINAL
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

// Consultar asignaciones previas
try {
    $stmt_asignaciones = $pdo->query("SELECT ID_NFINAL, ID_PREMIO FROM PREMIO_NFINAL");
    $asignaciones_previas = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error al obtener asignaciones previas: " . $e->getMessage();
}

// Mapear asignaciones por ID_NFINAL
$asignaciones_map = [];
foreach ($asignaciones_previas as $asignacion) {
    $asignaciones_map[$asignacion['ID_NFINAL']][] = $asignacion['ID_PREMIO'];
}

// Calcular cantidad máxima de fondos asignados por proyecto
$maxFondos = 1;
foreach ($asignaciones_map as $fondosAsignados) {
    if (count($fondosAsignados) > $maxFondos) {
        $maxFondos = count($fondosAsignados);
    }
}

$success = false;
$deleted = false;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['asignar_fondos'])) {
    foreach ($_POST['fondos'] as $id_nfinal => $premios) {
        // Eliminar asignaciones previas para este ID_NFINAL
        $stmt_delete = $pdo->prepare("DELETE FROM PREMIO_NFINAL WHERE ID_NFINAL = :id_nfinal");
        $stmt_delete->bindParam(':id_nfinal', $id_nfinal, PDO::PARAM_INT);
        $stmt_delete->execute();

        // Insertar las nuevas asignaciones
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
    $success = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_fondos'])) {
    try {
        $stmt_delete = $pdo->prepare("DELETE FROM PREMIO_NFINAL");
        $stmt_delete->execute();
        $deleted = true;
    } catch (PDOException $e) {
        echo "Error al eliminar fondos: " . $e->getMessage();
    }
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

    <style>
        .fondo-row { display: flex; align-items: center; margin-bottom: 5px; }
        .fondo-row select { flex: 1; margin-right: 5px; }
        .remove-fondo { margin-left: 5px; }
        .table-responsive { overflow-x: auto; }
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
        </div><!-- End Logo -->

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
                    include 'bdd/database.php';
                    $sql = "SELECT ID_ACTIVIDAD, NOM_ACTIVIDAD, PORCENTAJE FROM ACTIVIDAD";
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
                <ul id="components-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
                    <li>
                        <a href="resultadosindividualesp.php">
                            <i class="bi bi-circle"></i><span>Resultados Individuales</span>
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
                <a class="nav-link collapsed" href="users-profile.php">
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
                                    <h5 class="card-title">Asignación de fondos<span> | Resultados Finales</span></h5>
                                    
                                    <?php if ($success): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            ¡Beneficios asignados con éxito!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($deleted): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            ¡Beneficios eliminados con éxito!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                        <script>
                                            setTimeout(function() {
                                                window.location.href = 'fondos.php';
                                            }, 3000);
                                        </script>
                                    <?php endif; ?>

                                    <form method="post">
                                        <div class="table-responsive">
                                            <table id="tablaFondos" class="table table-bordered">
                                                <thead>
                                                    <tr id="encabezado">
                                                        <th>Proyecto</th>
                                                        <th>
                                                            Nota Final
                                                            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="ordenarNotas()" style="margin-left: 5px;">
                                                                <i class="fas fa-sort"></i>
                                                            </button>
                                                        </th>
                                                        <?php for ($i = 1; $i <= $maxFondos; $i++): ?>
                                                            <th>Beneficio <?php echo $i; ?></th>
                                                        <?php endfor; ?>
                                                        <th>Acción</th>
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
                                                            <td class="final-grade"><?php echo htmlspecialchars($resultado['NOTA_FINAL']); ?></td>
                                                            
                                                            <?php for ($i = 0; $i < $maxFondos; $i++): ?>
                                                                <td>
                                                                    <div class="fondo-row">
                                                                        <select name="fondos[<?php echo $resultado['ID_NFINAL']; ?>][]" class="form-select form-select-sm">
                                                                            <option value="">Seleccionar</option>
                                                                            <?php foreach ($fondos as $fondo): ?>
                                                                                <option value="<?php echo $fondo['ID_PREMIO']; ?>" 
                                                                                    <?php echo (isset($fondosAsignados[$i]) && $fondosAsignados[$i] == $fondo['ID_PREMIO']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($fondo['PREMIO']); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <?php if ($i >= $numActual): ?>
                                                                            <button type="button" class="btn btn-danger btn-sm remove-fondo" style="display: none;">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        <?php else: ?>
                                                                            <button type="button" class="btn btn-danger btn-sm remove-fondo">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            <?php endfor; ?>
                                                            
                                                            <td>
                                                                <button type="button" class="btn btn-success btn-sm add-fondo">
                                                                    <i class="fas fa-plus"></i> Agregar
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

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
    $(document).ready(function() {
        // Función para actualizar opciones y evitar duplicados
        function actualizarOpciones(fila) {
            var selects = fila.find('select');
            var valoresSeleccionados = [];
            
            // Recoger todos los valores seleccionados
            selects.each(function() {
                var valor = $(this).val();
                if (valor) {
                    valoresSeleccionados.push(valor);
                }
            });
            
            // Actualizar cada select
            selects.each(function() {
                var selectActual = $(this);
                var valorActual = selectActual.val();
                
                selectActual.find('option').each(function() {
                    var option = $(this);
                    var valorOption = option.val();
                    
                    if (valorOption === '') {
                        option.prop('disabled', false);
                    } else if (valoresSeleccionados.includes(valorOption) && valorOption !== valorActual) {
                        option.prop('disabled', true);
                    } else {
                        option.prop('disabled', false);
                    }
                });
            });
        }

        // Agregar nuevo beneficio
        $('.add-fondo').click(function() {
            var fila = $(this).closest('tr');
            var idNFinal = fila.data('nfinal');
            var celdasFondo = fila.find('td').slice(2, -1); // Excluir proyecto, nota y acción
            
            // Buscar la primera celda vacía
            var celdaVacia = null;
            celdasFondo.each(function() {
                var select = $(this).find('select');
                if (select.val() === '') {
                    celdaVacia = $(this);
                    return false;
                }
            });
            
            if (celdaVacia) {
                // Mostrar botón de eliminar en celda vacía
                celdaVacia.find('.remove-fondo').show();
                actualizarOpciones(fila);
                return;
            }
            
            // Si no hay celdas vacías, agregar nueva columna a toda la tabla
            agregarNuevaColumna();
            
            // Seleccionar el nuevo select en esta fila
            var nuevasCeldas = fila.find('td').slice(2, -1);
            var ultimaCelda = nuevasCeldas.last();
            ultimaCelda.find('.remove-fondo').show();
            actualizarOpciones(fila);
        });

        // Eliminar beneficio
        $(document).on('click', '.remove-fondo', function() {
            var fila = $(this).closest('tr');
            $(this).closest('td').find('select').val('');
            $(this).hide();
            actualizarOpciones(fila);
        });

        // Cambio en select
        $(document).on('change', 'select', function() {
            var fila = $(this).closest('tr');
            actualizarOpciones(fila);
            
            // Mostrar/ocultar botón de eliminar
            if ($(this).val() === '') {
                $(this).closest('td').find('.remove-fondo').hide();
            } else {
                $(this).closest('td').find('.remove-fondo').show();
            }
        });

        // Función para agregar nueva columna a toda la tabla
        function agregarNuevaColumna() {
            var tabla = $('#tablaFondos');
            var encabezado = $('#encabezado');
            var numColumnas = encabezado.find('th').length - 3; // Excluir proyecto, nota y acción
            
            // Agregar encabezado
            var nuevoTh = $('<th>').text('Beneficio ' + (numColumnas + 1));
            encabezado.find('th').eq(-2).before(nuevoTh);
            
            // Agregar celdas a cada fila
            tabla.find('tbody tr').each(function() {
                var fila = $(this);
                var idNFinal = fila.data('nfinal');
                
                var nuevaCelda = $('<td>').html(
                    '<div class="fondo-row">' +
                    '<select name="fondos[' + idNFinal + '][]" class="form-select form-select-sm">' +
                    '<option value="">Seleccionar</option>' +
                    '<?php foreach ($fondos as $fondo): ?>' +
                    '<option value="<?php echo $fondo['ID_PREMIO']; ?>"><?php echo addslashes($fondo['PREMIO']); ?></option>' +
                    '<?php endforeach; ?>' +
                    '</select>' +
                    '<button type="button" class="btn btn-danger btn-sm remove-fondo" style="display: none;">' +
                    '<i class="fas fa-trash"></i>' +
                    '</button>' +
                    '</div>'
                );
                
                fila.find('td').eq(-2).before(nuevaCelda);
            });
        }

        // Inicializar opciones al cargar
        $('#tablaFondos tbody tr').each(function() {
            actualizarOpciones($(this));
        });
    });

    // Función para ordenar notas
    function ordenarNotas() {
        var tabla = $('.table-bordered tbody');
        var filas = tabla.find('tr').get();
        
        filas.sort(function(a, b) {
            var notaA = parseFloat($(a).find('.final-grade').text());
            var notaB = parseFloat($(b).find('.final-grade').text());
            return notaB - notaA; // Orden descendente
        });
        
        $.each(filas, function(index, fila) {
            tabla.append(fila);
        });
    }

    // Script para ocultar/mostrar el menú lateral
    document.querySelector('.toggle-sidebar-btn').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('main').classList.toggle('active');
    });
    </script>
</body>
</html>