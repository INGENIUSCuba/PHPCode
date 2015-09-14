<?php 
session_start();
set_time_limit(300);
/*
protocolo a establecer
asunto: crear-centro
cuerpo: id centro|nombre del centro|correo|

asunto: editar-centro
cuerpo: id centro|nuevo nombre del centro|correo nuevo|

asunto: eliminar-centro
cuerpo: ID del centro|

asunto: crear-lista
cuerpo: id lista|nombre de la lista|ID del centro|

asunto: editar-lista
cuerpo: id lista|nuevo nombre de la lista|

asunto: eliminar-lista
Cuerpo: Id lista|

asunto: crear-usuario
Cuerpo: cant_usuarios|correo|nombre|1er apellido|2do apellido|
y tantos usuarios como cant_usuarios de forma seguida
ejem: 2|mario@casa.com|mario|gles|mtinez|pepe@casa.com|jose|ramirez|prieto|

editar-usuario
correo_old|correo_new|nombre|1er apellido|2do apellido|

asunto: eliminar-usuario
Cuerpo: cant_usuarios|correo|
y tantos usuarios como cant_usuarios de forma seguida
ejem: 2|pepe@casa.cu|marios@casa.cu|

asunto: crear-usuario-lista
Cuerpo: cant_usuarios|correo|id de la lista|
y tantos usuarios como cant_usuarios de forma seguida
ejem: 2|mario@casa.com|1|pepe@casa.com|1|


asunto: crear-usuario-usuario-lista
cuerpo: cant_usuarios|correo|nombre|apellido1|apellido2|id lista|
y tantos usuarios como cant_usuarios de forma seguida
ejem: 2|mario@casa.com|mario|gles|mtinez|2|pepe@casa.com|jose|ramirez|prieto|1|


asunto: eliminar-usuario-lista
Cuerpo: cant_usuarios|correo|id de la lista|
y tantos usuarios como cant_usuarios de forma seguida
ejem: 2|mario@casa.com|1|pepe@casa.com|1|

asunto: mover-usuario-lista
Cuerpo: cant_usuarios|correo|id de la lista vieja|id lista nueva|
y tantos usuarios como cant_usuarios de forma seguida
ejem: 2|mario@casa.com|1|3|pepe@casa.com|1|4|

asunto: editar-usuario-listas
cuerpo: cant_listas|correo_old|correo_new|nombre|1er apellido|2do apellido|id_lista|id_lista|
ejem: 2|pedro@gmail.com|pedro1@gmail.com|Pedro|Pérez|García|7|8|


asunto: crear-accesos
Cuerpo: id acceso|correo|clave|nombre|1er apellido|2do apellido|id de la lista|id de tipo de usuario|

asunto: editar-accesos
Cuerpo: id acceso|correo|clave|nombre|1er apellido|2do apellido|id de la lista|id de tipo de usuario|


asunto: eliminar-accesos
Cuerpo: id acceso|

asunto: enviar-correo
Cuerpo: |id de la lista|asunto| TODO LO Q TE DE LA GANA

*/

/////// el php.ini debe soportar imap  Y VERFICAR LA PARTE DE SMTP, PONER EL NOMBRE DE UN SERVIDOR QUE TENGA PERMISO
function envia_mensaje($cuerpo, $asunto, $from, $rpta)
  {
    require_once ('../Connections/dSendMail2.inc.php');
	include('../Connections/sitio.php'); 
	set_time_limit(0);
	ignore_user_abort(true);
	$m = new dSendMail2;
	mysql_select_db($database_sitio, $sitio);
	$query_accesoR = "SELECT * FROM acceso WHERE id_tipo = 1 or  id_tipo = 2";
	$accesoR = mysql_query($query_accesoR, $sitio) or die(mysql_error());
	$row_accesoR = mysql_fetch_assoc($accesoR);
	$totalRows_accesoR = mysql_num_rows($accesoR);

//comentar en sitio  de internet la linea de abajo	
//   $m->sendThroughSMTP($host_lista, 25, $usuario_lista, $clave_lista);
 do {   
	$m->setFrom($usuario_lista.'@'.$dominio_correo);
	$m->setTo($row_accesoR['correo']);
	$m->setSubject('Comando: '.$asunto.' es: '.$rpta.'. Creado por: '.$from);
	$m->setMessage((quoted_printable_decode($cuerpo)));
    $m->send();
   } while ($row_accesoR = mysql_fetch_assoc($accesoR));			
	
mysql_free_result($accesoR);	
  }
  
function cheaquea_correo_y_almacena_matriz (&$cant)
{
     include('../Connections/sitio.php'); 
  	 include('../Connections/cuenta_admin.php'); 
     $emails = imap_search($inbox,'ALL');

    // Informacion del mailbox
    $check = imap_mailboxmsginfo($inbox);
    if ($check) {
//        print "Fecha: "     . $check->Date    . "\n" ;
//        print "Total Mensajes: $check->Nmsgs | Sin Leer: $check->Unread | Recientes: $check->Recent | Eliminados: $check->Deleted \n";
//        print "Tamaño buzón: " . $check->Size . "\n\n" ;
    } else {
        print "imap_check() failed: " . imap_last_error() . "\n";
    }

     $cant=0;
     if($emails) 
	   {
	     foreach($emails as $email_number) 
		   {   
             $overview = imap_fetch_overview($inbox,$email_number,0);
			 $header=imap_headerinfo($inbox,$email_number,0);
		 	 $dato=$header->from[0]->mailbox;
			 $dato = $dato.'@'.$header->from[0]->host;
			 
			 
 //////// realizando consulta con el correo obtenido de revisar correo x correo para verficar si el correo coincide con algun usuario de la tabla acceso///////////////////
	         mysql_select_db($database_sitio, $sitio);
	         $query_acceso = "SELECT * FROM acceso WHERE correo = '".$dato."' limit 1";
	         $acceso = mysql_query($query_acceso, $sitio) or die(mysql_error());
	         $row_acceso = mysql_fetch_assoc($acceso);
	         $totalRows_acceso = mysql_num_rows($acceso);
 /////////////////////////

//// verficando si existe algun correo q coincida ocn el resultado de la consulta se almacena en una matriz/////////
		 if (isset($totalRows_acceso) &&($totalRows_acceso!=0)) 
		   {
			  $matriz[$cant][0] = $dato;
			  $matriz[$cant][1] = $overview[0]->subject;
			  $matriz[$cant][2] = imap_fetchbody($inbox, $email_number,1);
			  $matriz[$cant][3] = $row_acceso['id_lista'];
			  $matriz[$cant][4] = $row_acceso['id_tipo'];
			  $cant++;
		   } 
	mysql_free_result($acceso);
	    }
    }
 imap_close($inbox);
 	 if  (isset($totalRows_acceso) &&($totalRows_acceso!=0))   { return $matriz;} else {return $matriz=0;}
}

//<!------------------------------------ funciones de todas las sentencias SQL     ----------------------------------------------------->
function devuelve_dato(&$cuerpo,&$pos)
  {
    $cuerpo=substr($cuerpo,$pos+1,strlen($cuerpo));
    $pos = strpos($cuerpo, '|');
    $dato=substr($cuerpo, 0, $pos); 
//	echo('<br />'.utf8_decode(quoted_printable_decode($dato)).'<br />');
     return (quoted_printable_decode($dato));		
  }
function ejecuta_sql($sql)
  {
    include('../Connections/sitio.php'); 
    $result=$sql;
    $miresult = mysql_query($result,$sitio);  
//	print("\n Cuerpo del mensaje CORRECTO, Sentencia Ejecutada. \n");
  }
 
function verifica_crear_centro($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==3)
	 {
	    $pos = strpos($cuerpo, '|');
	    $id_centro =  substr($cuerpo, 0, $pos);  
		$centro=devuelve_dato($cuerpo,$pos);
		$correo=devuelve_dato($cuerpo,$pos);
		
		$sql="INSERT INTO centro (id_centro,nombre,correo) VALUES ('".$id_centro."','".$centro."','".$correo."');";
		ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }
 
 
function verifica_editar_centro($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==3)
	 {
	    $pos = strpos($cuerpo, '|');
	    $id_centro =  substr($cuerpo, 0, $pos);  
		$centro_new=devuelve_dato($cuerpo,$pos);
		$correo=devuelve_dato($cuerpo,$pos);
		
		$sql ="UPDATE centro SET nombre = '".$centro_new."', correo ='".$correo."' WHERE centro.id_centro =".$id_centro;
		ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 } 
 
function verifica_eliminar_centro($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==1)
	 {
	    $pos = strpos($cuerpo, '|');
	    $id_centro =  substr($cuerpo, 0, $pos);  
		
		$sql = "DELETE FROM centro WHERE id_centro = ".$id_centro;
		ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 } 


function verifica_inserta_crear_lista($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
//   echo($cuerpo);
    if ($cant_palo==3)
	 {
	    $pos = strpos($cuerpo, '|');
	    $id_lista =  substr($cuerpo, 0, $pos);  
		$lista=devuelve_dato($cuerpo,$pos);
		$centro=devuelve_dato($cuerpo,$pos);
		
		$sql="INSERT INTO lista (id_lista, id_centro, nombre)	  VALUES ('".$id_lista."','".$centro."','".$lista."');";
		ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_editar_lista($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==2)
	 {
	    $pos = strpos($cuerpo, '|');
	    $id_lista =  substr($cuerpo, 0, $pos);  
		$lista_new=devuelve_dato($cuerpo,$pos);
	    $sql ="UPDATE lista SET nombre = '".$lista_new."' WHERE lista.id_lista =".$id_lista;
   	    ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_elimina_eliminar_lista($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==1)
	 {
	    $pos = strpos($cuerpo, '|');
	    $id_lista =  substr($cuerpo, 0, $pos);  
		
		$sql = "DELETE FROM lista WHERE id_lista = ".$id_lista;
		ejecuta_sql($sql);
		return true;
	 } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }

function verifica_inserta_usuario($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_usuarios =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_usuarios*4)+1)
	 {
      for ( $i = 0 ; $i < $cant_usuarios ; $i ++) 
	   { 
   	     $correo_u=devuelve_dato($cuerpo,$pos);
		 $nombre=devuelve_dato($cuerpo,$pos);
		 $ape1=devuelve_dato($cuerpo,$pos);
		 $ape2=devuelve_dato($cuerpo,$pos);	 
		 $sql = "INSERT INTO usuarios (correo, nombre, apellido1, apellido2) VALUES ('".$correo_u."', '".$nombre."', '".$ape1."', '".$ape2."')";
		 ejecuta_sql($sql);
	   } 	 return true;
  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_editar_usuario($cuerpo)
 {
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==5)
	 { 
	   $pos = strpos($cuerpo, '|');
	   $correo_u=substr($cuerpo, 0, $pos);  
       $correo_new=devuelve_dato($cuerpo,$pos);
	   $nombre=devuelve_dato($cuerpo,$pos);
	   $ap1=devuelve_dato($cuerpo,$pos);
	   $ap2=devuelve_dato($cuerpo,$pos);
       $sql ="UPDATE usuarios SET correo ='".$correo_new."', nombre = '".$nombre."',apellido1 = '".$ap1."',apellido2 = '".$ap2."' WHERE usuarios.correo = '".$correo_u."'";
		ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_eliminar_usuario($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_usuarios =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_usuarios+1))
	 {
      for ( $i = 0 ; $i < $cant_usuarios ; $i ++) 
	   { 
	     $correo_u=devuelve_dato($cuerpo,$pos);
    	 $sql = "DELETE FROM usuarios WHERE correo = '".$correo_u."'";
		 ejecuta_sql($sql);
		} return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }

function verifica_crear_usuario_usuario_lista($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_usuarios =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_usuarios*5)+1)
	 {
      for ( $i = 0 ; $i < $cant_usuarios ; $i ++) 
	   { 
   	     $correo_u=devuelve_dato($cuerpo,$pos);
		 $nombre=devuelve_dato($cuerpo,$pos);
		 $ape1=devuelve_dato($cuerpo,$pos);
		 $ape2=devuelve_dato($cuerpo,$pos);	 
		 $id_lista=devuelve_dato($cuerpo,$pos); 
		 $sql ="INSERT INTO usuarios (correo, nombre, apellido1, apellido2) VALUES ('".$correo_u."', '".$nombre."','".$ape1."', '".$ape2."')";
		 ejecuta_sql($sql);
     	 $sql ="INSERT INTO usuario_lista (id_lista, correo) VALUES (".$id_lista.", '".$correo_u."')";
		 ejecuta_sql($sql);
	   } 	 return true;
  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }

function verifica_editar_usuario_listas($cuerpo)
  {
    $pos = strpos($cuerpo, '|');
    $cant_listas =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==(6+$cant_listas))
	 {
       $correo_old=devuelve_dato($cuerpo,$pos);
	   $correo_new=devuelve_dato($cuerpo,$pos);
	   $nombre=devuelve_dato($cuerpo,$pos);
	   $ape1=devuelve_dato($cuerpo,$pos);
	   $ape2=devuelve_dato($cuerpo,$pos);	 
	   echo('|||'.$correo_old.' '.$correo_new.' '.$nombre.' '.$ape1.' '.$ape2);
	   $sql = "DELETE FROM usuarios WHERE correo = '".$correo_old."'";
	   ejecuta_sql($sql);
       $sql ="INSERT INTO usuarios (correo, nombre, apellido1, apellido2) VALUES ('".$correo_new."', '".$nombre."','".$ape1."', '".$ape2."')";
	   ejecuta_sql($sql);
	  
      for ( $i = 0 ; $i < $cant_listas ; $i ++) 
	   { 
	   	 $id_lista=devuelve_dato($cuerpo,$pos); 
//		 echo('<br />|'.$id_lista);
 	     $sql ="INSERT INTO usuario_lista (id_lista, correo) VALUES (".$id_lista.", '".$correo_new."')";
		 ejecuta_sql($sql);
       } return true;
     } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
  }

function verifica_inserta_usuario_lista($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_usuarios =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_usuarios*2)+1)
	 {
      for ( $i = 0 ; $i < $cant_usuarios ; $i ++) 
	    {
  	      $correo_u =  devuelve_dato($cuerpo, $pos);
   	      $id_lista=devuelve_dato($cuerpo,$pos);
  		  $sql = "INSERT INTO usuario_lista (correo, id_lista) VALUES ('".$correo_u."', '".$id_lista."')";
		  ejecuta_sql($sql);
		}return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }

 
function verifica_eliminar_usuario_lista($cuerpo)
 {
// echo($cuerpo);
    $pos = strpos($cuerpo, '|');
    $cant_usuarios =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_usuarios*2)+1)
	 {
      for ( $i = 0 ; $i < $cant_usuarios ; $i ++) 
	   { 
	     $correo_u=devuelve_dato($cuerpo,$pos);
//		 echo('<br />'.$correo_u);
	     $id_lista=devuelve_dato($cuerpo,$pos);

    	$sql = "DELETE FROM usuario_lista WHERE correo = '".$correo_u."' and id_lista =".$id_lista;
//		echo('<br />'.$sql);
		ejecuta_sql($sql);
		}return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_crear_accesos($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==8)
	 {
	    $pos = strpos($cuerpo, '|');
		$id_acceso =  substr($cuerpo, 0, $pos);
	    $correo_u =  devuelve_dato($cuerpo,$pos);
	    $clave=devuelve_dato($cuerpo,$pos);
 	    $nombre=devuelve_dato($cuerpo,$pos);
 	    $apellido1=devuelve_dato($cuerpo,$pos);
 	    $apellido2=devuelve_dato($cuerpo,$pos);				 
		$id_lista=devuelve_dato($cuerpo,$pos);
 	    $id_tipo=devuelve_dato($cuerpo,$pos); 

 	    $sql = "INSERT INTO acceso (id_acceso, correo, clave, nombre, apellido1, apellido2, id_lista, id_tipo)
		 VALUES ('".$id_acceso."','".$correo_u."', '".$clave."', '".$nombre."','".$apellido1."','".$apellido2."', '".$id_lista."' , '".$id_tipo."')";		
		 ejecuta_sql($sql);
		 return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_editar_accesos($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==8)
	 {
	    $pos = strpos($cuerpo, '|');
		$id_acceso =  substr($cuerpo, 0, $pos);
	    $correo_u =  devuelve_dato($cuerpo,$pos);
	    $clave=devuelve_dato($cuerpo,$pos);
	    $nombre=devuelve_dato($cuerpo,$pos);
 	    $apellido1=devuelve_dato($cuerpo,$pos);
 	    $apellido2=devuelve_dato($cuerpo,$pos);			
		$id_lista=devuelve_dato($cuerpo,$pos);
		$id_tipo=devuelve_dato($cuerpo,$pos); 
		$sql ="UPDATE acceso SET correo = '".$correo_u."', clave =  '".$clave."' , nombre = '".$nombre."', apellido1 = '".$apellido1."', apellido2 = '".$apellido2."', id_lista = '".$id_lista."',id_tipo = '".$id_tipo."' WHERE acceso.id_acceso =".$id_acceso;
    	ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_eliminar_accesos($cuerpo)
 {
   $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==1)	
	 {
	    $pos = strpos($cuerpo, '|');
	    $id_acceso =  substr($cuerpo, 0, $pos);
    	$sql = "DELETE FROM acceso WHERE id_acceso = ".$id_acceso;
		ejecuta_sql($sql);
		return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }

function verifica_mover_usuario_lista($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_usuarios =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_usuarios*3)+1)
	 {
      for ( $i = 0 ; $i < $cant_usuarios ; $i ++) 
	    {
  	      $correo_u =  devuelve_dato($cuerpo, $pos);
 	      $id_lista_vieja=devuelve_dato($cuerpo,$pos);
 	      $id_lista_nueva=devuelve_dato($cuerpo,$pos);		
		  $sql="UPDATE usuario_lista SET id_lista = '".$id_lista_nueva."' WHERE id_lista  = '".$id_lista_vieja."' AND correo = '".$correo_u."'";
		  ejecuta_sql($sql);
		 } return true;
	  } else {print("\n Cuerpo del mensaje incorrecto \n"); return false;}
  
 } 
 
 //////////////////
 function ejecuta_sql2($sql)
  {
    include('../Connections/sitio.php'); 
    $result=$sql;
    mysql_select_db($database_sitio, $sitio);
    $miresult = mysql_query($result,$sitio);  
//	print("\n Cuerpo del mensaje CORRECTO, Sentencia Ejecutada. \n");
  }
  
  
function devuelve_dato2(&$cuerpo,&$pos)
  {
    $cuerpo=substr($cuerpo,$pos+1,strlen($cuerpo));
    $pos = strpos($cuerpo, '|');
    $dato=substr($cuerpo, 0, $pos); 
//	echo('<br />'.utf8_decode(quoted_printable_decode($dato)).'<br />');
     return (quoted_printable_decode($dato));		
     return ($dato);		
  }
  
function crear_centro($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_centros =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_centros*3)+1)
	 {
      for ( $i = 0 ; $i < $cant_centros ; $i ++) 
	   { 
   	     $id=devuelve_dato2($cuerpo,$pos);
		 $nombre=devuelve_dato2($cuerpo,$pos);
		 $correo=devuelve_dato2($cuerpo,$pos);
		 $sql = "INSERT INTO centro (id_centro, nombre, correo) VALUES ('".$id."', '".$nombre."', '".$correo."')";
//		 echo($sql."<br />");
		 ejecuta_sql($sql);
	   } 	 return true;
  } else {print("\n Cuerpo del mensaje incorrecto centro \n"); return false;}
 }
 
 
 function crear_lista($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_listas =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_listas*3)+1)
	 {
      for ( $i = 0 ; $i < $cant_listas ; $i ++) 
	   { 
   	     $id=devuelve_dato2($cuerpo,$pos);
		 $nombre=devuelve_dato2($cuerpo,$pos);
		 $idcentro=devuelve_dato2($cuerpo,$pos);
		 $sql = "INSERT INTO lista (id_lista, nombre, id_centro) VALUES ('".$id."', '".$nombre."', '".$idcentro."')";
	//	 echo($sql."<br />");
		 ejecuta_sql($sql);
	   } 	 return true;
  } else {print("\n Cuerpo del mensaje incorrecto lista \n"); return false;}
 }
 
  
 function crear_usuarios($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_listas =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_listas*4)+1)
	 {
      for ( $i = 0 ; $i < $cant_listas ; $i ++) 
	   { 
   	     $correo_u=devuelve_dato2($cuerpo,$pos);
		 $nombre=devuelve_dato2($cuerpo,$pos);
		 $ape1=devuelve_dato2($cuerpo,$pos);
		 $ape2=devuelve_dato2($cuerpo,$pos);	 
		 $sql = "INSERT INTO usuarios (correo, nombre, apellido1, apellido2) VALUES ('".$correo_u."', '".$nombre."', '".$ape1."', '".$ape2."')";
		// echo($sql."<br />");
		 ejecuta_sql($sql);
	   } 	 return true;
  } else {print("\n Cuerpo del mensaje incorrecto usuario \n"); return false;}
 }
 
 function crear_usuarios_lista($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
	$sql_total="";
    $cant_listas =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_listas*2)+1)
	 {
      for ( $i = 0 ; $i < $cant_listas ; $i ++) 
	   { 
   	    $correo_u =  devuelve_dato2($cuerpo, $pos);
   	      $id_lista=devuelve_dato2($cuerpo,$pos);
  		  $sql = "INSERT INTO usuario_lista (correo, id_lista) VALUES ('".$correo_u."', '".$id_lista."'); ";
//		  $sql_total = $sql_total.$sql;
	//	 echo($sql."<br />");
		 ejecuta_sql($sql);
	   } 
	//    echo($sql_total);
	  //  ejecuta_sql($sql_total);
	   	 return true;
  } else {print("\n Cuerpo del mensaje incorrecto usuario - lista \n"); return false;}
 }
 
 
 
 function crear_accesos($cuerpo)
 {
    $pos = strpos($cuerpo, '|');
    $cant_accesos =  substr($cuerpo, 0, $pos);  
    $cant_palo = substr_count($cuerpo, '|');  
    if ($cant_palo==($cant_accesos*8)+1)
	 {
      for ( $i = 0 ; $i < $cant_accesos ; $i ++) 
	   { 
        $id_acceso =  devuelve_dato2($cuerpo,$pos);
	    $correo_u =  devuelve_dato2($cuerpo,$pos);
	    $clave=devuelve_dato2($cuerpo,$pos);
 	    $nombre=devuelve_dato2($cuerpo,$pos);
 	    $apellido1=devuelve_dato2($cuerpo,$pos);
 	    $apellido2=devuelve_dato2($cuerpo,$pos);				 
		$id_lista=devuelve_dato2($cuerpo,$pos);
 	    $id_tipo=devuelve_dato2($cuerpo,$pos); 

 	    $sql = "INSERT INTO acceso (id_acceso, correo, clave, nombre, apellido1, apellido2, id_lista, id_tipo)
		 VALUES ('".$id_acceso."','".$correo_u."', '".$clave."', '".$nombre."','".$apellido1."','".$apellido2."', '".$id_lista."' , '".$id_tipo."')";		
	// echo($sql."<br />");
		 ejecuta_sql($sql);
	   } 	 return true;
  } else {print("\n Cuerpo del mensaje incorrecto accesos \n"); return false;}
 }
 
 
 function verifica_rehacer_bd_ok($cuerpo)
 {
   $sql="DELETE FROM centro";
   ejecuta_sql2($sql);
   $sql="DELETE FROM usuarios";
   ejecuta_sql2($sql);
   
    $pos = strpos($cuerpo, 'Listas:');
    $centro =  substr($cuerpo, 8, $pos-8);  
    crear_centro($centro);
	
	
    $pos1 = strpos($cuerpo, 'Accesos:');
    $lista =  substr($cuerpo, $pos+7, $pos1-$pos-7);  
	crear_lista($lista);

    $pos2 = strpos($cuerpo, 'Usuarios:');
    $accesos =  substr($cuerpo, $pos1+8, $pos2-$pos1-8);  
	crear_accesos($accesos);
	
    $pos3 = strpos($cuerpo, 'Usuarios_listas:');
    $usuarios =  substr($cuerpo, $pos2+9, $pos3-$pos2-9);  
	crear_usuarios($usuarios);

    $pos4 = strlen($cuerpo);
    $usuarios_lista =  substr($cuerpo, $pos3+16, $pos4-$pos3);  
	crear_usuarios_lista($usuarios_lista);

 //  echo($centro."<br>");
 //  echo($lista."<br>");
 //  echo($accesos."<br>");
 //  echo($usuarios."<br>");
 //  echo($usuarios_lista."<br>");
 } 

 
 //////////////////
 function verifica_rehacer_bd($cuerpo)
 {
 verifica_rehacer_bd_ok($cuerpo);
 return true;
 } 
 
// <!------------------------------------------------- FIN de las funciones de todas las sentencias SQL     -------------------------------------------------------------------->

function verifica_enviar_correo($asunto,$cuerpo)
 {
   	  $cant_palo = substr_count($cuerpo, '|');
      if (($cant_palo==6) || ($cant_palo==3))
	    {
		   print("\n Correo con cuerpo: enviar-correo, CORRECTO [ en espera de ser procesado... ] \n");
		   return true;
		} else { print("\n Cuerpo del mensaje incorrecto \n"); return false;}
 }


function verifica_datos_cuerpo($asunto,$cuerpo,$from)
  { 
   if ($asunto=='crear-centro')                 { if (verifica_crear_centro($cuerpo))                {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='editar-centro')                { if (verifica_editar_centro($cuerpo))               {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }	   
   if ($asunto=='eliminar-centro')              { if (verifica_eliminar_centro($cuerpo))             {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='crear-lista')                  { if (verifica_inserta_crear_lista($cuerpo))         {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='editar-lista')                 { if (verifica_editar_lista($cuerpo))                {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='eliminar-lista')               { if (verifica_elimina_eliminar_lista($cuerpo))      {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='crear-usuario')                { if (verifica_inserta_usuario($cuerpo))             {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='editar-usuario')               { if (verifica_editar_usuario($cuerpo))              {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='eliminar-usuario')             { if (verifica_eliminar_usuario($cuerpo))            {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='crear-usuario-usuario-lista')  { if (verifica_crear_usuario_usuario_lista($cuerpo)) {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='crear-usuario-lista')          { if (verifica_inserta_usuario_lista($cuerpo))       {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='eliminar-usuario-lista')       { if (verifica_eliminar_usuario_lista($cuerpo))      {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='mover-usuario-lista')          { if (verifica_mover_usuario_lista($cuerpo))         {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='editar-usuario-listas')        { if (verifica_editar_usuario_listas($cuerpo))       {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='crear-accesos')                { if (verifica_crear_accesos($cuerpo))               {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='eliminar-accesos')             { if (verifica_eliminar_accesos($cuerpo))            {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='editar-accesos')               { if (verifica_editar_accesos($cuerpo))              {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }
   if ($asunto=='rehacer-bd') 		        { if (verifica_rehacer_bd($cuerpo))                  {envia_mensaje($cuerpo, $asunto, $from,'Correcto');} else {envia_mensaje($cuerpo, $asunto, $from,'Incorrecto');}; }   
   if ($asunto=='enviar-correo')                { verifica_enviar_correo($asunto,$cuerpo); }
  }


function verifica_comando_correcto ($matriz,$cant,&$cant_ok)
  {
     include('../Connections/sitio.php'); 
//// verficando si el comando emitido es correcto
	$sirven = array();
	 for ( $i = 0 ; $i < $cant ; $i ++) 
		{
			mysql_select_db($database_sitio, $sitio);
			$query_comando = "SELECT * FROM comando WHERE tipo_comando = ".$matriz[$i][4];
			$comando = mysql_query($query_comando, $sitio) or die(mysql_error());
			$row_comando = mysql_fetch_assoc($comando);
			$totalRows_comando = mysql_num_rows($comando);
			$paso='No';
				do {
				     if ($row_comando['hacer'] == $matriz[$i][1]) { $paso='Si';}
				   } while ($row_comando = mysql_fetch_assoc($comando)); 

			mysql_free_result($comando);
		if ($paso=='Si') {print("el correo de ".$matriz[$i][0]." con asuto: ".$matriz[$i][1]." es un:[asunto correcto] para ser procesado \n");  array_push($sirven,$i); $cant_ok++; }
		else {  print("el correo de ".$matriz[$i][0]." con asuto: ".$matriz[$i][1]." es INCORRECTO o de usuario sin privilegios \n");  }
		}
	 return $sirven;	
}		

//////////////////////// principal ////////////////////////////////////
$matriz=cheaquea_correo_y_almacena_matriz($cant);
if ($matriz!=0)
 {
   $sirven=verifica_comando_correcto($matriz,$cant,$cant_ok);
    for ( $i = 0 ; $i < $cant_ok ; $i ++) 
      { 
	     verifica_datos_cuerpo($matriz[$sirven[$i]][1],$matriz[$sirven[$i]][2], $matriz[$sirven[$i]][0]);  		 
	  }	 
  } else print("\n No existen correos CORRECTOS para ser procesados !!! \n"); 
?>
