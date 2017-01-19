<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

session_start();

if(isset($_GET['login']))
{	
	include "db-settings.php";
	include "config.php";
	include "logHelper.php";
	
	$conn = pg_connect($connString)
		or die('Could not connect: ' . pg_last_error());
	
	$username = $_POST['username'];
	$password = $_POST['password'];
	
	$sql = "SELECT * FROM $adminsTableName WHERE username = '$username'";
	$result = pg_query($conn, $sql);
	if(pg_num_rows($result) == 0)
	{
		// No result. Check if db exists
		if(pg_num_rows(pg_query($conn, "SELECT 1 FROM $adminsTableName LIMIT 1")) == 0)
		{
			// No Result -> Table doesn't exist or Db error.
			writeLog("Failed to access admin table.");
			writeLog(pg_last_error($conn));
			
			// ******** First Time Setup block ********
			writeLog("Attempting Performing first time setup.");
			
			$result = pg_query($conn, "CREATE TABLE $adminsTableName(username text NOT NULL, password text NOT NULL, PRIMARY KEY (username))");
			if (!$result) {
				writeLog("An error occurred during admin Table creation.");
				exit;
			}
			
			$defaultPassword =  password_hash($defaultAdminPassword, PASSWORD_DEFAULT);
			
			$result = pg_query($conn, "INSERT INTO $adminsTableName(username, password) VALUES ('$defaultAdminUsername', '$defaultPassword')");
			if (!$result) {
				writeLog("An error occurred during User creation.");
				exit;
			}
			
			$sql = "CREATE TABLE IF NOT EXISTS $generalInfoTableName(
						para text NOT NULL,
						time timestamp,
						sInfo text,
						iInfo integer,
						eInfo integer,
						PRIMARY KEY (para))";
			$result = pg_query($conn, $sql);
			if (!$result) {
				writeLog("An error occurred during general Table creation.");
				exit;
			}
			
			$sql = "CREATE TABLE IF NOT EXISTS $settingsTableName(
						para text NOT NULL,
						value real,
						PRIMARY KEY (para))";
			$result = pg_query($conn, $sql);
			if (!$result) {
				writeLog("An error occurred during settings Table creation.");
				exit;
			}
			
			include "saveSettings.php";
			
			$errorString = "First time setup performed. Please retry.";
			// ****************************************
		} else
		{
			$errorString = "Unknown User or Database error.";
			writeLog("Login Attempted: " . $errorString);
		}
	} else if(pg_num_rows($result) == 1)
	{
		if(password_verify($password, pg_fetch_result($result, 0, 1)))
		{
			$_SESSION['userid'] = pg_fetch_result($result, 0, 0);
			writeLog("Login Successful: " . pg_fetch_result($result, 0, 0));
		} else
		{
			$errorString = "Wrong password.";
			writeLog("Login Attempted: " . $errorString . " User: " . pg_fetch_result($result, 0, 0));
		}	
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
