<?php
/**
 * PROCESADOR AJAX: LISTADO DE STAFF (VERSIÓN BLINDADA UUID)
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

// Evitamos cualquier salida de texto previa que rompa el JSON
if (ob_get_length()) ob_clean();

// Columnas para ordenamiento (Índices según tu tabla DataTables)
$columns = ['s.id_staff', 's.dni', 's.apellido', 'a.nombre_area', 's.activo'];

// 1. CONSULTA SQL: Agregamos uuid_staff para el blindaje de enlaces
$query = "SELECT s.*, a.nombre_area, 
          (SELECT COUNT(*) FROM retenciones_judiciales r 
           WHERE r.id_persona = s.id_staff AND r.tipo_persona = 'Staff' AND r.activo = 1) as total_retenciones
          FROM personal_staff s 
          LEFT JOIN areas a ON s.id_area = a.id_area 
          WHERE 1=1";

// 2. BUSCADOR GLOBAL (Seguro)
if (!empty($_POST['search']['value'])) {
    $search = $_POST['search']['value'];
    // Usamos parámetros nombrados para mayor seguridad si fuera necesario, 
    // pero mantenemos tu estructura con limpieza básica
    $query .= " AND (s.dni LIKE :search OR s.apellido LIKE :search OR s.nombre LIKE :search)";
}

// 3. CONTEO DE TOTALES
$totalData = $pdo->query("SELECT COUNT(*) FROM personal_staff")->fetchColumn();
$totalFiltered = $totalData; 

// 4. ORDENAMIENTO
if (isset($_POST['order'])) {
    $query .= " ORDER BY " . $columns[$_POST['order'][0]['column']] . " " . $_POST['order'][0]['dir'];
} else {
    $query .= " ORDER BY s.apellido ASC";
}

// 5. PAGINACIÓN
if (isset($_POST['start']) && $_POST['length'] != -1) {
    $query .= " LIMIT " . intval($_POST['start']) . " ," . intval($_POST['length']);
}

$stmt = $pdo->prepare($query);

// Si hay búsqueda, vinculamos el parámetro
if (!empty($_POST['search']['value'])) {
    $stmt->bindValue(':search', '%' . $_POST['search']['value'] . '%');
}

$stmt->execute();
$data = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nestedData = [];
    
    // Mapeo exacto para los índices de tu JavaScript:
    $nestedData[0]  = $row['id_staff'];         // ID Interno (úsalo solo para lógica interna, no para URLs)
    $nestedData[1]  = $row['dni'];              
    $nestedData[2]  = $row['apellido'] . ", " . $row['nombre']; 
    $nestedData[3]  = $row['nombre_area'] ?? 'Sin Área';        
    $nestedData[4]  = $row['activo'];           
    $nestedData[5]  = $row['cbu'];              
    $nestedData[6]  = $row['banco'];            
    $nestedData[7]  = $row['cantidad_hijos'];   
    $nestedData[8]  = $row['hijos_verificados'];
    $nestedData[9]  = $row['total_retenciones'];
    $nestedData[10] = $row['id_area'];          
    
    // --- CLAVE DEL BLINDAJE ---
    // Agregamos el UUID en el índice 11. 
    // Tu JS lo usará para construir: staff/edit/xxxx-xxxx
    $nestedData[11] = $row['uuid_staff']; 

    $data[] = $nestedData;
}

header('Content-Type: application/json');
echo json_encode([
    "draw"            => intval($_POST['draw'] ?? 0),
    "recordsTotal"    => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data"            => $data
]);