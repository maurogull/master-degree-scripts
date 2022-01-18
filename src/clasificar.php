<?php

$cn = mysqli_connect("localhost", "root", "", "metricas");


if(isset($_POST['commit']) && !isset($_POST['saltear'])) {

	if(isset($_POST['FI'])) {
		mysqli_query($cn, "UPDATE commits SET type='FI',review_date=NOW() where id='{$_POST['commit']}' limit 1 ");
	}
	if(isset($_POST['FR'])) {
		mysqli_query($cn, "UPDATE commits SET type='FR',review_date=NOW() where id='{$_POST['commit']}' limit 1 ");
	}
	if(isset($_POST['GM'])) {
		mysqli_query($cn, "UPDATE commits SET type='GM',review_date=NOW() where id='{$_POST['commit']}' limit 1 ");
	}
	if(isset($_POST['DISCARD'])) {
		mysqli_query($cn, "UPDATE commits SET discarded='Y',review_date=NOW() where id='{$_POST['commit']}' limit 1 ");
	}


	if(isset($_POST['refactor'])) {
		mysqli_query($cn, "UPDATE commits SET is_refactor='Y' where id='{$_POST['commit']}' limit 1 ");
	}
	if(isset($_POST['security'])) {
		mysqli_query($cn, "UPDATE commits SET is_security='Y' where id='{$_POST['commit']}' limit 1 ");
	}


	echo '<h4>OK ' . $_POST['commit']. '</h4>';

}


$res1 = mysqli_query($cn, "SELECT count(*) as faltan
						from commits
						where review_date is null
						");

$res2 = mysqli_query($cn, "SELECT *, rand() r
						from commits
						where review_date is null
						having r > 0.5
						limit 1");
$faltan = mysqli_fetch_assoc($res1);
$faltan = $faltan['faltan'];

if($faltan==0) die("todo terminado !!");

$datos = mysqli_fetch_assoc($res2);

?>

<!DOCTYPE html>
<html>
<head>
	<title>Clasificador métricas cambio</title>


	<style>
	#raw { 
		float: right;
		width: 650px;
		height: 600px;
		overflow: scroll;
	}

	#ppal {
		float:left;
		width: 600px;
	}

	input[type="submit"] {
		padding: 10px;
		font-size:18pt;
		margin-left:10px;
	}

	</style>
</head>
<body>

	<pre id="raw"><?= htmlentities($datos['raw']) ?></pre>
	
	<div id="ppal">

		<h2>Clasificador métricas cambio v1.0</h2>

		<pre><?= htmlentities($datos['message']) ?></pre>


		<form action="" method="post">
			<!--<label for="refactor">REFACTOR</label> <input type="checkbox" name="refactor" value="1" id="refactor" />
			&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<label for="security">SECURITY</label> <input type="checkbox" name="security" value="1" id="security" />
			-->
			
			<br/><br/>

			<input type="submit" name="FI"  value="FI" />
			<input type="submit" name="FR"  value="FR"/>
			<input type="submit" name="GM"  value="GM" />
			<input type="submit" name="DISCARD"  value="DISCARD" />

			<input type="submit" name="saltear"  value="Saltear" />
			
			<input type="hidden" name="commit"  value="<?= $datos['id'] ?>" />

		</form>
		
		<br/>
		<br/>
		<br/>
		<p>(Faltan clasificar <?= $faltan ?> commits)</p>

	</div>

</body>
</html>
