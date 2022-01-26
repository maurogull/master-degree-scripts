<?php

$cn = mysqli_connect("localhost", "root", "", "metricas");

chdir('c:\\users\\mauro\\desktop\\git mw\\mediawiki');


/*


 ---------------------------------------------------------------

         OJO AL LIMIT

 ---------------------------------------------------------------



*/


$clases = mysqli_query($cn, "SELECT id,name,path
						from classes
						where name is not null 
						and deleted_on is null
						and deletion_commit is null 
						limit 800,400");   //  <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<< LIMIT

while($clase = mysqli_fetch_assoc($clases)) {

	$path = substr($clase['path'], 2);

	ob_start();
	system("git log --diff-filter=D --date=short -- $path");
	$lines = explode("\n", ob_get_contents());
	ob_end_clean();

	if(count($lines) > 3) {
		$commit = substr($lines[0], -40);
		$deletiondate = substr($lines[2], -10);

		echo $clase['name'] . ' : ' . $path . ' ' . $deletiondate . ' ' . $commit . "<br>";
		
		mysqli_query($cn, "UPDATE classes
						set deleted_on = '$deletiondate' , deletion_commit = '$commit'
						where id = {$clase['id']}
						limit 1");

		if(mysqli_error($cn)!="") die( mysqli_error($cn) . ' procesando ' . $clase['name'] . ' : ' . $path );

	} else {
		echo $clase['name'] . ' : ' . $path . " --------  NO ENCONTRADO --------------  <br>";
	}


}
