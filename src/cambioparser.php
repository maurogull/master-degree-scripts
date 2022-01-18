<?php

//	$VERS = '1.23';
$VERS = 'master 2014';

$_GUARDAR_EN_BASE = true;

$cn = mysqli_connect("localhost", "root", "", "metricas");

$lines = file("commits {$VERS}.txt");

$temp = array();

$en_mensaje = false;
$en_diff = false;
$en_diff_detail = false;
$diff_count = 0;

foreach($lines as $linecount => $line) {

	if($linecount % 20000) {
		mysqli_close($cn);
		usleep(2000);
		$cn = mysqli_connect("localhost", "root", "", "metricas");
	}

	if(isset($temp['commit'])) {
		if(!isset($temp['rawlines'])) $temp['rawlines']='';
		$temp['rawlines'] .= $line;
	}

	if(trim($line)=="") continue;


	$test = preg_match('/^diff --git (.*) (.*)$/', $line, $found);
	if($test) {
		$en_mensaje = false;
		$en_diff = true;
		$en_diff_detail = false;
		$diff_count++;
		$temp['diffs'][$diff_count]['a'] = $found[1];
		$temp['diffs'][$diff_count]['b'] = $found[2];
		$diff_detail_count = 0;

		//testeo de diferencia en diff a/b
		$prueba1 = substr($found[1], 1);
		$prueba2 = substr($found[2], 1);
		//if($prueba1!=$prueba2) die(" ---- OJO  no coinciden {$found[1]} y {$found[2]} ");

		continue;
	}


	if($en_mensaje) {
		if(!isset($temp['message'])) $temp['message'] = '';
		$temp['message'] .= $line;
		continue;
	}
	

	if($en_diff || $en_diff_detail) {
		//estoy en diff o detail y llega un/otro detail
		$test = preg_match('/^@@ .* @@ (.*)$/', $line, $found);
		if($test) {
			$en_mensaje = false;
			$en_diff = false;
			$en_diff_detail = true;
			$diff_detail_count++;

			$temp['diffs'][$diff_count][$diff_detail_count]['owner'] = $found[1];
			$temp['diffs'][$diff_count][$diff_detail_count]['added'] = 0;
			$temp['diffs'][$diff_count][$diff_detail_count]['deleted'] = 0;

			continue;
		}

	}


	if($en_diff_detail) {

		if(substr($line, 0, 1)=='+') { 
			$temp['diffs'][$diff_count][$diff_detail_count]['added']++; 
			continue;
		}
		if(substr($line, 0, 1)=='-') { 
			$temp['diffs'][$diff_count][$diff_detail_count]['deleted']++;
			continue;
		}

	}




	$test = preg_match('/^commit ([0-9a-f]{40})$/', $line, $found);
	if($test) {

		if($temp) { 
			guardar($temp);
			$temp = array();
		}
		$temp['commit'] = $found[1]; 
		$en_diff = false;
		$en_diff_detail = false;
		$en_mensaje = false;
		continue;
	}

	$test = preg_match('/^Author:\s*(.*)$/', $line, $found);
	if($test) {
		$temp['author'] = $found[1]; 
		continue;
	}

	$test = preg_match('/^Commit:\s*(.*)$/', $line, $found);
	if($test) {
		$temp['commiter'] = $found[1]; 
		continue;
	}

	$test = preg_match('/^AuthorDate:\s*([0-9-]*)$/', $line, $found);
	if($test) {
		$temp['author_date'] = $found[1]; 
		continue;
	}

	$test = preg_match('/^CommitDate:\s*([0-9-]*)$/', $line, $found);
	if($test) {
		$temp['commit_date'] = $found[1]; 
		$en_mensaje = true;
		continue;
	}





}

guardar($temp);




function guardar(array $temp) : void {
	global $cn; 
	global $VERS; 
	global $_GUARDAR_EN_BASE; 

	//var_dump($temp);
	echo $temp['commit'] . "\n";

	if(!$_GUARDAR_EN_BASE) return;


	//me fijo que el commit no exista. si ya existe es porq parte de este proceso ya se hizo: omitimos.
	$existe = mysqli_query($cn, "SELECT id from commits where id='{$temp['commit']}' limit 1" );
	if(mysqli_error($cn)!="") die(" --154-- ERROR consulta!!! " . mysqli_error($cn));
	if(mysqli_num_rows($existe)==1) return;


	$author_id = buscar_persona_o_crear($temp['author']);
	$commiter_id = buscar_persona_o_crear($temp['commiter']);

	$message = mysqli_escape_string($cn, $temp['message']);
	$rawlines = mysqli_escape_string($cn, substr($temp['rawlines'],0,3000));

	mysqli_query($cn, "INSERT INTO commits 
						(id, 
						author_date, commit_date, message,
						author, commiter, raw, version)
						VALUES
						( '{$temp['commit']}' ,
						'{$temp['author_date']}', '{$temp['commit_date']}', '{$message}' ,
						  {$author_id}, {$commiter_id}, '{$rawlines}', '$VERS' )  " );
	if(mysqli_error($cn)!="") die(" ---- ERROR consulta!!! " . mysqli_error($cn));
	//$commit_id = mysqli_insert_id($cn);
	$commit_id = $temp['commit'];


	
	foreach($temp['diffs'] as $diff) {
		$filename = $diff['a'];
		if(substr($filename, -4)!='.php') continue;
		$a = '?';
		$b = '?';

		foreach($diff as $i=>$change) {
			if($i=='a') {$a=$change; continue; }
			if($i=='b') {$b=$change; continue; }

			$idclase = buscar_clase_o_crearla($change['owner'], $filename);


			mysqli_query($cn, "INSERT INTO classchanges 
						(class, commit, lines_added, lines_deleted,
						a, b)
						VALUES
						( $idclase , '$commit_id' , {$change['added']} , {$change['deleted']} ,
						 '$a' , '$b' )  " );
			
			if(mysqli_error($cn)!="") die(" ---- ERROR consulta!!! " . mysqli_error($cn));

		}

	}



}


function buscar_persona_o_crear(string $name) : int {
	global $cn;

	$res = mysqli_query($cn, "SELECT * from authors where name='$name' limit 1");
	if(mysqli_error($cn)!="") die(" ---- ERROR consulta!!! " . mysqli_error($cn));
	
	if(mysqli_num_rows($res)==1) {
		//existe
		$datos = mysqli_fetch_assoc($res);
		return (int) $datos['id'];
	}
	else {
		//no existe
		$res = mysqli_query($cn, "INSERT into authors (name) values ('$name') ");
		return mysqli_insert_id($cn);
	}


}


function buscar_clase_o_crearla(string $rawname, string $path) : int {
	global $cn;
	global $VERS;

	$rawname = mysqli_escape_string($cn, $rawname);
	$path = mysqli_escape_string($cn, $path);

	$res = mysqli_query($cn, "SELECT * from classes where raw_name='$rawname' and version='$VERS' limit 1");
	if(mysqli_error($cn)!="") die(" ---- ERROR consulta!!! " . mysqli_error($cn));
	
	if(mysqli_num_rows($res)==1) {
		//existe
		$datos = mysqli_fetch_assoc($res);
		return (int) $datos['id'];
	}
	else {
		//no existe
		$res = mysqli_query($cn, "INSERT into classes (raw_name,path,version) 
									values ('$rawname','$path','$VERS') ");
		return mysqli_insert_id($cn);
	}


}