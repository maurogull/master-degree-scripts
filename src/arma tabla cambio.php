<?php


$cn = mysqli_connect("localhost", "root", "", "metricas");


/*****************************************************



	OJO NO TIENE LIMIT LA CONSULTA; LO VA A HACER
	PARA **TODAS** LAS CLASES y TRES VECES (tres trimestres)




*******************************************************/


$clases = mysqli_query($cn, "SELECT id,name,path
						from classes
						where name is not null
						order by name  
						 ");     /// <<<<<<   LIMIT ACA  ------------------------------------------------ -0-0-0-0-0-0-0-0-0-0-

$tabla = array();

for($trimestre = 1; $trimestre <= 3; $trimestre++) {
	mysqli_data_seek($clases,0);
	while($clase = mysqli_fetch_assoc($clases)) {


		if($trimestre==1) { $desde = '2014-1-1 00:00'; $hasta = '2014-4-1 00:00'; $hastabugfix = '2014-7-1 00:00'; }
		if($trimestre==2) { $desde = '2014-4-1 00:00'; $hasta = '2014-7-1 00:00'; $hastabugfix = '2014-10-1 00:00'; }
		if($trimestre==3) { $desde = '2014-7-1 00:00'; $hasta = '2014-10-1 00:00'; $hastabugfix = '2015-1-1 00:00'; }
		//if($trimestre==4) { $desde = '2014-10-1 00:00'; $hasta = '2015-1-1 00:00'; $hastabugfix = '2014-4-1 00:00'; }

		$idclase = $clase['id'];

		$nuevo = array(); //$nuevo será una fila de la tabla final excel
		$nuevo['trimestre'] = $trimestre;  // --------------------------------------- METRICA
		$nuevo['id'] = $idclase; // --------------------------------------- METRICA
		$nuevo['clase'] = $clase['name']; // --------------------------------------- METRICA


		$res = mysqli_query($cn, "SELECT class, a , SUM(lines_added) added, SUM(lines_deleted) deleted
									FROM classchanges cc
									LEFT JOIN commits cm ON cm.id=cc.commit 
									WHERE class=$idclase AND cm.commit_date BETWEEN '$desde' AND '$hasta'
									AND cm.type='FI' and cm.discarded='N'
									GROUP BY cm.id");

		if(mysqli_num_rows($res)==0) continue;

		$nuevo['revisions'] = mysqli_num_rows($res);  // --------------------------------------- METRICA

		$loc_added = $loc_deleted = 0;
		$max_loc_added = $max_loc_deleted = 0;
		$max_codechurn = 0;
		while($aux = mysqli_fetch_assoc($res)) {
			$loc_added += $aux['added'];
			$loc_deleted += $aux['deleted'];
			if($aux['added'] > $max_loc_added) $max_loc_added = $aux['added'];
			if($aux['deleted'] > $max_loc_deleted) $max_loc_deleted = $aux['deleted'];
			if(($aux['added']-$aux['deleted']) > $max_codechurn) $max_codechurn = ($aux['added']-$aux['deleted']);
		}

		$nuevo['loc_added'] = $loc_added;  // --------------------------------------- METRICA
		$nuevo['max_loc_added'] = $max_loc_added;  // --------------------------------------- METRICA
		$nuevo['ave_loc_added'] = $loc_added / $nuevo['revisions'];  // --------------------------------------- METRICA
		$nuevo['loc_deleted'] = $loc_deleted;  // --------------------------------------- METRICA
		$nuevo['max_loc_deleted'] = $max_loc_deleted;  // --------------------------------------- METRICA
		$nuevo['ave_loc_deleted'] = $loc_deleted / $nuevo['revisions'];  // --------------------------------------- METRICA
		$nuevo['codechurn'] = $loc_added - $loc_deleted;  // --------------------------------------- METRICA
		$nuevo['max_codechurn'] = $max_codechurn;  // --------------------------------------- METRICA
		$nuevo['ave_codechurn'] = $nuevo['codechurn'] / $nuevo['revisions'];  // --------------------------------------- METRICA



		$res = mysqli_query($cn, "SELECT DISTINCT author
									FROM classchanges cc
									LEFT JOIN commits cm ON cm.id=cc.commit 
									WHERE cc.class=$idclase AND cm.commit_date BETWEEN '$desde' AND '$hasta'
									AND cm.type='FI' and cm.discarded='N'
									GROUP BY cm.id");

		$nuevo['authors'] = mysqli_num_rows($res);  // --------------------------------------- METRICA




		$res = mysqli_query($cn, "SELECT cm.id idcommit
								FROM commits cm
								WHERE cm.discarded = 'N' AND cm.commit_date BETWEEN '$desde' AND '$hasta'
								and cm.type = 'FI'
								AND cm.id IN (
									SELECT commit
									FROM classchanges cc
									WHERE cc.class = $idclase   )    ");

		$commits_con_esta_clase = mysqli_num_rows($res);
		$ave_changeset = 0;
		$max_changeset = 0;
		while($aux = mysqli_fetch_assoc($res)) {
			$res2 = mysqli_query($cn, "SELECT DISTINCT class
										FROM classchanges cc
										WHERE cc.commit = '{$aux['idcommit']}'   ");
			$otras_modificadas = mysqli_num_rows($res2) - 1;
			if($otras_modificadas > $max_changeset) $max_changeset = $otras_modificadas;
			$ave_changeset += $otras_modificadas;
		}
		$ave_changeset = $ave_changeset / $commits_con_esta_clase;

		$nuevo['ave_changeset'] = $ave_changeset;  // --------------------------------------- METRICA
		$nuevo['max_changeset'] = $max_changeset;  // --------------------------------------- METRICA




		$res = mysqli_query($cn, "SELECT ROUND(DATEDIFF('$hasta', created_on)/7, 0) AS age
									FROM classes c
									WHERE c.id = $idclase  ");
		$age = mysqli_fetch_assoc($res);
		$age = $age['age'];

		$nuevo['age'] = $age;  // ---------------------------------------------------- METRICA




		$res = mysqli_query($cn, "SELECT class, a , SUM(lines_added) added, ROUND(DATEDIFF(cm.commit_date, c.created_on)/7, 0) AS age
									FROM classchanges cc
									LEFT JOIN commits cm ON cm.id = cc.commit 
									LEFT JOIN classes c ON c.id = cc.class
									WHERE cc.class=$idclase AND cm.commit_date BETWEEN '$desde' AND '$hasta'
									AND cm.type='FI' AND cm.discarded='N'
									GROUP BY cm.id");

		$dividendo = 0;
		$divisor = 0;
		while($aux = mysqli_fetch_assoc($res)) {
			$dividendo += $aux['age'] * $aux['added'];
			$divisor += $aux['added'];
		}
		//if($divisor==0) die($idclase);	
		if($divisor==0) $nuevo['weighted_age']=0;	//ocurre cuando el commit borra pero no agrega, da LOC_added 0
		else $nuevo['weighted_age'] = $dividendo / $divisor;  // --------------------------------------- METRICA






		$res = mysqli_query($cn, "SELECT cm.id idcommit
									FROM commits cm
									WHERE cm.discarded = 'N' AND cm.commit_date BETWEEN '$desde' AND '$hasta'
									AND cm.type = 'FR'
									AND cm.id IN (
										SELECT commit
										FROM classchanges cc
										WHERE cc.class = $idclase  )   ");
		$bugfixes = mysqli_num_rows($res);

		$nuevo['bugfixes'] = $bugfixes;  // ---------------------------------------------------- METRICA







		$res = mysqli_query($cn, "SELECT cm.id idcommit
									FROM commits cm
									WHERE cm.discarded = 'N' AND cm.commit_date BETWEEN '$hasta' AND '$hastabugfix'
									AND cm.type = 'FR'
									AND cm.id IN (
										SELECT commit
										FROM classchanges cc
										WHERE cc.class = $idclase  )   ");
		$bugfixes = mysqli_num_rows($res);

		$nuevo['bugfixes_prox_periodo'] = $bugfixes;  // ---------------------------------------------------- METRICA







		$tabla[] = $nuevo;

	}
}




//listo, armamos CSV para excel !

echo "trimestre|id|nombre|revisions|loc_added|max_loc_added|ave_loc_added|loc_deleted|max_loc_deleted|ave_loc_deleted|codechurn|max_codechurn|ave_codechurn|authors|ave_changeset|max_changeset|age|weighted_age|bugfixes|bugfixes_prox_periodo\n";

foreach($tabla as $linea) {
	$sep = '|';
	
	echo $linea['trimestre'] . $sep;
	echo $linea['id'] . $sep;
	echo $linea['clase'] . $sep;
	echo $linea['revisions'] . $sep;
	echo $linea['loc_added'] . $sep;
	echo $linea['max_loc_added'] . $sep;
	echo decimales($linea['ave_loc_added']) . $sep;
	echo $linea['loc_deleted'] . $sep;
	echo $linea['max_loc_deleted'] . $sep;
	echo decimales($linea['ave_loc_deleted']) . $sep;
	echo $linea['codechurn'] . $sep;
	echo $linea['max_codechurn'] . $sep;
	echo decimales($linea['ave_codechurn']) . $sep;
	echo $linea['authors'] . $sep;
	echo decimales($linea['ave_changeset']) . $sep;
	echo $linea['max_changeset'] . $sep;
	echo $linea['age'] . $sep;
	echo decimales($linea['weighted_age']) . $sep;
	echo $linea['bugfixes'] . $sep;
	echo $linea['bugfixes_prox_periodo'] ;

	echo "\n";
}



function decimales($numero) {
	if($numero==0) return 0;
	$aux = floatval(number_format($numero, 2, '.', ''));
	return  str_replace('.', ',', $aux);
}

//var_dump($tabla);

// yeeeee 2018-11-27   :)