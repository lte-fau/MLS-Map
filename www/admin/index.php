<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

session_start();

if(isset($_GET['login']))
{	
	include "../../db/db-settings.php";
	$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());
	
	$username = $_POST['username'];
	$password = $_POST['password'];
	
	$sql = "SELECT * FROM admins WHERE username = '$username'";
	$result = pg_query($conn, $sql);

	if (!$result) {
		$result = pg_query($conn, "CREATE TABLE admins(username text NOT NULL, password text NOT NULL, PRIMARY KEY (username))");
			
		if (!$result) {
			echo "An error occurred during Table creation.\n";
			exit;
		}
		
		$defaultPassword =  password_hash($defaultAdminPassword, PASSWORD_DEFAULT);
		
		$result = pg_query($conn, "INSERT INTO admins(
			username, password) VALUES ('$defaultAdminUsername', '$defaultPassword')");
	
		if (!$result) {
			echo "An error occurred during User creation.\n";
			exit;
		}
	
		$sql = "SELECT * FROM admins WHERE username = '$username'";
		$result = pg_query($conn, $sql); 
		if (!$result) {
			echo "An error occurred during Data Read.\n";
			exit;
		}		
	}
	
	if(pg_num_rows($result) == 1)
	{
		if(password_verify($password, pg_fetch_result($result, 0, 1)))
		{
			$_SESSION['userid'] = pg_fetch_result($result, 0, 0);
		}else
		{
			$errorString = "Wrong password.";
		}
			
	} else
	{
		$errorString = "Unknown User.";
	}
}

if(isset($_SESSION['userid']))
{
	header('Location: admin.php');
	exit;
}
?>

<html>
<head>
	<title>MapView Adm</title>
	<meta charset="UTF-8">
	
	<link rel=icon href="../favicon.png">
	
	<link rel="stylesheet" href="../jquery-ui/jquery-ui.min.css" />
	<link rel="stylesheet" href="adminDesign.css" />
	
	<script src="../js/jquery.min.js"></script>
	<script src="../jquery-ui/jquery-ui.min.js"></script>
	
</head>
<body>

	<div id="adminDiv">
		<div id="loginDiv">
			<p> 
			<?php 
				if(isset($errorString))
					echo $errorString;
				else
					echo "<h2>Please sign in.</h2>";
			?>
 
			<form action="?login=1" method="post" id="loginForm">
				<label for="usernameBox">Username:</label><br>
				<input type="text" size="50" maxlength="250" name="username" id="usernameBox" class="TextBox"><br><br>
				 
				<label for="passwordBox">Password:</label><br>
				<input type="password" size="50"  maxlength="250" name="password"  id="passwordBox" class="TextBox"><br>
				<br>
				<input type="submit" value="Login" class="TextBox">
			</form> 
		</div>
	</div>
	
	<script type="text/javascript">
		$("#usernameBox").addClass("ui-widget ui-widget-content ui-corner-all");
		$("#passwordBox").addClass("ui-widget ui-widget-content ui-corner-all");
	</script>
	
</body>
</html>
