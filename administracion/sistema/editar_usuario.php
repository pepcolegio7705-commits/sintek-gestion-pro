<?php

   session_start();
   if($_SESSION['rol'] != 1 && $_SESSION['rol'] != 2 ){
      header("Location: ./");
   }

      include ("../conexion.php");
      include ("config.php");
      //Actualizar datos
  

      if(isset($_POST['btnEditar'])){
            if(empty($_POST['nombre']) || empty($_POST['correo']) || empty($_POST['usuario']) || empty($_POST['clave']) || empty($_POST['rol'])){
               $mensaje =  '<script>
                                 Swal.fire({
                                 icon: "error",
                                 title: "Oops...",
                                 text: "Los campos no deben estar vacíos!"
                                    });
                              </script>';

            }else{
   
               $idusuario = openssl_decrypt($_POST['id'],AES,KEY);
               $nombre = $_POST['nombre'];
               $email = $_POST['correo'];
               $user = $_POST['usuario'];
               $clave = $_POST['clave'];
               $rol = $_POST['rol'];
               //print_r($idusuario);
               //exit();
               $idusuario1 = mysqli_real_escape_string($con,$idusuario);
               $nombre1 = mysqli_real_escape_string($con,$nombre);
               $email1 = mysqli_real_escape_string($con,$email);
               $user1 = mysqli_real_escape_string($con,$user);
               $clave1 = mysqli_real_escape_string($con,$clave);
               $rol1 = mysqli_real_escape_string($con,$rol);
      
               $sentencia = $con->prepare("UPDATE usuarios SET nombre = ?, correo = ?, usuario = ?, clave = ?, rol = ? where idusuario = ?");
               $sentencia->bind_param("ssssii",$nombre1,$email1,$user1,$clave1,$rol1,$idusuario1);
               $sentencia->execute();
   
               if($sentencia){
                  $mensaje = '<script>
                                    Swal.fire({
                                          position: "top-end",
                                          icon: "success",
                                          title: "Usuario actualizado correctamente",
                                          showConfirmButton: false,
                                          timer: 1500
                                    });

                              </script>';
                  }else{
                     $mensaje =  '<script>
                                    Swal.fire({
                                      icon: "Error",
                                          title: "Oops...",
                                          text: "Error en la actualización del Usuario - Comuniquese con el administrador!""
                                        });
                                    </script>';
                  }
               }
            }

    $con->close();
?>


<?php

   //Chequeamos que el id del usuario no venga vacío
   If(empty(openssl_decrypt($_GET['id'],AES,KEY))){
      header("location:lista_usuarios.php");
      mysqli_close($con);
   }

   //Chequeamos que usuario exista en la base de datos
   $id = openssl_decrypt($_GET['id'],AES,KEY);
   include("../conexion.php");

   $sql = mysqli_query($con,"SELECT u.idusuario, u.nombre, u.correo, u.usuario, u.clave, u.rol as idrol, (r.rol) as rol from usuarios u INNER JOIN rol r on u.rol = r.idrol WHERE idusuario = $id and estatus = 1");
   $resultado = mysqli_num_rows($sql);

   if($resultado == 0){
      header("location:lista_usuarios.php");
      mysqli_close($con);
   }else{
      $option = '';
      while($data_consulta=mysqli_fetch_array($sql)){
         $idusuario = $data_consulta['idusuario'];
         $nombre = $data_consulta['nombre'];
         $correo = $data_consulta['correo'];
         $usuario = $data_consulta['usuario'];
         $clave = $data_consulta['clave'];
         $idrol = $data_consulta['idrol'];
         $rol = $data_consulta['rol'];

         if($idrol == 1){
            $option = '<option value="'.$idrol.'" select>'.$rol.'</option>';
        }else if($idrol == 2){
            $option = '<option value="'.$idrol.'" select>'.$rol.'</option>';
        }else if($idrol == 3){
            $option = '<option value="'.$idrol.'" select>'.$rol.'</option>';
        }else if($idrol == 4){
            $option = '<option value="'.$idrol.'" select>'.$rol.'</option>';
        }else if($idrol == 5){
            $option = '<option value="'.$idrol.'" select>'.$rol.'</option>';
         }else if($idrol == 6){
            $option = '<option value="'.$idrol.'" select>'.$rol.'</option>';
         }
      }
   }

mysqli_close($con);
?>

<!DOCTYPE html>
<html lang="en">
   <head>
      <!-- basic -->
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <!-- mobile metas -->
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="viewport" content="initial-scale=1, maximum-scale=1">
      <!-- site metas -->
      <title>SAP - Sistema Asistencia Personal</title>
      <meta name="keywords" content="">
      <meta name="description" content="">
      <meta name="author" content="">
      <!-- site icon -->
      <link rel="icon" href="images/fevicon.png" type="image/png" />
      <!-- bootstrap css -->
      <link rel="stylesheet" href="css/bootstrap.min.css" />
      <!-- site css -->
      <link rel="stylesheet" href="css/style.css" />
      <!-- responsive css -->
      <link rel="stylesheet" href="css/responsive.css" />
      <!-- color css -->
      <link rel="stylesheet" href="css/colors.css" />
      <!-- select bootstrap -->
      <link rel="stylesheet" href="css/bootstrap-select.css" />
      <!-- scrollbar css -->
      <link rel="stylesheet" href="css/perfect-scrollbar.css" />
      <link rel="stylesheet" href="css/font-awesome.min.css" />
      <!-- custom css -->
      <link rel="stylesheet" href="css/custom.css" />
      <link rel="shortcut icon" href="images/icono.png">
      
      <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
      <![endif]-->
      <script src="sweetalert/sweetalert2@11.js"></script> 
   </head>
   <body class="dashboard dashboard_1">

   <?php

         include("../conexion.php");

         $sql_usuarios = mysqli_query($con, "select COUNT(*) as total_usuarios from usuarios where estatus = 1");
         $resultado_usuarios = mysqli_fetch_array($sql_usuarios);
         $total_usuarios=$resultado_usuarios['total_usuarios'];

         $sql_docentes = mysqli_query($con, "select count(*) as total_doce from docentes where estatus = 1");
         $resultado_doce = mysqli_fetch_array($sql_docentes);
         $total_doce = $resultado_doce['total_doce'];

         $sql_dep = mysqli_query($con, "SELECT count(*) as total_dep from departamentos");
         $resultado_dep = mysqli_fetch_array($sql_dep);
         $total_dep = $resultado_dep['total_dep'];

         $sql_asis = mysqli_query($con, "select count(*) as total_asis from asistencia");
         $resultado_asis = mysqli_fetch_array($sql_asis);
         $total_asis = $resultado_asis['total_asis'];

   ?>
   <?php echo $mensaje?>
      <div class="full_container">
         <div class="inner_container">
            <!-- Sidebar  -->
            <nav id="sidebar">
               <div class="sidebar_blog_1">
                  <div class="sidebar-header">
                     <div class="logo_section">
                        <a href="index.php"><img class="logo_icon img-responsive" src="images/logo/logo_icon.png" alt="#" /></a>
                     </div>
                  </div>
                  <div class="sidebar_user_info">
                     <div class="icon_setting"></div>
                     <div class="user_profle_side">
                        <div class="user_img"><img class="img-responsive" src="images/layout_img/user_img.png" alt="#" /></div>
                        <div class="user_info">
                           <h6><?php echo  $_SESSION['nombre'].' - '.$_SESSION['rol']?></h6>
                           <p><span class="online_animation"></span> Online</p>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="sidebar_blog_2">
                  <h4>General</h4>
                  <ul class="list-unstyled components">
                     <li class="active">
                        <a href="index.php"><i class="fa fa-dashboard yellow_color"></i> <span>Escritorio</span></a>
                     </li>
                     <li>
                        <a href="#element" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fa fa-diamond purple_color"></i> <span>Acceso</span></a>
                        <ul class="collapse list-unstyled" id="element">
                        <li><a href="registro_usuario.php">> <span>Registro Usuario</span></a></li>
                           <li><a href="lista_usuarios.php">> <span>Lista Usuarios</span></a></li>
                           <li><a href="lista_departamentos.php">> <span>Departamentos</span></a></li>
                           <li><a href="listado_cargos.php">> <span>Cargos</span></a></li>
                        </ul>
                     </li>
                    
                     <li>
                        <a href="#apps" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fa fa-object-group blue2_color"></i> <span>Docentes</span></a>
                        <ul class="collapse list-unstyled" id="apps">
                           <li><a href="registro_docente.php">> <span>Registro Docente</span></a></li>
                           <li><a href="listado_docentes.php">> <span>Lista Docentes</span></a></li>
                           <!--<li><a href="">> <span>Media Gallery</span></a></li>-->
                        </ul>
                     </li>
                     <li class="active">
                        <a href="#additional_page" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fa fa-clone yellow_color"></i> <span>Asistencias</span></a>
                        <ul class="collapse list-unstyled" id="additional_page">
                           <li>
                              <a href="lista_asistencia.php">> <span>Asistencia</span></a>
                           </li>
                           <li>
                              <a href="busqueda_asistencia.php">> <span>Por Agente</span></a>
                           </li>
                        </ul>
                     </li>
                  </ul>
               </div>
            </nav>
            <!-- end sidebar -->
            <!-- right content -->
            <div id="content">
            
               <!-- topbar -->
               <div class="topbar">
                  <nav class="navbar navbar-expand-lg navbar-light">
                     <div class="full">
                        <button type="button" id="sidebarCollapse" class="sidebar_toggle"><i class="fa fa-bars"></i></button>
                        <div class="right_topbar">
                           <div class="icon_info">
                              <ul class="user_profile_dd">
                                 <li>
                                    <a class="dropdown-toggle" data-toggle="dropdown"><img class="img-responsive rounded-circle" src="images/layout_img/user_img.png" alt="#" /><span class="name_user"><?php echo  $_SESSION['nombre']?></span></a>
                                    <div class="dropdown-menu">
                                       <a class="dropdown-item" href="">Perfil</a>
                                       
                                       <a class="dropdown-item" href="salir.php"><span>Cerrar Sesión</span> <i class="fa fa-sign-out"></i></a>
                                    </div>
                                 </li>
                              </ul>
                           </div>
                        </div>
                     </div>
                  </nav>
               </div>
               <!-- end topbar -->
               <!-- dashboard inner -->
               <div class="midde_cont">
                  <div class="container-fluid">
                     <div class="row column_title">
                        <div class="col-md-12">
                           <div class="page_title">
                              <h2>Dashboard</h2>
                           </div>
                        </div>
                     </div>
                     <div class="row column1">
                        <div class="col-md-6 col-lg-3">
                           <div class="full counter_section margin_bottom_30">
                              <div class="couter_icon">
                                 <div> 
                                    <i class="fa fa-user yellow_color"></i>
                                 </div>
                              </div>
                              <div class="counter_no">
                                 <div>
                                    <p class="total_no"><?php echo $total_doce?></p>
                                    <p class="head_couter">Docentes</p>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                           <div class="full counter_section margin_bottom_30">
                              <div class="couter_icon">
                                 <div> 
                                    <i class="fa fa-clock-o blue1_color"></i>
                                 </div>
                              </div>
                              <div class="counter_no">
                                 <div>
                                    <p class="total_no"><?php echo $total_asis?></p>
                                    <p class="head_couter">Asistencias</p>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                           <div class="full counter_section margin_bottom_30">
                              <div class="couter_icon">
                                 <div> 
                                    <i class="fa fa-users blue1_color" aria-hidden="true"></i>
                                 </div>
                              </div>
                              <div class="counter_no">
                                 <div>
                                    <p class="total_no"><?php echo $total_usuarios?></p>
                                    <p class="head_couter">Usuarios</p>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                           <div class="full counter_section margin_bottom_30">
                              <div class="couter_icon">
                                 <div> 
                                    <i class="fa fa-comments-o red_color"></i>
                                 </div>
                              </div>
                              <div class="counter_no">
                                 <div>
                                    <p class="total_no"><?php echo $total_dep?></p>
                                    <p class="head_couter">Departamentos</p>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                 
                    
                     <!-- end graph -->
                  </div>

                  <!---------------------Editar Usuarios--------------------->

                  <div class="content m-auto">
                     
                    <!-- Bootstrap Design -->
                    <h2 class="text-center">Editar Información Usuario</h2>
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <!-- Default Elements -->
                            <div class="block">

                                <div class="shadow-lg p-3 m-auto bg-white rounded">
                                    <form action="" method="post" class="">
                                       <input type="hidden" name="id" value="<?php echo openssl_encrypt($idusuario,AES,KEY)?>">
                                        <div class="form-group row">
                                            <label class="col-12" for="example-text-input">Nombre Completo</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $nombre?>" placeholder="Ingrese nombre completo">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-12" for="example-email-input">Email</label>
                                            <div class="col-md-9">
                                                <input type="email" class="form-control" id="correo" name="correo" value="<?php echo $correo?>" placeholder="Email..">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-12" for="example-text-input">Usuario</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo $usuario?>" placeholder="Ingrese nombre usuario">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-12" for="example-password-input">Password</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="clave" name="clave" value="<?php echo $clave?>" placeholder="Password..">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label class="col-12" for="example-select">Tipo Usuario</label>
                                            <div class="col-md-9">
                                            <?php
                                                include "../conexion.php";
                                                $query_rol = mysqli_query($con, "SELECT * from rol");
                                                mysqli_close($con);
                                                $result_rol = mysqli_num_rows($query_rol);

                                             ?>

                                              <select id="rol" name = "rol" class="form-control" name="rol">
                                                <?php
                                                  echo $option;
                                                  if ($result_rol > 0) {
                                                      while ($rol = mysqli_fetch_array($query_rol)) {
                                                ?>
                                                <option value="<?php echo $rol["idrol"]?>"><?php echo $rol["rol"]?></option>

                                                <?php
                                                      }
                                                  }
                                                ?>
                                              </select>
                                           </div>
                                         </div>

                                        <div class="form-group row">
                                            <div class="col-6">
                                                <button type="submit" class="btn btn-success" name="btnEditar"><i class="fa fa-floppy-o" aria-hidden="true"></i> Editar Usuario</button>
                                            </div>
                                            <div class="col-6">
                                              <div class="col-6">
                                                 <a class="btn btn-warning" href="lista_usuarios.php"><i class="fa fa-backward" aria-hidden="true"></i> Volver</a>
                                               </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <!-- END Default Elements -->
                        </div>
                      </div>
                    </div>

                  <!----------------------Fin Editar Usuario----------------->
                  <!-- footer -->
                  <div class="container-fluid">
                     <div class="footer">
                        <p>Copyright &copy; <?php echo date("Y");?> JAW Sistemas. Todos los derechos reservados.<br><br>
                         
                        </p>
                     </div>
                  </div>
               </div>
               <!-- end dashboard inner -->
            </div>
         </div>
      </div>
       <!-- jQuery -->
       <script src="js/jquery.min.js"></script>
      <script src="js/popper.min.js"></script>
      <script src="js/bootstrap.min.js"></script>
      <!-- wow animation -->
      <script src="js/animate.js"></script>
      <!-- select country -->
      <script src="js/bootstrap-select.js"></script>
      <!-- owl carousel -->
      <script src="js/owl.carousel.js"></script>

      <!-- nice scrollbar -->
      <script src="js/perfect-scrollbar.min.js"></script>
      <script>
         var ps = new PerfectScrollbar('#sidebar');
      </script>
      <!-- custom js -->
      <script src="js/custom.js"></script>
      <script src="js/chart_custom_style1.js"></script>
   </body>
</html>