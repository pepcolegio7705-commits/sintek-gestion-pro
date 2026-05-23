<?php
require 'conexion.php';

// 1. Verificación de seguridad y sesión
require 'seguridad.php';

verificar_permisos(['Administrador']);
$rol = $_SESSION['rol'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 2. Recolección y saneamiento de datos
    $fecha       = $_POST['fecha_gasto'];
    $id_cat      = (int)$_POST['id_cat_gasto'];
    $monto       = (float)$_POST['monto'];
    $id_modo     = (int)$_POST['id_modo_pago']; // Asegúrate que este name coincida con tu select de modo pago
    $descripcion = htmlspecialchars(trim($_POST['descripcion']), ENT_QUOTES, 'UTF-8');
    $usuario     = $_SESSION['nombre_usuario']; // Quién realiza la operación

    // 3. Validación de campos obligatorios
    if (empty($fecha) || $id_cat <= 0 || $monto <= 0 || $id_modo <= 0) {
        header("Location: modulo_gastos.php?status=invalid");
        exit();
    }

    try {
        // Iniciamos una transacción por seguridad
        $pdo->beginTransaction();

        // 4. Preparar la inserción con las nuevas columnas
        $sql = "INSERT INTO gastos (
                    id_cat_gasto, 
                    descripcion, 
                    monto, 
                    fecha_gasto, 
                    id_modo_pago, 
                    estado, 
                    usuario_registro
                ) VALUES (
                    :cat, 
                    :descr, 
                    :monto, 
                    :fecha, 
                    :modo, 
                    'Pagado', 
                    :usu
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cat'   => $id_cat,
            ':descr' => $descripcion,
            ':monto' => $monto,
            ':fecha' => $fecha,
            ':modo'  => $id_modo,
            ':usu'   => $usuario
        ]);

        // 5. Confirmar operación
        $pdo->commit();

        // Éxito: Redirigimos al módulo de gastos con el parámetro de éxito
        header("Location: modulo_gastos.php?status=success");
        exit();

    } catch (PDOException $e) {
        // Si algo sale mal, revertimos los cambios
        $pdo->rollBack();
        
        // Logueamos el error para el programador
        error_log("Error en inserción de gasto: " . $e->getMessage());
        
        // Redirigimos con error de base de datos
        header("Location: modulo_gastos.php?status=db_error");
        exit();
    }

} else {
    // Si intentan acceder directamente al archivo sin POST
    header("Location: modulo_gastos.php");
    exit();
}