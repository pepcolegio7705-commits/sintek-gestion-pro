<?php

    session_start();

    if (!empty($_SESSION['active'])) {
        header('location:sistema/');        
    }else{

        if(!empty($_POST)){
            if(empty($_POST['usuario']) || empty($_POST['clave'])){
                 echo '<div class="alert alert-danger" role="alert">
                        Campos usuario y/o clave vacíos!!!
                    </div>';
            }else{
                require_once "conexion.php";

                //con esta funcion evitamos simbolos especiales por seguridad

                $user = mysqli_real_escape_string($con,$_POST['usuario']);
                $pass =mysqli_real_escape_string($con, $_POST['clave']);

                $query = mysqli_query($con,"SELECT * from usuarios where usuario = '$user' and clave = '$pass' and estatus = 1");
                mysqli_close($con);
                $result = mysqli_num_rows($query);

                if($result > 0){
                    $data = mysqli_fetch_array($query);
                
                    $_SESSION['active'] = true;
                    $_SESSION['idUser'] = $data['idusuario'];
                    $_SESSION['nombre'] = $data['nombre'];
                    $_SESSION['email'] = $data['correo'];
                    $_SESSION['user'] = $data['usuario'];
                    $_SESSION['rol'] = $data['rol'];

                    header('location:sistema/');
                }else{
                    echo '<div class="alert alert-danger" role="alert">
                            Usuario y/o clave incorrectos!!!
                        </div>';
                    session_destroy();
                }
            }
        }
    }
?>





<!DOCTYPE html>
<html lang="en">
<head>
   <link href="css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
   <link rel="stylesheet" href="css/styles.css">
   <script src="js/bootstrap.min.js"></script>
   <script src="js/jquery.min.js"></script>
   <link rel="shortcut icon" href="img/icono.png">   
</head>

<body>
   <section class="login-block">
      <div class="container">
         <div class="row">
            <div class="col-md-4 login-sec">
               <h2 class="text-center">Inicio de Sesión</h2>
               <form class="login-form" action="" method="post">
                  <div class="form-group">
                     <label for="usuario">Usuario</label>
                     <input type="text" class="form-control" name="usuario" placeholder="Ingrese usuario">
                     
                  </div>
                  <div class="form-group">
                     <label for="clave">Password</label>
                     <input type="password" class="form-control" name="clave" placeholder="Ingrese contraseña">
                  </div>
                  <div class="form-group">
                     <button type="submit" class="btn btn-danger">Ingresar</button>
                  </div>
               </form>
            </div>
            <div class="col-md-8 m-auto">
               <img class="rounded mx-auto d-block m-3" src="img/avatar.png" style="width:60%">
            </div>
            <!-- footer -->
            <div class="container-fluid">
               <div class="footer">
                  <p>Copyright &copy; <?php echo date("Y");?> JAW Sistemas. Todos los derechos reservados.<br><br>
               </div>
            </div>
         </div>
      </div>
   </section>
</body>

</html>