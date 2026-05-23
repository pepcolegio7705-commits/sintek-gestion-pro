<?php

    session_start();
    if($_SESSION['rol'] != 1 && $_SESSION['rol'] != 2){
        header("Location: ./");
    }

    include "../conexion.php";

    if(isset($_POST['btnNuevo'])){
      
      if(empty($_POST['nombre']) || empty($_POST['dni']) || empty($_POST['titulo']) || empty($_POST['dep']) ||  empty($_POST['car']) || empty($_POST['revista']) || empty($_POST['disp']) || empty($_POST['correo']) || empty($_POST['obs'])){
         $mensaje = '<script>
                     Swal.fire({
                       icon: "error",
                       title: "Oops...!",
                       text: "Operación inválida, todos los campos son obligatorios."
                       })
                 </script>';
         }else{

               $nombre = $_POST['nombre'];
               $dni = $_POST['dni'];
               $email = $_POST['correo'];
               $dep = $_POST['dep'];
               $car = $_POST['car'];
               $revista = $_POST['revista'];
               $titulo = $_POST['titulo'];
               $disp = $_POST['disp'];
               $obs = $_POST['obs'];

               $nombre1 = mysqli_real_escape_string($con,$nombre);
               $dni1 = mysqli_real_escape_string($con,$dni);
               $email1 = mysqli_real_escape_string($con,$email);
               $dep1 = mysqli_real_escape_string($con,$dep);
               $car1 = mysqli_real_escape_string($con,$car);
               $revista1 = mysqli_real_escape_string($con,$revista);
               $titulo1 = mysqli_real_escape_string($con,$titulo);
               $disp1 = mysqli_real_escape_string($con,$disp);
               $obs1 = mysqli_real_escape_string($con,$obs);

               $query_consulta = $con->prepare("SELECT * from docentes where dni = ?");
               $query_consulta->bind_param("i",$dni1);
               $query_consulta->execute();
               $resultado = $query_consulta->get_result();
               $result = $resultado->fetch_assoc();

               if($result > 0){
                  $mensaje = '<script>
                     Swal.fire({
                       icon: "error",
                       title: "Oops...!",
                       text: "El número de DNI ya existe en la base de datos!!."
                       })
                 </script>';
               }else{
                  $query_insert = $con->prepare("INSERT INTO docentes(ayn,dni,titulo,iddepartamento,idcargo,revista,dispo,email,obs)VALUES(?,?,?,?,?,?,?,?,?)");
                  $query_insert->bind_param("sssiissss",$nombre1,$dni1,$email1,$dep1,$car1,$revista1,$titulo1,$disp1,$obs1);
                  $query_insert->execute();

                  if($query_insert){
                     $mensaje = '<script>
                                 Swal.fire({
                                       position: "top-end",
                                       icon: "success",
                                       title: "Docente almacenado correctamente",
                                       showConfirmButton: false,
                                       timer: 1500
                                 });

                              </script>';
                  }else{
                     $mensaje =  '<script>
                                       Swal.fire({
                                       icon: "Error",
                                       title: "Oops...",
                                       text: "Error al almacenar el Docente - Comuniquese con el administrador!"
                                             });
                                    </script>';
                  }
               }
            }
   
         }
    $con->close();      
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
                     <div class="content m-auto">
                    <!-- Bootstrap Design -->
                    <?php echo $alert?>
                    <h2 class="text-center">Registro Personal Docente - No Docente</h2>
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <!-- Default Elements -->
                            <div class="block">

                                <div class="shadow-lg p-3 m-auto bg-white rounded">
                                    <form action="" method="post" class="">
                                        <div class="form-group row">
                                            <label class="col-12" for="example-text-input">Nombre Completo</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ingrese nombre completo">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-12" for="example-text-input">Dni</label>
                                            <div class="col-md-9">
                                                <input type="number" class="form-control" id="dni" name="dni" placeholder="Ingrese número dni">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                             <label class="col-12" for="titulo">Título</label>
                                             <div class="col-md-9">
                                                <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ingrese Título">
                                            </div>
                                          </div>
                                        <div class="form-group row">
                                            <label class="col-12" for="example-select">Departamento</label>
                                            <div class="col-md-9">
                                              <?php
                                                     include "../conexion.php";
                                                      $query_dep = mysqli_query($con, "SELECT * from departamentos where estatus=1");

                                                      $result_dep = mysqli_num_rows($query_dep);
                                                      mysqli_close($con);
                                                  ?>

                                               <select id="dep" name="dep" class="form-control">
                                                   <?php
                                                     if ($result_dep > 0) {
                                                         while ($dep = mysqli_fetch_array($query_dep)) {
                                                   ?>
                                                   <option value="<?php echo $dep["id"]?>"><?php echo $dep["descripcion"]?></option>

                                                   <?php
                                                         }
                                                      }
                                                   ?>
                                               </select>
                                           </div>
                                         </div>
                                         <div class="form-group row">
                                             <label class="col-12" for="example-select">Cargo</label>
                                             <div class="col-md-9">
                                                <?php
                                                      include "../conexion.php";
                                                         $query_car = mysqli_query($con, "SELECT * from cargos where estatus=1");

                                                         $result_car = mysqli_num_rows($query_car);
                                                         mysqli_close($con);
                                                   ?>

                                                <select id="car" name="car" class="form-control">
                                                   <option value="">---------</option>
                                                      <?php
                                                      if ($result_car > 0) {
                                                            while ($car = mysqli_fetch_array($query_car)) {
                                                      ?>
                                                      <option value="<?php echo $car["id"]?>"><?php echo $car["nombre"]?></option>

                                                      <?php
                                                            }
                                                      }
                                                      ?>
                                                </select>
                                             </div>
                                          </div>
                                          <div class="form-group row">
                                             <label class="col-12" for="example-select">Situación Revista</label>
                                             <div class="col-md-9">
                                                <select id="revista" name="revista" class="form-control">
                                                   <option value="">---------</option>
                                                   <option value="Titular">Titular</option>
                                                   <option value="Interino">Interino</option>
                                                   <option value="Suplente">Suplente</option>
                                                   <option value="Provisorio">Provisorio</option>
                                                   <option value="Provisorio">Habilitante</option>
                                                   <option value="Provisorio">Supletorio</option>
                                                   <option value="Provisorio">Sin Título</option>
                                                   <option value="Tareas Pasivas">Tareas Pasivas</option>
                                                   <option value="Permanente">Permanente</option>
                                                   <option value="Interino">Mensual</option>
                                                   <option value="Interino">Temporal</option>
                                                   <option value="Contratado">Contratado</option>
                                                </select>
                                             </div>
                                          </div>
                                          <div class="form-group row">
                                             <label class="col-12" for="disp">Disposición</label>
                                             <div class="col-md-9">
                                                <input type="text" class="form-control" id="disp" name="disp" placeholder="Ingrese posible disposición">
                                            </div>
                                          </div>
                                        <div class="form-group row">
                                            <label class="col-12" for="example-email-input">Email</label>
                                            <div class="col-md-9">
                                                <input type="email" class="form-control" id="correo" name="correo" placeholder="Email..">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                          <label class="col-12" for="obs">Observaciones</label>
                                          <div class="col-md-9">
                                             <textarea class="form-control" id="obs" name="obs" placeholder="" row="5"></textarea>
                                          </div>
                                          
                                        </div>
                                        <div class="form-group row">
                                            <div class="col-6">
                                                <button type="submit" class="btn btn-success" name="btnNuevo"><i class="fa fa-floppy-o" aria-hidden="true"></i> Crear Docente</button>
                                            </div>
                                            <div class="col-6">
                                              <div class="col-6">
                                                 <a class="btn btn-warning" href="listado_docentes.php"><i class="fa fa-backward" aria-hidden="true"></i> Volver</a>
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



       
