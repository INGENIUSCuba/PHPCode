<?php
require_once('../Connections/sitio.php'); 

function ejecuta_sqls($sql)
  {
    include('../Connections/sitio.php'); 
    $result=$sql;
    $miresult = mysql_query($result,$sitio);  
	print("\n  Sentencia Ejecutada. \n");
  } 

mysql_select_db($database_sitio, $sitio);


$sql="SELECT usuario_lista.correo AS destino, concat(usuarios.nombre, ' ',usuarios.apellido1, ' ',usuarios.apellido2) AS nomb_destino, lista.nombre AS nombre_lista,  centro.correo, correos_lista.fecha, correos_lista.origen, correos_lista.id_lista, correos_lista.asunto, correos_lista.cuerpo, correos_lista.id_correo, correos_lista.remitente, lista.id_lista FROM (usuarios INNER JOIN ((centro INNER JOIN lista ON centro.id_centro = lista.id_centro) INNER JOIN usuario_lista ON lista.id_lista = usuario_lista.id_lista) ON usuarios.correo = usuario_lista.correo) INNER JOIN correos_lista ON lista.id_lista = correos_lista.id_lista ORDER BY correos_lista.fecha";
$query_usuario_listaR = $sql;
$usuario_listaR = mysql_query($query_usuario_listaR, $sitio) or die(mysql_error());
$row_usuario_listaR = mysql_fetch_assoc($usuario_listaR);
$totalRows_usuario_listaR = mysql_num_rows($usuario_listaR);
if ($totalRows_usuario_listaR<>0) {
 do {
 
 		$asunto= $row_usuario_listaR['asunto'];

        $sql = "INSERT INTO email (fecha, remitente, responder, para, asunto, cuerpo, nomb_remitente, nomb_para,origen,id_lista)
		 VALUES ('".date("Y-m-d G:i:s")."', '".$row_usuario_listaR['remitente']."', '".$row_usuario_listaR['correo']."','".$row_usuario_listaR['destino']."','".$asunto."','".$row_usuario_listaR['cuerpo']."','".$row_usuario_listaR['remitente']."','".$row_usuario_listaR['nomb_destino']."','".$row_usuario_listaR['origen']."','".$row_usuario_listaR['id_lista']."')";
	    ejecuta_sqls($sql);

 	}   while ($row_usuario_listaR = mysql_fetch_assoc($usuario_listaR)); 

 $sql='TRUNCATE TABLE correos_lista';
 ejecuta_sqls($sql);
 
 } else {print("\n no hay correos para procesar \n");}

mysql_free_result($usuario_listaR);
?>
