<?php

   session_start();
   if($_SESSION['rol'] != 1 && $_SESSION['rol'] != 2 ){
      header("Location: ./");
   }

   include ("../conexion.php");
   include "config.php";

   if(empty($_POST['btnEliminar'])){
      
      $idcargo = openssl_decrypt($_POST['id'],AES,KEY);
      
      $idcargo1 = mysqli_real_escape_string($con,$idcargo);

      $query_delete = $con->prepare("UPDATE cargos set estatus = 0 where id = ?");
      $query_delete->bind_param("i",$idcargo1);
      $query_delete->execute();

      if($query_delete){
         $mensaje = '<script>
                           Swal.fire({
                                 position: "top-end",
                                 icon: "success",
                                 title: "Cargo eliminado correctamente",
                                 showConfirmButton: false,
                                 timer: 1500
                           });

                        </script>';
      }else{
         $mensaje =  '<script>
                              Swal.fire({
                                 icon: "Error",
                                 title: "Oops...",
                                 text: "Error al eliminar el Cargo - Comuniquese con el administrador!"
                              });
                        </script>';
      }

   }

   
   mysqli_close($con);
?>

<?php

    include ("../conexion.php");

    if(empty(openssl_decrypt($_REQUEST['id'],AES,KEY))){
        header("location:listado_cargos.php");
        mysqli_close($con);
    }

    $id = openssl_decrypt($_REQUEST['id'],AES,KEY);
    $query_consulta = mysqli_query($con, "SELECT * from cargos where id = $id and estatus=1");
    $result = mysqli_num_rows($query_consulta);

    If($result == 0){
        header("location:listado_cargos.php");
        mysqli_close($con);
    }

    $query = mysqli_query($con, "SELECT * from cargos where id = $id and estatus=1");

    while($data = mysqli_fetch_array($query)){
        $id = $data['id'];
        $desc = $data['nombre'];
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
      <div class="full_container">
         <?php echo $mensaje?>
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
                      <!----------------------Formulario Eliminar-------------------->

                    <div class="col-md-12">
                        <?php echo $alert?>
                        <div class="block block-themed">
                            <div class="block-header bg-gd-emerald">
                                <h3 class="block-title">Eliminar Cargo</h3>
                            </div>
                            <div class="block-content text-center">

                              <div class="">
                                   <i class="fas fa-user-times fa-3x" style="color:red"></i>
                                   <br><br>
                                   <h2>¿Está seguro de eliminar el siguiente registro?</h2>
                                   <hr>
                                   <p>ID: <span><?php echo $id;?></span></p>
                                   <p>Cargo: <span><?php echo $desc;?></span></p>

                                   <form method="post" action="">
                                     <input type="hidden" name="id" value="<?php echo openssl_encrypt($id,AES,KEY);?>">
                                     <a href="listado_cargos.php" class="btn btn-success"><i class="fas fa-ban"></i> Cancelar</a>
                                     <button type="submit" class="btn btn-danger" name="btnEliminar"><i class="fas fa-user-minus"></i> Eliminar</button>
                                   </form>
                                 </div>

                            </div>
                        </div>
                    </div>

                    <!-------------------Fin formulario---------------------------->
                     <!-- end graph -->
                  </div>
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