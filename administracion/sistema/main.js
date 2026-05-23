$(document).ready(function() {
   
    
    $('#tablaAsistencias').DataTable({
      "bProcessing": true,
             "bDeferRender": true,
             "bServerSide": true,
             "sAjaxSource": "serverside/serversideAsistencia.php",
             /*"columnDefs": [ {
                 "targets": -1,
                 "defaultContent": "<div class='wrapper text-center'><div class='btn-group'><button class='btn btn-info btn-sm btnEditar' data-toggle='tooltip' title='Editar'><i class='fas fa-edit'></i> Editar</button><button class='btn btn-danger btn-sm btnBorrar' data-toggle='tooltip' title='Eliminar'><i class='fas fa-trash-alt'></i> Eliminar</button></div></div>"
             } ],*/

             responsive: true,
            dom: 'Bfrtilp',
            buttons:[
            {
                extend: 'print',
                text: '<i class="fa fa-print"></i> ',
                messageTop: '',
                title:'',
                className: 'btn btn-warning',
                 customize: function (win) {
                    $(win.document.body)
                        .css('font-size','8pt')
                        .prepend('<div style="margin-left:5%">' +
                                    '<table border=\'1\' cellspacing=0 style="text-align:center; width: 90%;">' +

                                         '<thead>' +
                                            '<tr>' +
                                                '<th colspan=\'2\'><h4>ESCUELA N° 752 - "Raquel Chattah de Bec" - Tel. 280-4484112 - Rawson Chubut</h4></th>' +
                                                '<th></th>' +
                                            '</tr>' +
                                            '<tr>' +
                                            '<th colspan=\'2\'><h4>Asistencia</h4></th>' +
                                            '</tr>' +
                                         '</thead>' +
                                         '<tbody>' +
                                            '<tr>' +
                                                '<td colspan="2" style="font-size: 8px"><img src="../img/logo.png"></td>' +
                                            '</tr>' +
                                         '</tbody>' +
                                    '</table>'+
                                '</div>');
                              },

                },
               ],
               language: {
                "lengthMenu": "Mostrar _MENU_ registros",
                "zeroRecords": "No se encontraron resultados",
                "info": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "infoFiltered": "(filtrado de un total de _MAX_ registros)",
                "sSearch": "Buscar:",
                "oPaginate": {
                    "sFirst": "Primero",
                    "sLast":"Último",
                    "sNext":"Siguiente",
                    "sPrevious": "Anterior"
                 },
                 "sProcessing":"Procesando...",
            },
    
    });
});