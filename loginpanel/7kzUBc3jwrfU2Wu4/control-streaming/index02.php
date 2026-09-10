<?php
// 1. CONEXIÓN A LA BASE DE DATOS
$host = 'localhost';
$user = 'root';
$pass = ''; 
$db   = 'streaming_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Error en base de datos: " . $e->getMessage());
}

// 2. CONTROLADOR DE ACCIONES (POST-REDIRECT-GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    if ($_POST['accion'] === 'guardar') {
        $cliente = trim($_POST['cliente']);
        $plataforma = trim($_POST['plataforma']);
        $cuenta_correo = trim($_POST['cuenta_correo']);
        $perfil_numero = intval($_POST['perfil_numero']);
        $pin_perfil = !empty($_POST['pin_perfil']) ? trim($_POST['pin_perfil']) : 'No tiene';
        $fecha_vencimiento = $_POST['fecha_vencimiento'];
        $precio = floatval($_POST['precio']);
        $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp']);

        $sql = "INSERT INTO perfiles_streaming (cliente, plataforma, cuenta_correo, perfil_numero, pin_perfil, fecha_vencimiento, precio, whatsapp) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cliente, $plataforma, $cuenta_correo, $perfil_numero, $pin_perfil, $fecha_vencimiento, $precio, $whatsapp]);
        
        header("Location: index.php?msg=guardado");
        exit;
    }

    if ($_POST['accion'] === 'renovar') {
        $id = intval($_POST['id']);
        // Si ya venció, suma desde hoy. Si no, suma desde su fecha de vencimiento actual.
        $sql = "UPDATE perfiles_streaming 
                SET fecha_vencimiento = DATE_ADD(IF(fecha_vencimiento < CURDATE(), CURDATE(), fecha_vencimiento), INTERVAL 30 DAY) 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        header("Location: index.php?msg=renovado");
        exit;
    }

    if ($_POST['accion'] === 'eliminar') {
        $id = intval($_POST['id']);
        $sql = "DELETE FROM perfiles_streaming WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        header("Location: index.php?msg=eliminado");
        exit;
    }
}

// 3. INDICADORES Y CONSULTAS FINANCIERAS
$hoy = date('Y-m-d');

$total_perfiles = $pdo->query("SELECT COUNT(*) FROM perfiles_streaming")->fetchColumn();
$vencidos_hoy   = $pdo->query("SELECT COUNT(*) FROM perfiles_streaming WHERE fecha_vencimiento < '$hoy'")->fetchColumn();
$ingresos_mes   = $pdo->query("SELECT SUM(precio) FROM perfiles_streaming WHERE fecha_vencimiento >= '$hoy'")->fetchColumn();
$ingresos_mes   = $ingresos_mes ? floatval($ingresos_mes) : 0.00;

// Obtener listado maestro
$stmt = $pdo->query("SELECT * FROM perfiles_streaming ORDER BY fecha_vencimiento ASC");
$perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StreamDash Live - Localhost</title>
    <!-- Tailwind CSS (Diseño Moderno Avanzado) -->
    <script src="https://tailwindcss.com"></script>
    <!-- Bootstrap Icons para iconos rápidos -->
    <link rel="stylesheet" href="https://jsdelivr.net">
    <!-- AlpineJS para interactividad reactiva en cliente -->
    <script defer src="https://jsdelivr.net"></script>
</head>
<body class="bg-[#f8fafc] text-slate-800 font-sans antialiased" x-data="{ openModal: false, buscador: '' }">

    <!-- ─── NAVBAR SUPERIOR ─── -->
    <nav class="bg-white/90 backdrop-blur-md border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="bg-gradient-to-tr from-indigo-600 to-purple-600 text-white p-2.5 rounded-xl shadow-lg shadow-indigo-100">
                        <i class="bi bi-play-btn-fill text-xl flex"></i>
                    </div>
                    <div>
                        <span class="text-xl font-black text-slate-900 tracking-tight">Stream<span class="text-indigo-600">Hub</span></span>
                        <span class="ml-2 bg-indigo-50 text-indigo-600 text-[10px] uppercase font-bold px-2 py-0.5 rounded-md border border-indigo-100">Localhost</span>
                    </div>
                </div>

                <!-- Buscador Central Dinámico -->
                <div class="hidden md:block flex-1 max-w-md mx-8">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="bi bi-search"></i>
                        </span>
                        <input x-model="buscador" type="text" placeholder="Buscar cliente por nombre o correo..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 focus:bg-white transition-all">
                    </div>
                </div>

                <!-- Botón Añadir -->
                <div>
                    <button @click="openModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-100 flex items-center space-x-2">
                        <i class="bi bi-plus-circle-fill"></i>
                        <span>Asignar Perfil</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- ─── CONTENIDO GENERAL ─── -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Notificaciones de Eventos -->
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 rounded-xl border flex items-center space-x-3 shadow-sm <?php 
                echo $_GET['msg'] == 'guardado' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : '';
                echo $_GET['msg'] == 'renovado' ? 'bg-blue-50 border-blue-200 text-blue-800' : '';
                echo $_GET['msg'] == 'eliminado' ? 'bg-rose-50 border-rose-200 text-rose-800' : '';
            ?>">
                <i class="bi <?php 
                    echo $_GET['msg'] == 'guardado' ? 'bi-check-circle-fill text-emerald-500' : '';
                    echo $_GET['msg'] == 'renovado' ? 'bi-arrow-repeat text-blue-500' : '';
                    echo $_GET['msg'] == 'eliminado' ? 'bi-trash-fill text-rose-500' : '';
                ?> text-lg"></i>
                <span class="text-xs font-semibold">
                    <?php
                        if($_GET['msg'] == 'guardado') echo "¡Éxito! Nueva venta agregada al control.";
                        if($_GET['msg'] == 'renovado') echo "Suscripción extendida +30 días con éxito.";
                        if($_GET['msg'] == 'eliminado') echo "El registro se ha eliminado de la base de datos.";
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- 📊 METRICAS (KPI DASHBOARD) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cupos Vendidos</p>
                    <h3 class="text-3xl font-black text-slate-900 mt-1"><?php echo $total_perfiles; ?></h3>
                </div>
                <div class="bg-slate-50 text-slate-600 h-11 w-11 rounded-xl flex items-center justify-center text-lg">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cortes / Pendientes</p>
                    <h3 class="text-3xl font-black <?php echo $vencidos_hoy > 0 ? 'text-rose-600' : 'text-slate-900'; ?> mt-1"><?php echo $vencidos_hoy; ?></h3>
                </div>
                <div class="<?php echo $vencidos_hoy > 0 ? 'bg-rose-50 text-rose-600 animate-pulse' : 'bg-slate-50 text-slate-400'; ?> h-11 w-11 rounded-xl flex items-center justify-center text-lg">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Caja Mensual Estimada</p>
                    <h3 class="text-3xl font-black text-slate-900 mt-1">$<?php echo number_format($ingresos_mes, 2); ?> <span class="text-xs text-slate-400 font-normal">MXN</span></h3>
                </div>
                <div class="bg-emerald-50 text-emerald-600 h-11 w-11 rounded-xl flex items-center justify-center text-lg">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
        </div>

        <!-- Buscador para pantallas móviles -->
        <div class="block md:hidden mb-6">
            <input x-model="buscador" type="text" placeholder="Buscar cliente..." class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none">
        </div>

        <!-- ─── GRID DE TARJETAS DE CLIENTES ─── -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($perfiles as $p): 
                // 1. Evaluar Estado de Vencimiento
                if ($p['fecha_vencimiento'] < $hoy) {
