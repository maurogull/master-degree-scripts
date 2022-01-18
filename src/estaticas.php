<?php

$cn = mysqli_connect("localhost", "root", "", "metricas");



/*****************************************************



	OJO !!!!!!    

	HAY UNAS FECHAS EN LA CONSULTA QUE BUSCA LOS 
	BUGFIXES DEL PERIODO.
	ESTE SCRIPT SE EJECUTA CON EL REPOSITORIO ACOMODADO
	EN CIERTO PUNTO PARA COMPUTAR LAS MÉTRICAS ESTÁTICAS
	DE ESE MOMENTO DEL CÓDIGO, PERO TAMBIÉN GENERA EN EL
	RESULTADO UNA COLUMNA CON LOS BUGFIXES DEL PERÍODO, QUE
	SE SACAN DE LA BASE DE DATOS GENERADA PARA LAS MÉTRICAS
	DE CAMBIO.
	ESE PERÍODO DE BUGFIXES ESTÁ HARDCODEADO EN LA CONSULTA, 
	ALREDEDOR DE LA LINEA 600, EN EL BUCLE FINAL QUE GENERA 
	LA SALIDA AL BUFFER PARA LLEVAR A EXCEL.



*******************************************************/



define('MEDIAWIKI', 'true');
define('ROOTPATH', 'C:\\Users\\Mauro\\Desktop\\git mw\\mediawiki');
//define('ROOTPATH', 'C:\\Users\\Mauro\\Desktop\\testing metricas');
//define('PHPBINPATH', 'C:\\xampp2\\php\\php.exe');

//require(ROOTPATH.'\\AutoLoader.php');

spl_autoload_register(function ($classname) {
	global $classesfound;
	foreach ($classesfound as $c) {
		if ($c['name']==$classname) {
			require_once $c['file'];
		}
	}
});



$php_native_types = array("bool", "int", "float", "string", "object", "array", "mixed", "void", "number", "stdClass");



$_filesfound = array();
discover_files(ROOTPATH.'\\includes');
discover_files(ROOTPATH.'\\vendor'); //1.25 en adelante, por empezar a usar Composer

$classesfound = discover_classes($_filesfound);

$system_metrics = array();
$system_metrics['Robiolo_1'] = 0;
$system_metrics['Robiolo_2'] = 0;
$system_metrics['Robiolo_3'] = 0;
$system_metrics['Robiolo_4'] = 0;
$system_metrics['Robiolo_5'] = 0;
$system_metrics['Robiolo_6'] = 0;
$system_metrics['Robiolo_7'] = 0;
$system_metrics['Robiolo_8'] = 0;
$system_metrics['Robiolo_9'] = 0;
$system_metrics['Robiolo_10'] = 0;
$system_metrics['Robiolo_11'] = 0;
$system_metrics['System_LOC'] = 0;


foreach($classesfound as $i=>$class) {
	$classesfound[$i]['parents'] = class_parents($class['name']);
	$classesfound[$i]['implements'] = class_implements($class['name']);
}


foreach($classesfound as $i=>$class) {
	try {
		$refl = new ReflectionClass($class['name']); 
	}
	catch(Exception $e) {
		continue;
	}

	$classesfound[$i]['is_interface'] = $refl->isInterface();
	$classesfound[$i]['namespace'] = $refl->getNamespaceName();

	//------ LOC

	$classesfound[$i]['LOC'] = get_class_LOC($class['file']);
	if($classesfound[$i]['is_external']=='N') $system_metrics['System_LOC'] += $classesfound[$i]['LOC'];

	//------ fin LOC




	//------ Robiolo de sistema


	//Robiolo 1 y 2
	if(!$refl->isInterface() && $classesfound[$i]['is_external']=='N') $system_metrics['Robiolo_1']++;
	if($refl->isInterface() && $classesfound[$i]['is_external']=='N') $system_metrics['Robiolo_2']++;


	//Robiolo 3, 4, 5
	if(!isset($clases_externas_extendidas)) $clases_externas_extendidas = array();
	if(!isset($interfaces_externas_extendidas)) $interfaces_externas_extendidas = array();
	if($classesfound[$i]['is_external']=='Y') {
		foreach($classesfound as $j=>$class2) {
			if($i==$j) continue;
			if($class2['is_external']=='Y') continue; //contamos solo las internas que extienden externas

			$refl2 = new ReflectionClass($class2['name']); 

			if($refl2->isSubclassOf($classesfound[$i]['name'])) { //la segunda extiende la primera?
			//if(in_array($classesfound[$i]['name'], $class2['parents'])) { //la segunda extiende la primera?
				if($refl->isInterface()) @$interfaces_externas_extendidas[$classesfound[$i]['name']]++;
				else @$clases_externas_extendidas[$classesfound[$i]['name']]++;
			}

			if(!$refl2->isInterface() && $refl->isInterface() && $refl2->implementsInterface($classesfound[$i]['name'])) $system_metrics['Robiolo_5']++;
		}

		$system_metrics['Robiolo_3'] = count($clases_externas_extendidas);
		$system_metrics['Robiolo_4'] = count($interfaces_externas_extendidas);	

	}


	//Robiolo 6, 7
	if(!isset($clases_raiz_extendidas)) $clases_raiz_extendidas = array();
	if(!isset($interfaces_raiz_extendidas)) $interfaces_raiz_extendidas = array();

	if($classesfound[$i]['is_external']=='N') { //es interna
		if(
			(!$refl->isInterface() && count($classesfound[$i]['parents'])==0) 
			|| 
			($refl->isInterface() && count($classesfound[$i]['implements'])==0)       ) {  //es raíz

			foreach($classesfound as $j=>$class2) {
				if($i==$j) continue;
				if($class2['is_external']=='Y') continue;

				$refl2 = new ReflectionClass($class2['name']); 


				if(!$refl->isInterface() && !$refl2->isInterface() && 
					$refl2->isSubclassOf($classesfound[$i]['name'])) @$clases_raiz_extendidas[$classesfound[$i]['name']]++;

				if($refl->isInterface() && $refl2->isInterface() && 
					$refl2->isSubclassOf($classesfound[$i]['name'])) @$interfaces_raiz_extendidas[$classesfound[$i]['name']]++;

			}

		}


		$system_metrics['Robiolo_6'] = count($clases_raiz_extendidas);
		$system_metrics['Robiolo_7'] = count($interfaces_raiz_extendidas);

	}


	//Robiolo 8
	if($classesfound[$i]['is_external']=='N') { //es interna
		if(!$refl->isInterface() && count($classesfound[$i]['parents'])>0 ) {  //clase no raíz, veo la profundidad
			$raiz = end($classesfound[$i]['parents']);
			$raiz_es_interna = true;

			foreach($classesfound as $j=>$class2) {
				if($i==$j) continue;
				if($class2['name']!=$raiz) continue;

				if($class2['is_external']=='Y') $raiz_es_interna = false;
				else $raiz_es_interna = true;
			}

			if($raiz_es_interna && count($classesfound[$i]['parents']) > $system_metrics['Robiolo_8']) {  //encontramos mayor
				$system_metrics['Robiolo_8'] = count($classesfound[$i]['parents']); //cantidad de clases de la rama - 1
			}

		}  
	}


	//Robiolo 9
	if($classesfound[$i]['is_external']=='N') { //es interna
		if($refl->isInterface() && count($classesfound[$i]['implements'])>0 ) {  //interfaz no raíz, veo la profundidad
			$raiz = end($classesfound[$i]['implements']);
			$raiz_es_interna = true;

			foreach($classesfound as $j=>$class2) {
				if($i==$j) continue;
				if($class2['name']!=$raiz) continue;

				if($class2['is_external']=='Y') $raiz_es_interna = false;
				else $raiz_es_interna = true;
			}

			if($raiz_es_interna && count($classesfound[$i]['implements']) > $system_metrics['Robiolo_9']) {  //encontramos mayor
				$system_metrics['Robiolo_9'] = count($classesfound[$i]['implements']); //cantidad de interfaces de la rama - 1
			}

		}  
	}





	//Robiolo 10, 11
	if($classesfound[$i]['is_external']=='N') { //es interna
		if(!$refl->isInterface() && count($classesfound[$i]['parents'])==0 ) {  //es clase raíz
			if(!$refl->isAbstract()) $system_metrics['Robiolo_10']++;
			if(!$refl->isAbstract() && count($classesfound[$i]['implements'])!=0) $system_metrics['Robiolo_11']++;

		}

	}

	//------ fin Robiolo de sistema






	//------ Robiolo de clase

	$classesfound[$i]['Robiolo_12'] = 0;
	$classesfound[$i]['Robiolo_13'] = 0;
	$classesfound[$i]['Robiolo_14'] = 0;
	$classesfound[$i]['Robiolo_15'] = 0;

	if($classesfound[$i]['is_external']=='N') {


		$methods = $refl->getMethods();

		



		//Robiolo 15/16
		foreach($methods as $m) {	
			$classesfound[$i]['Robiolo_15']++; //incluye heredados
	 	}


		//Robiolo 14
		$puntos_y_comas = 0;
		$metodos = 0;
		foreach($methods as $m) {
			if($m->class != $class['name']) continue;  //los heredados no

			$metodos++;
			$lines = get_method_lines($class['file'], $m->getStartLine(), $m->getEndLine());
			
			foreach($lines as $line) {  //vamos linea por linea buscando ;
				$puntos_y_comas += substr_count($line, ';');
			}

	 	}
		if($metodos>0) $classesfound[$i]['Robiolo_14'] = number_format($puntos_y_comas / $metodos, 2, ',', '');



		//Robiolo 13
		foreach($methods as $m) {
			if($m->class != $class['name']) continue;  //los heredados no

			$lines = get_method_lines($class['file'], $m->getStartLine(), $m->getEndLine());
			
			foreach($lines as $line) { 
				$es_mensaje_polimorf = false;
				preg_match('/\$([a-zA-Z0-9_]+)->([a-zA-Z0-9_]+)\s*\(/', $line, $result);
				if (count($result) == 0) continue;  //no hay llamada a método en esta línea, salteamos

				$variable = $result[1];
				$metodo = $result[2];

				if($variable=='this') continue;
				
				//me fijo si la variable es un parámetro de este método
				foreach($m->getParameters() as $parameter) {
					if($parameter->getName() == $variable) { //sí, lo es
						if(!$parameter->hasType()) break; //el parametro no tiene tipo declarado

						$paramtype = $parameter->getType();
						if($paramtype->isBuiltin()) break; //el tipo declarado es uno interno de PHP
						
						try{
							$refl3 = new ReflectionClass($paramtype->__toString());
						}
						catch(Exception $e) {
							break;
						}
						
						if($refl3->isAbstract() || $refl3->isInterface()) {  //el tipo es clase abstracta o interfaz, bingo!
							$es_mensaje_polimorf = true;
							break;
						}
						else {
							//nos fijamos si él método es heredado, entonces tb sería mensaje polimorf!
							$metodosDestinatario = $refl3->getMethods();
							foreach($metodosDestinatario as $md) {
								if($md->name == $metodo && $md->class != $refl3->getName()) {
									$es_mensaje_polimorf = true;
									break;
								}
							}
						}
					}
				}

				if($es_mensaje_polimorf) {
					$classesfound[$i]['Robiolo_13']++;
					// @$classesfound[$i]['Robiolo_13_methods'] .= $metodo.'--';
				}

			}

	 	}





		//Robiolo 12
		if(count($classesfound[$i]['parents'])>0) { // si tiene superclases
			$heredados = 0;
			$sobreescritos = 0;
			$sobreescritos_considerar = 0;

			foreach($methods as $m) {
				if($m->isConstructor()) continue; //no se consideran constructores

				if($m->class != $class['name']) { $heredados++; continue; }

				if($m->isAbstract()) continue; //solo concretos

				$es_sobreescrito = false;
				foreach($classesfound[$i]['parents'] as $parent) {
					$reflP = new ReflectionClass($parent);
					foreach($reflP->getMethods() as $metP) {
						if($metP->name == $m->name) {
							$es_sobreescrito = true;
							$sobreescritos++;
							break 2;
						}
					}
				}

				if(!$es_sobreescrito) continue;

				$lines = get_method_lines($class['file'], $m->getStartLine(), $m->getEndLine());
				foreach($lines as $line) {
					if(strpos($line, 'parent::' . $m->name) !== false) continue 2;  //si llama al metodo en parent, chau
				}

				$sobreescritos_considerar++;  //listo, lo consideramos como método a incorporar en esta métrica
				
	 		}

	 		if($heredados+$sobreescritos > 0) {
	 			$classesfound[$i]['Robiolo_12'] = number_format($sobreescritos_considerar / ($heredados+$sobreescritos) * 100, 2, ',', '');
	 		}
	 		//$classesfound[$i]['Robiolo_12_heredados'] =  $heredados ;
	 		//$classesfound[$i]['Robiolo_12_sobreescritos'] =  $sobreescritos ;
	 		//$classesfound[$i]['Robiolo_12_sobreescritos_considerar'] =  $sobreescritos_considerar ;

		}


	}

	//------ fin Robiolo de clase






	// -------------------- C&K ---------------------------------------------------------- 

	$methods = $refl->getMethods();

	$classesfound[$i]['CK_WMC'] = 0;
	$classesfound[$i]['CK_RFC'] = 0;
	$classesfound[$i]['CK_CBO'] = 0;


	//------ WMC

	foreach($methods as $m) {
		
		if($m->class == $class['name']) $classesfound[$i]['CK_WMC']++; //no cuenta heredados

 	}

	//------ fin WMC
		

	//------ RFC

	$methods_called = array();
	$static_methods_called = array();

	foreach($methods as $m) {

		$classesfound[$i]['CK_RFC']++; //cuenta métodos incluso los heredados

		//voy método por método y cuento las llamadas a método en cada método -- eso se agrega a RFC
		$lines = get_method_lines($class['file'], $m->getStartLine(), $m->getEndLine());

		//$classesfound[$i][$m->name]=$lines;   //----descomentar esta línea para ver el código de cada método

		foreach($lines as $line) {  //vamos linea por linea buscando llamadas a método
			preg_match_all('/([a-zA-Z0-9_\)\(]+)->([a-zA-Z0-9_]+)\(/', $line, $result);
			foreach ($result[2] as $k => $mc) {
				if($result[1][$k]=="this") continue;    //si es una llamada sobre $this la omitimos, si no la contaríamos dos veces
				$methods_called[$mc] = true;
			}
		}
		//$classesfound[$i][$m->name.'_MC']=$methods_called;


		foreach($lines as $line) {  //vamos linea por linea buscando llamadas estáticas
			preg_match_all('/([a-zA-Z0-9_\)\(]+)::([a-zA-Z0-9_]+)\(/', $line, $result);
			foreach ($result[2] as $j => $mc) {
				if($result[1][$j]=="self") continue;    //si es una llamada sobre self:: la omitimos, idem anterior
				$static_methods_called[$result[1][$j].'::'.$mc] = true;
			}
		}
		//$classesfound[$i][$m->name.'_SMC']=$static_methods_called;

	}

	$classesfound[$i]['CK_RFC'] += count($methods_called);
	$classesfound[$i]['CK_RFC'] += count($static_methods_called);

	//------ fin RFC



	//------ CBO

	$classes_coupled_to = array();

	foreach($methods as $m) {

		if($m->class != $class['name']) continue;  // omito los heredados

		$parameters = $m->getParameters();

		foreach($parameters as $p) {  //acomplamiento dado por parámetro recibido
			if($p->hasType())  {
				if(!isset($classes_coupled_to[ $p->getType()->__toString() ] ))  $classes_coupled_to[ $p->getType()->__toString() ] = 0;
				$classes_coupled_to[ $p->getType()->__toString() ] ++;
			}
		}

		$lines = get_method_lines($class['file'], $m->getStartLine(), $m->getEndLine());
		
		//acoplamiento dado por accesos estáticos incluida superclase
		foreach($lines as $line) {  
			preg_match_all('/([a-zA-Z0-9_\)\(]+)::([a-zA-Z0-9_]+)/', $line, $result); //la regex es sutilmente distinta a la de RFC porque se cuentan los accesos a variables tb aquí para acoplamiento, entonces no tiene el paréntesis final. ej Revision::DELETED_USER hay que contarla aquí y en RFC no porque no es una llamada.
			foreach ($result[1] as $j => $mc) {
				if(!isset($classes_coupled_to[ $mc ] ))  $classes_coupled_to[ $mc ] = 0;
				$classes_coupled_to[ $mc ] ++;
			}
		}

		//acoplamiento por instanciación de una clase
		foreach($lines as $line) {  
			preg_match_all('/new\s+([\\\\a-zA-Z0-9_]+)\s*\(/', $line, $result);
			foreach ($result[1] as $j => $mc) {
				if(!isset($classes_coupled_to[ $mc ] ))  $classes_coupled_to[ $mc ] = 0;
				$classes_coupled_to[ $mc ] ++;
			}
		}

		//acoplamiento por excepción atrapada
		foreach($lines as $line) {  
			preg_match_all('/catch\s+\(\s+([a-zA-Z0-9_]+)\s+\$/', $line, $result);
			foreach ($result[1] as $j => $mc) {
				if(!isset($classes_coupled_to[ $mc ] ))  $classes_coupled_to[ $mc ] = 0;
				$classes_coupled_to[ $mc ] ++;
			}
		}

		//acoplamiento por tipo de retorno
		if($m->hasReturnType()){
			if(!isset($classes_coupled_to[ $m->getReturnType()->__toString() ] ))  $classes_coupled_to[ $m->getReturnType()->__toString() ] = 0;
			$classes_coupled_to[ $m->getReturnType()->__toString() ] ++;
		} 

	}

	foreach($classes_coupled_to as $ii=>$cla) {
		if(in_array($ii, $php_native_types)) unset($classes_coupled_to[$ii]);
		if(in_array($ii, array('parent', 'static', 'super', 'self', 'this'))) unset($classes_coupled_to[$ii]);
	}


	$classesfound[$i]['CK_CBO'] = count($classes_coupled_to);
	//$classesfound[$i]['CK_CBO_classes'] = print_r($classes_coupled_to, true);

	//------ fin CBO


	//------ Gullino
	
	$classesfound[$i]['GULL_1_UWRT'] = 0;
	$classesfound[$i]['GULL_2_DWRT'] = 0;
	$classesfound[$i]['GULL_3_SRT'] = 0;
	$classesfound[$i]['GULL_4_UWPT'] = 0;
	$classesfound[$i]['GULL_5_DWPT'] = 0;
	$classesfound[$i]['GULL_6_SPT'] = 0;

	foreach($methods as $m) {

		if($m->class != $class['name']) continue;  // omito los heredados

		//$lines = get_method_lines($class['file'], $m->getStartLine(), $m->getEndLine());
		//$types = discover_method_parameters_types($lines);

		$parameters = $m->getParameters();

		foreach($parameters as $p) {
			if($p->hasType())  {
				$classesfound[$i]['GULL_6_SPT']++;
				//@$classesfound[$i]['GULL_6_SPT_types'] .= $p->getType() . '-';
				continue;
			}
			else {
				if(parameter_type_is_documented($m, $p->getName())) $classesfound[$i]['GULL_5_DWPT']++;
				else $classesfound[$i]['GULL_4_UWPT']++;
			}
		}

		if($m->name!='__construct') {

			if($m->hasReturnType()){
				$classesfound[$i]['GULL_3_SRT']++;
				//@$classesfound[$i]['GULL_3_SRT_rettypes'] .= $m->getReturnType();
			} 
			else {
				if(has_return_type_documented($m)) $classesfound[$i]['GULL_2_DWRT']++;
				else $classesfound[$i]['GULL_1_UWRT']++;
			}

		}

	}

	//------ fin Gullino
	


}  // fin bucle enorme que recorre las clases encontradas en el sistema


//var_dump($classesfound);



echo 'Id|Clase|Es_Externa|Es_Interfaz|LOC|CK_WMC|CK_RFC|CK_CBO|Robiolo_12|Robiolo_13|Robiolo_14|Robiolo_15|Gullino_1|Gullino_2|Gullino_3|Gullino_4|Gullino_5|Gullino_6|Bugfixes_semestre' . "\n";

foreach($classesfound as $class) {
	echo $class['id'] . '|' ;
	echo $class['name'] . '|' ;
	echo $class['is_external'] . '|' ;
	if($class['is_interface']) echo 'Y|'; else echo 'N|' ;
	
	echo $class['LOC'] . '|' ;
	echo $class['CK_WMC'] . '|' ;
	echo $class['CK_RFC'] . '|' ;
	echo $class['CK_CBO'] . '|' ;
	echo $class['Robiolo_12'] . '|' ;
	echo $class['Robiolo_13'] . '|' ;
	echo $class['Robiolo_14'] . '|' ;
	echo $class['Robiolo_15'] . '|' ;
	echo $class['GULL_1_UWRT'] . '|' ;
	echo $class['GULL_2_DWRT'] . '|' ;
	echo $class['GULL_3_SRT'] . '|' ;
	echo $class['GULL_4_UWPT'] . '|' ;
	echo $class['GULL_5_DWPT'] . '|' ;
	echo $class['GULL_6_SPT']  . '|' ;






	/*************************


	CONSULTA A CONTINUACIÓN OJO '''''FECHAS''''', CAMBIAN EL RESULTADO GENERADO, EN CONJUNCIÓN CON EL ESTADO
	DEL REPOSITORIO DE DONDE SE LEEN LOS ARCHIVOS


	************************/


	if($class['id'] != -1) {  // si tiene -1 es porque esta clase no forma parte de las métricas de cambio (nunca fue modificada por ningún commit)
		$res = mysqli_query($cn, "SELECT cm.id idcommit
										FROM commits cm
										WHERE cm.discarded = 'N' AND cm.commit_date BETWEEN '2014-7-1' AND '2014-12-31'
										AND cm.type = 'FR'
										AND cm.id IN (
											SELECT commit
											FROM classchanges cc
											WHERE cc.class = {$class['id']} )   ");
		$bugfixes = mysqli_num_rows($res);
	} else {
		$bugfixes = 0;
	}

	echo $bugfixes  . "\n" ;



}


echo "\n\n\n  ";
echo "\nSystem LOC: " . $system_metrics['System_LOC'];
echo "\nRobiolo 1: Tamaño, cant clases: " . $system_metrics['Robiolo_1'];
echo "\nRobiolo 2: Tamaño, cant interfaces: " . $system_metrics['Robiolo_2'];
echo "\nRobiolo 3: Reutilización: clases externas especializadas: " . $system_metrics['Robiolo_3'];
echo "\nRobiolo 4: Reutilización: interfaces externas extendidas: " . $system_metrics['Robiolo_4'];
echo "\nRobiolo 5: Reutilización: clases que implementan interf ext: " . $system_metrics['Robiolo_5'];
echo "\nRobiolo 6: Herencia: Jerarquías de clase: " . $system_metrics['Robiolo_6'];
echo "\nRobiolo 7: Herencia: Jerarquías de interfaz: " . $system_metrics['Robiolo_7'];
echo "\nRobiolo 8: Especialización: niveles por jerarquía de clases: " . $system_metrics['Robiolo_8'];
echo "\nRobiolo 9: Especialización: niveles por jerarquía de interfaces: " . $system_metrics['Robiolo_9'];
echo "\nRobiolo 10: Generalización: clases raíz concretas: " . $system_metrics['Robiolo_10'];
echo "\nRobiolo 11: Generalización: clases raíz concretas que implementan interf: " . $system_metrics['Robiolo_11'];






// ============================================= funciones auxiliares =============================================


function discover_files($path) {
	global $_filesfound;

	$scan  = @scandir($path);
	if(!$scan) return; //is not a directory

	foreach($scan as $file) {
		if($file == '.' || $file == '..') continue;
		if($file == 'AutoLoader.php') continue;   //excluimos este archivo porque lo usamos en esta misma herramienta
		if(substr($file, -4) === '.php') $_filesfound[] = $path . '\\' . $file;
		else discover_files($path . '\\' . $file);
	}
}

function discover_classes($files) {
	global $cn;

	$classes = array();

	foreach($files as $file) {
		$classnames = discover_class_names($file);
		if(!$classnames) echo "\n no class declaration in this file: $file --- " ;
		else {
			$isexternal='N';

			$libs = ROOTPATH.'\\includes\\libs';
			if(substr($file, 0, strlen($libs))==$libs) $isexternal='Y';

			$vendor = ROOTPATH.'\\vendor';
			if(substr($file, 0, strlen($vendor))==$vendor) $isexternal='Y';

			foreach($classnames as $c) {

				$res = mysqli_query($cn, "SELECT id,name
											from classes
											where name = '$c' ");
				if(mysqli_num_rows($res)>1) echo "\n ----- !! clase duplicada $c, filas: " . mysqli_num_rows($res) ;
				
				if(mysqli_num_rows($res)==1) {
					$fila = mysqli_fetch_assoc($res);
					$idclase = $fila['id'];
				} else {
					$idclase = -1;
				}

				$classes[] = array('id' => $idclase, 'name'=>$c, 'file'=>$file, 'is_external'=>$isexternal); 
			}
		}
	}

	return $classes;
}

function discover_class_names($file) {  // OJOOOO que hay declaraciones en varias lineas q esta función no encuentra bien!!!! 1.31
	$lines = get_source($file);
	//var_dump($lines);exit;

	$classnames = array();

	$buscandollave = false;
	foreach($lines as $i=>$line) {
		if(preg_match('/^(\s+)?(abstract\s)?class\s([a-zA-Z0-9_]*)(\s+)?$/', $line, $matches)) 
			{ $buscandollave = true; $classprobable = $matches[3]; continue;}
		if(preg_match('/^(\s+)?(final\s)?class\s([a-zA-Z0-9_]*)(\s+)?$/', $line, $matches)) 
			{ $buscandollave = true; $classprobable = $matches[3]; continue;}
		
		if($buscandollave && preg_match('/^(\s+)?{(\s+)?$/', $line, $matches)) $classnames[] = $classprobable; 
		else {  $buscandollave = false; $classprobable = ''; }

		if(preg_match('/^(\s+)?(abstract\s+)?class\s+([a-zA-Z0-9_]*)(\s+)?{/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		if(preg_match('/^(\s+)?(final\s+)?class\s+([a-zA-Z0-9_]*)(\s+)?{/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		
		if(preg_match('/^(\s+)?(abstract\s+)?class\s+([a-zA-Z0-9_]*)(\s+)extends(\s+)([a-zA-Z0-9_]*)(\s+){/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		if(preg_match('/^(\s+)?(final\s+)?class\s+([a-zA-Z0-9_]*)(\s+)extends(\s+)([a-zA-Z0-9_]*)(\s+){/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		
		if(preg_match('/^(\s+)?(abstract\s+)?class\s+([a-zA-Z0-9_]*)(\s+)implements(\s+)([a-zA-Z0-9,_\s]*)(\s+){/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		if(preg_match('/^(\s+)?(final\s+)?class\s+([a-zA-Z0-9_]*)(\s+)implements(\s+)([a-zA-Z0-9,_\s]*)(\s+){/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		
		if(preg_match('/^(\s+)?(abstract\s+)?class\s+([a-zA-Z0-9_]*)(\s+)extends(\s+)([a-zA-Z0-9_]*)(\s+)implements(\s+)([a-zA-Z0-9,_\s]*)(\s+){/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		if(preg_match('/^(\s+)?(final\s+)?class\s+([a-zA-Z0-9_]*)(\s+)extends(\s+)([a-zA-Z0-9_]*)(\s+)implements(\s+)([a-zA-Z0-9,_\s]*)(\s+){/', $line, $matches)) 
			{ $classnames[] = $matches[3]; continue; }
		
		if(preg_match('/^(\s+)?interface\s([a-zA-Z0-9_]*)(\s+)?{/', $line, $matches)) 
			{ $classnames[] = $matches[2]; continue; }  //root interface declaration
		if(preg_match('/^(\s+)?interface\s([a-zA-Z0-9_]*)(\s+)extends(\s+)([a-zA-Z0-9_]*)(\s+)?{/', $line, $matches)) 
			{ $classnames[] = $matches[2]; continue; }  //interface extension


	}

	if(count($classnames)>0) return $classnames;
	else return false;
}

function get_source($file) {
	return file($file);

	/*
	$output = array();
	exec(PHPBINPATH . ' -w "' . $file . '"', $output);
	return $output;
	*/
}

function get_method_lines($file, $startline, $endline) {
	$startline--;
	$endline--;
	$lines = file($file);

	foreach($lines as $i=>$l) {
		if($i < $startline || $i > $endline) unset($lines[$i]);
	}

	//quitamos las cosas comentadas. esto se puede mejorar mucho!
	foreach($lines as $i=>$l) {
		if(substr(trim($l),0,1)=='#') unset($lines[$i]);

		$barras = strpos($l, '//');
		if($barras!==false) $lines[$i] = substr($l, 0, $barras);
	}

	return $lines;
}


function get_class_LOC($file) {
	$lines = file($file);

	$en_multilinea=false;
	foreach($lines as $i=>$l) {

		//seguimos en multilinea, seguimos buscando el cierre
		if($en_multilinea && strpos(trim($l),'*/')===false){ unset($lines[$i]); continue; }

		//seguimos en multilinea, seguimos buscando el cierre
		if($en_multilinea && substr(trim($l),-2)=='*/'){ unset($lines[$i]); $en_multilinea=false; continue; }
		

		//quitamos lineas que arrancan con php #  //  /**  *  */
		if(trim($l)=='<?php') { unset($lines[$i]); continue; }
		if(substr(trim($l),0,1)=='#') { unset($lines[$i]); continue; }
		if(substr(trim($l),0,2)=='//') { unset($lines[$i]); continue; }
		if(substr(trim($l),0,3)=='/**') { unset($lines[$i]); continue; }
		if(substr(trim($l),0,2)=='* ') { unset($lines[$i]); continue; }
		if(trim($l)=='*') { unset($lines[$i]); continue; }
		if(substr(trim($l),0,2)=='*/') { unset($lines[$i]); continue; }
		if(substr(trim($l),0,2)=='/*' && substr(trim($l),-2)=='*/') { unset($lines[$i]); continue; }

		if(strpos(trim($l),'/* ')!==false && strpos(trim($l),'*/')===false) { unset($lines[$i]); $en_multilinea=true; continue; }

		//quitamos líneas vacías
		if(strlen(trim($l))==0) { unset($lines[$i]); continue; }
		
	}

	
	//debugging info
	//if($file=="C:\Users\Mauro\Desktop\git mw\mediawiki\includes\WebResponse.php") {  var_dump($lines); exit; }
	

	if($en_multilinea) throw new Exception("Llegamos a EOF y no encontré cierre de comentario multilinea :( $file ");

	return count($lines);
}


/*
function discover_method_parameters_types($lines) {
	$types = array();

	$all_lines = "";
	foreach ($lines as $line) {
		$all_lines .= $line;
	}

	$primer_parentesis_abre = strpos($all_lines, '(');
	if($primer_parentesis_abre===false) throw new Exception("error al parsear firma del método!");
	$primer_parentesis_cierra = strpos($all_lines, ')');
	if($primer_parentesis_cierra===false) throw new Exception("error al parsear firma del método!");


	$params = substr($all_lines, $primer_parentesis_abre+1, $primer_parentesis_cierra-$primer_parentesis_abre-1);
	$params = explode(',', $params);

	if(count($params)>0) {
		foreach($params as $param) {
			if(preg_match('/([a-zA-Z0-9_|]*)\s([$][a-zA-Z0-9_]*)/', $param, $matches)) 
				{ 
					if(strlen($matches[1]) > 0) $types[] = $matches[1];
					continue; 
				}
		}
	}

	return $types;
}
*/


function has_return_type_documented(ReflectionFunctionAbstract $method) {
	$docblock = $method->getDocComment();
	if(!$docblock) return false;

	if(strpos($docblock, '* @return ') !== false) return true;
	else return false;
}

function parameter_type_is_documented(ReflectionFunctionAbstract $method, $parameterName) {
	$docblock = $method->getDocComment();
	if(!$docblock) return false;

	if(strpos($docblock, '* @param $'.$parameterName) !== false) return true;
	else return false;
}






function wfRunHooks() {}   //  PARCHE.... DEBE SER ALGUNA FUNCIÓN LLAMADA CUANDO INCLUIMOS LOS ARCHIVOS A ANALIZAR....