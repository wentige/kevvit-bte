<?php
	$config = parse_ini_file('config.ini');
	$servername = $config['db_host'];
	$username = $config['db_user'];
	$password = $config['db_password'];
	$database = $config['db_name'];

	$conn = new mysqli($servername, $username, $password, $database);
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} 

	$sql = "SELECT * FROM emails ORDER BY date DESC";
	$info = array();
	$result = $conn->query($sql);
	if ($result === false) {
		echo "Error: " . $sql . "<br>" . $conn->error."<br/>";
	} elseif ($result->num_rows > 0) {
		while ($row = $result->fetch_assoc()) {
			array_push($info, array($row["id"], $row["emailuid"], $row["sendername"], $row["senderaddr"], $row["title"], $row["body"], $row["date"]));
		}
	}
	foreach ($info as $row) {
		#echo "id: " . $row[0] . "<br>sender: " . $row[1] . "<br>title: " . $row[2] . "<br>body: " . $row[3] . "<br><br>";
	}


?>

<html>
<head>
<title>Emails</title>
<style>
  body { width: 1560px; margin: 0 auto; }
  table { border-collapse: collapse; width: 100%; }
  table, td, th { border: 1px solid gray; padding: 5px 10px; }
  input[type=submit] { padding: 4px 10px; }
  input[type=text] { font-size:15px; padding: 5px 10px; }
  .center { text-align: center; }
  #newlist li
  {
	text-decoration: none;
	list-style: none;
	display:block;	
	margin:1px;
	text-align:left;
  }
</style>

</head>
<body>
<br>
<h1>Inbox</h1>
<br>

<table border="1">
    <tr style="background-color: #eee;">
      
      <th>Sender</th>
	  <th>Address</th>
	  <th>Title</th>
	  <th>Date</th>
    </tr>
	
	<?php
	// Set the background color of the table
	
	$colour = "#c3cde6";
	foreach ($info as $row){
		
	?>

	<tr bgcolor="<?= $colour ?>">
        <td class="center"><?= $row[2] ?></td>
        <td class="center"><?= $row[3] ?></td>
	<td class="center"><a href="view_email.php?id=<?= $row[1] ?>" target="_blank">{$row[4]}</a></td>
        <td class="center"><?= $row[6] ?></td>
	</tr>

        <?php
	}
	
	
	?>
</table>
