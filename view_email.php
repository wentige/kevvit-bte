<?php
    if (isset($_GET['id'])) {
        $config = parse_ini_file('config.ini');
        $servername = $config['db_host'];
        $username = $config['db_user'];
        $password = $config['db_password'];
        $database = $config['db_name'];

        $conn = new mysqli($servername, $username, $password, $database);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        } 
        
        $emailuid = $_GET['id'];


        $sql = "SELECT * FROM emails WHERE emailuid = \"" . $emailuid . "\"";
        $info = array();
        $result = $conn->query($sql);
        if ($result === false) {
            echo "Error: " . $sql . "<br>" . $conn->error."<br/>";
        } elseif ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                array_push($info, array($row["id"], $row["emailuid"], $row["sendername"], $row["senderaddr"], $row["title"], $row["body"], $row["date"]));
            }
        }
    } else {
        echo "<h1> ERROR </h1>";
        echo "<p>No email ID specified. Use emailmonitorpage.php</p>";
    }
    

?>

<html>
<head>
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

		<table border="1">
    <tr style="background-color: #eee;">
      
      <th>Details</th>
    </tr>
	
	<?php
	// Set the background color of the table
	
	$colour = "#c3cde6";
	foreach ($info as $row){
		echo "<h1> " . $row[4] . "</h1>";
		echo "<h3> " . $row[2] . "</h3>";
		echo "<h3> " . $row[3] . "</h3>";
		echo "<h3> " . $row[6] . "</h3>";
        echo "<title>" . $row[4] . "</title>";
	?>
	<tr>
        <td class="center" bgcolor="<?= $colour ?>"><?= nl2br($row[5]) ?></td>
	</tr>
	<?php
	}
	
	
	?>
</table>