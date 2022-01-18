<?php

$cn = mysqli_connect("localhost", "root", "", "metricas");



/*****************************************************



	OJO !!!!!!    

	
	ESTE SCRIPT SE EJECUTA CON EL REPOSITORIO ACOMODADO
	EN CIERTO PUNTO PARA COMPUTAR LAS MÉTRICAS ESTÁTICAS
	DE ESE MOMENTO DEL CÓDIGO

	primera revisión del año 2014:
	git checkout ee01799f47013d0d6dee73b9612e81eb82e3e460

	primera revisión de julio 2014:
	git checkout cae5da1ca330021d966f5a81ec5b4760a3da0568

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
$system_metrics['clases_externas'] = 0;
$system_metrics['interfaces_externas'] = 0;


foreach($classesfound as $i=>$class) {
	$classesfound[$i]['parents'] = class_parents($class['name']);
	$classesfound[$i]['implements'] = class_implements($class['name']);
}


foreach($classesfound as $i=>$class) {
	try {
		$refl = new ReflectionClass($class['name']); 
	}
	catch(Exception $e) {
		echo '<p>omitiendo ' . $class['name'] . ' imposible hacer Reflection</p>';
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

	if(!$refl->isInterface() && $classesfound[$i]['is_external']=='Y') $system_metrics['clases_externas']++;
	if($refl->isInterface() && $classesfound[$i]['is_external']=='Y') $system_metrics['interfaces_externas']++;

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




	


}  // fin bucle enorme que recorre las clases encontradas en el sistema


//var_dump($classesfound);


echo "<br/>System LOC: " . $system_metrics['System_LOC'];
echo "<br/>Robiolo 1: Tamaño, cant clases: " . $system_metrics['Robiolo_1'];
echo "<br/>Robiolo 2: Tamaño, cant interfaces: " . $system_metrics['Robiolo_2'];
echo "<br/>Robiolo 3: Reutilización: clases externas especializadas: " . $system_metrics['Robiolo_3'];
echo "<br/>Robiolo 4: Reutilización: interfaces externas extendidas: " . $system_metrics['Robiolo_4'];
echo "<br/>Robiolo 5: Reutilización: clases que implementan interf ext: " . $system_metrics['Robiolo_5'];
echo "<br/>Robiolo 6: Herencia: Jerarquías de clase: " . $system_metrics['Robiolo_6'];
echo "<br/>Robiolo 7: Herencia: Jerarquías de interfaz: " . $system_metrics['Robiolo_7'];
echo "<br/>Robiolo 8: Especialización: niveles por jerarquía de clases: " . $system_metrics['Robiolo_8'];
echo "<br/>Robiolo 9: Especialización: niveles por jerarquía de interfaces: " . $system_metrics['Robiolo_9'];
echo "<br/>Robiolo 10: Generalización: clases raíz concretas: " . $system_metrics['Robiolo_10'];
echo "<br/>Robiolo 11: Generalización: clases raíz concretas que implementan interf: " . $system_metrics['Robiolo_11'];
echo "<br/>Clases externas " . $system_metrics['clases_externas'];
echo "<br/>Interfaces externas: " . $system_metrics['interfaces_externas'];




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