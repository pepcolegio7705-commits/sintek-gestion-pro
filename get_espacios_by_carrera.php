<?php
    require 'conexion.php'; 

    // Verifica que se haya recibido el ID de la carrera
    if (isset($_GET['id_carrera'])) {
        $id_carrera = (int)$_GET['id_carrera'];
        
        // Consulta los espacios curriculares asociados a ese ID de carrera
        $sql = "SELECT id_espacio, nombre_espacio, anio_cursada 
                FROM espacios_curriculares 
                WHERE id_carrera = :id_carrera 
                ORDER BY anio_cursada, nombre_espacio";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_carrera', $id_carrera, PDO::PARAM_INT);
        $stmt->execute();
        $espacios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Devuelve los resultados en formato JSON
        header('Content-Type: application/json');
        echo json_encode($espacios);
    } else {
        // Si no se especifica la carrera, devuelve un arreglo vacío
        header('Content-Type: application/json');
        echo json_encode([]);
    }
?>