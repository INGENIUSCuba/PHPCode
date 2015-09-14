<?php include('../Connections/sitio.php'); 
  	 include('../Connections/cuenta_admin.php'); 

function envia_mensaje_profe($asunto, $from, $rpta)
  {
    require_once ('../Connections/dSendMail2.inc.php');
	include('../Connections/sitio.php'); 
	set_time_limit(0);
	ignore_user_abort(true);
	$m = new dSendMail2;
//    $m->sendThroughSMTP($host_lista, 25, $usuario_lista, $clave_lista);
	$m->setFrom($usuario_lista.'@'.$dominio_correo);
	$m->setTo($from);
	$m->setSubject('Envio: '.$rpta.' con asunto: '.(quoted_printable_decode($asunto)));
	$m->setMessage('Envio: '.$rpta.' con asunto: '.(quoted_printable_decode($asunto)));
//	$m->setCharset('ISO-8859-1');
    $m->send();
	 
  }

function salva_atachado($mailbox, $msg_number, $dir,$nombre)
  {
     $email_file = $dir."/".$nombre.".eml";
     imap_savebody  ($mailbox, $email_file, $msg_number);
     $handle = fopen($email_file,"r");
     $text = fread($handle,filesize($email_file));
     fclose($handle);
////////////////////
 $archivo = $dir."/".$nombre.'.eml';
  $id = fopen($archivo, 'r');
  $contenido = fread($id, filesize($archivo));
  $str = str_replace("X-Spam-Score: 2.9", "", $contenido);
//  $str = str_replace("X-Spam-Score-Int: 29", "", $str); 
//  $str = str_replace("X-Spam-Bar: ++", "", $str);
  fclose($id);
  $fp = fopen($dir."/".$nombre.'.eml', 'w');
  fwrite($fp, $str);
  fclose($fp);
//////////////////////

     return $text;
  }

function devuelve_datos(&$cuerpo,&$pos)
  {
    $cuerpo=substr($cuerpo,$pos+1,strlen($cuerpo));
    $pos = strpos($cuerpo, '|');
    $dato=substr($cuerpo, 0, $pos); 
    return $dato;		
  }
  
function ejecutas_sqls($sql)
  {
    include('../Connections/sitio.php'); 
    $result=$sql;
    $miresult = mysql_query($result,$sitio);  
	print("\n Cuerpo del mensaje CORRECTO, Sentencia Ejecutada. \n");
  }  

//////////////////////////////////////////main////////////////////////////////////////////////////
$num = imap_num_msg($inbox); 
$emails = imap_search($inbox,'ALL');
 
if($emails) {
  foreach($emails as $email_number) {  
    $overview = imap_fetch_overview($inbox,$email_number,0); 
	
 	$header=imap_headerinfo($inbox,$email_number,0);
    $dato=$header->from[0]->mailbox;
	$dato = $dato.'@'.$header->from[0]->host;
	
	mysql_select_db($database_sitio, $sitio);
	$query_accesosR = "SELECT * FROM acceso WHERE correo = '".$dato."'";
	$accesosR = mysql_query($query_accesosR, $sitio) or die(mysql_error());
	$row_accesosR = mysql_fetch_assoc($accesosR);
	$totalRows_accesosR = mysql_num_rows($accesosR);
	  	 if (isset($totalRows_accesosR) &&($totalRows_accesosR!=0) && ($overview[0]->subject=='enviar-correo')) 
		    { 
			   print("Correo analizado: Asunto CORRECTO y quien  Envia CORRECTO \n"); 
			     $cuerpo = imap_fetchbody($inbox, $email_number,1);
			  			  
			  $cant_palo = substr_count($cuerpo, '|');
                 if (($cant_palo==6) || ($cant_palo==3))
                	 {
                 	    $pos = strpos($cuerpo, '|');
                	    $id_lista =  substr($cuerpo, 0, $pos);
				        $id_lista=devuelve_datos($cuerpo,$pos); 
						if (($id_lista==$row_accesosR['id_lista']) || ($row_accesosR['id_tipo']==1) || ($row_accesosR['id_tipo']==2))
							  {
					   	        $asunto=devuelve_datos($cuerpo,$pos);
								$nombre = imap_header($inbox, $email_number); // get first mails header
								$nombre = $nombre->udate;

								$ata = salva_atachado($inbox,$email_number,'atachados',$nombre);
								$sql = "INSERT INTO correos_lista (fecha, asunto, id_lista, remitente,cuerpo,origen) VALUES ('".date("Y-m-d G:i:s")."', '".(quoted_printable_decode($asunto))."',".$id_lista.",'".$username."','".$nombre.".eml','".$dato."')";
//								$sql = "INSERT INTO correos_lista (fecha, asunto, id_lista, remitente,cuerpo) VALUES ('".date("Y-m-d G:i:s")."', '".utf8_decode($asunto)."',".$id_lista.",'".$dato."','".$nombre.".eml')";
					   		    ejecutas_sqls($sql);
								envia_mensaje_profe($asunto, $dato, 'Correcto');
							} else {print("\n Este usuario NO puede enviar a esta lista\n");}
					  } else {print("\n Cuerpo del mensaje incorrecto \n"); envia_mensaje_profe('correo incorrecto', $dato, 'Incorrecto');}
		   
			} else {print("\n No es un correo para ser procesado o es de un usuario que no existe o no tiene privilegios\n");}
	mysql_free_result($accesosR);
	}	
}
imap_close($inbox);
?>
