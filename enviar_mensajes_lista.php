<?php
function limpia()
  {
     $dir = "atachados/";
      $handle = opendir($dir);
      while ($file = readdir($handle))
       {
        if (is_file($dir.$file))    {   unlink($dir.$file);   }
       }
  }
  
function ejecuta_sqls($sql)
  {
    include('../Connections/sitio.php'); 
    $result=$sql;
    $miresult = mysql_query($result,$sitio);  
	print("\n  Sentencia Ejecutada. \n");
  } 
  
function envia_mensajes()
 {  
  require_once ('../Connections/sitio.php'); 
  require_once ('../Connections/dSendMail2.inc.php');
   //////////////////
   set_time_limit(0);
   ignore_user_abort(true);
   /////////////////////

   mysql_select_db($database_sitio, $sitio);
   $cant_mensaje = 150;
   $sql="SELECT * FROM email LIMIT ".$cant_mensaje;

  $query_usuario_listaR = $sql;
  $usuario_listaR = mysql_query($query_usuario_listaR, $sitio) or die(mysql_error());
  $row_usuario_listaR = mysql_fetch_assoc($usuario_listaR);
  $totalRows_usuario_listaR = mysql_num_rows($usuario_listaR);
  $idcuerpo = $row_usuario_listaR['cuerpo'];
  if ($totalRows_usuario_listaR<>0) 
   {
    do {
       $m = new dSendMail2;
//   	    $m->importEML($row_usuario_listaR['cuerpo']);
		$m->setEMLFile("atachados/".$row_usuario_listaR['cuerpo']);
//        $m->sendThroughSMTP($host_lista, 25, $usuario_lista, $clave_lista);
     	$m->setFrom($row_usuario_listaR['remitente']);
		$m->headers['Reply-To']  =  $row_usuario_listaR['responder'];
		$m->setTo($row_usuario_listaR['para']);
		$m->setSubject($row_usuario_listaR['asunto']);
		 if ($m->send()) 
  		   {
             print ("\n Correo a enviado a usuario: ".$row_usuario_listaR['para']."|  asunto: ".$row_usuario_listaR['asunto']);
			 ///////////////////
			 $sql = "INSERT INTO enviados (fecha ,de ,para ,asunto,id_lista) VALUES ('".date("Y-m-d G:i:s")."', '".$row_usuario_listaR['origen']."', '".$row_usuario_listaR['para']."', '".$row_usuario_listaR['asunto']."', '".$row_usuario_listaR['id_lista']."');";
			 ejecuta_sqls($sql);
			 //////////////////
	     	 $sql='DELETE FROM email WHERE id_email = '.$row_usuario_listaR['id_email'];
		     if ($idcuerpo != $row_usuario_listaR['cuerpo']) { unlink( "atachados/".$idcuerpo); $idcuerpo=$row_usuario_listaR['cuerpo']; }
             ejecuta_sqls($sql);
		   } 
	
   	   }   while ($row_usuario_listaR = mysql_fetch_assoc($usuario_listaR)); 
   if ($totalRows_usuario_listaR<$cant_mensaje)	 {limpia();}
   } else  { print("\n No hay correos para enviar \n");   limpia(); }

mysql_free_result($usuario_listaR);
}

function enviar_direcciones_erroneas()
  {
    include('../Connections/sitio.php'); 
    require_once ('../Connections/dSendMail2.inc.php');
    //////////////////
    set_time_limit(0);
    ignore_user_abort(true);
    /////////////////////

    mysql_select_db($database_sitio, $sitio);
	$query_direcciones_malas = "SELECT * FROM malas";
	$direcciones_malas = mysql_query($query_direcciones_malas, $sitio) or die(mysql_error());
	$row_direcciones_malas = mysql_fetch_assoc($direcciones_malas);
	$totalRows_direcciones_malas = mysql_num_rows($direcciones_malas);

	if ($totalRows_direcciones_malas<>0)
	 {
    $cuerpo =''; 	
	   do 
	     {
	        $cuerpo=$cuerpo.$row_direcciones_malas['correos_malos'].'|' ;
	     } while ($row_direcciones_malas = mysql_fetch_assoc($direcciones_malas)); 
	 $cuerpo=$totalRows_direcciones_malas.'|'.$cuerpo;

   
    mysql_free_result($direcciones_malas); 
	$sql = "TRUNCATE TABLE malas";
	ejecuta_sqls($sql);
	
 //////////////////////////////////////////////////////////

    mysql_select_db($database_sitio, $sitio);
    $query_admin = "SELECT * FROM acceso WHERE ((id_tipo = 1) or ( id_tipo = 2))";
    $admin = mysql_query($query_admin, $sitio) or die(mysql_error());
    $row_admin = mysql_fetch_assoc($admin);
    $totalRows_admin = mysql_num_rows($admin);  
	   do {
            $m = new dSendMail2;
	    	$m->setFrom($usuario_lista.'@'.$dominio_correo);
			$m->setTo($row_admin['correo']);
			$m->setSubject('Direcciones Erroneas');
			$m->setMessage($cuerpo);
			$m->send(); 
 	    } while ($row_admin = mysql_fetch_assoc($admin)); 
	
	mysql_free_result($admin);
  } else print("\n No existen direcciones erroneas para ser enviadas\n");

}  
  
//main principal
envia_mensajes();
enviar_direcciones_erroneas();
?>
