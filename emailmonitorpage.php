<?php
	session_start();

	function getSessionValue($field) {
		if (isset($_SESSION[$field])) {
			$input = $_SESSION[$field];
		} else {
			$input = '';
		}
		return $input;
	}

	function getPostValue($field) {
		if (isset($_POST[$field])) {
			$input = $_POST[$field];
			if ($input != '' && strpos($input, '/') !== false && ($field === "beforedate" || $field === "afterdate")) {
				$dateTime = DateTime::createFromFormat("m/d/Y", $input);
				$errors = DateTime::getLastErrors();
				if ($dateTime === false || $errors['error_count'] > 0 || $errors['warning_count'] > 0) {
					// Invalid date format or input
					echo "Invalid date format. Please enter dates in the format MM/DD/YYYY.";
					$input = '';
				} else {
					// Format the date as needed
					$input = $dateTime->format("Y-m-d");
				}
			}
			$_SESSION[$field] = $input;
		} else {
			$input = '';
		}
		return $input;
	}

	function truncate($str, $maxChar) {
		if (strlen($str) > $maxChar) {
			return substr($str, 0, $maxChar) . "...";
		} else {
			return $str;
		}
	}

	$config = parse_ini_file('config.ini');
	$servername = $config['db_host'];
	$username = $config['db_user'];
	$password = $config['db_password'];
	$database = $config['db_name'];

	$conn = new mysqli($servername, $username, $password, $database);
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} 

	$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
	$emailsPerPage = 20;
	$startIndex = ($currentPage - 1) * $emailsPerPage;

	$sql = "SELECT * FROM emails WHERE NOT id = 0";

	if ($_POST) {
		if(isset($_POST['clearbtn'])) {
			$_POST['sender'] = '';
			$_POST['address'] = '';
			$_POST['title'] = '';
			$_POST['beforedate'] = '';
			$_POST['afterdate'] = '';
			$_SESSION['sender'] = '';
			$_SESSION['address'] = '';
			$_SESSION['title'] = '';
			$_SESSION['beforedate'] = '';
			$_SESSION['afterdate'] = '';
		} elseif (isset($_POST['searchbtn'])) {
			$sender = getPostValue('sender');
			$sql = $sql . " AND sendername LIKE '%" . $sender . "%'";

			$address = getPostValue('address');
			$sql = $sql . " AND senderaddr LIKE '%" . $address . "%'";

			$title = getPostValue('title');
			$sql = $sql . " AND title LIKE '%" . $title . "%'";

			$beforedate = getPostValue('beforedate');
			if ($beforedate != '') $sql = $sql . " AND DATE(date) <= '" . $beforedate . "'";

			$afterdate = getPostValue('afterdate');
			if ($afterdate != '') $sql = $sql . " AND DATE(date) >= '" . $afterdate . "'";
			
		} 
		header("Location: emailmonitorpage.php?page=1");
	} else {
		$_POST['sender'] = getSessionValue('sender');
		$_POST['address'] = getSessionValue('address');
		$_POST['title'] = getSessionValue('title');
		$_POST['beforedate'] = getSessionValue('beforedate');
		$_POST['afterdate'] = getSessionValue('afterdate');
		

		$sender = getSessionValue('sender');
		$sql = $sql . " AND sendername LIKE '%" . $sender . "%'";

		$address = getSessionValue('address');
		$sql = $sql . " AND senderaddr LIKE '%" . $address . "%'";

		$title = getSessionValue('title');
		$sql = $sql . " AND title LIKE '%" . $title . "%'";

		$beforedate = getSessionValue('beforedate');
		if ($beforedate != '') $sql = $sql . " AND DATE(date) <= '" . $beforedate . "'";

		$afterdate = getSessionValue('afterdate');
		if ($afterdate != '') $sql = $sql . " AND DATE(date) >= '" . $afterdate . "'";
	}

	$sql = $sql . " ORDER BY date DESC LIMIT " . $emailsPerPage . " OFFSET " . $startIndex;
	echo $sql;
	$info = array();
	$result = $conn->query($sql);
	if ($result === false) {
		echo "Error: " . $sql . "<br>" . $conn->error."<br/>";
	} elseif ($result->num_rows > 0) {
		while ($row = $result->fetch_assoc()) {
			array_push($info, array($row["id"], $row["emailuid"], $row["sendername"], $row["senderaddr"], $row["title"], $row["body"], $row["date"]));
		}
	}
	
	$frompos = strpos($sql, "FROM");
	$orderpos = strpos($sql, "ORDER");
	$substr = substr($sql, $frompos, $orderpos - $frompos);

	$sql = "SELECT count(*) AS total " . $substr;
	$result = $conn->query($sql);
	if ($result === false) {
		echo "Error: " . $sql . "<br>" . $conn->error."<br/>";
	} elseif ($result->num_rows > 0) {

		$row = $result->fetch_assoc();
		$totalEmails = $row['total'];
	}
	if ($totalEmails > 0) {
		$totalPages = ceil($totalEmails / $emailsPerPage);
	} else {
		$totalPages = 1;
	}
?>

<html>
<head>
<title>Emails</title>
<style>
  body { width: 1560px; margin: 0 auto; }
  table { border-collapse: collapse; width: 100%; }
  table, td, th { 
	border: 1px solid gray; 
	padding: 5px 10px; 
  }
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
  /* Styling for pagination links */
  .pagination {
            font-size: 20px; /* Adjust the font size as per your preference */
            display: flex;
            justify-content: center;
            align-items: center;
        }
	.pagination a {
		margin: 10px;
		text-decoration: none;
		color: #333;
	}
	.current-page {
		font-weight: bold;
	}
	.go-btn {
		margin-left: 10px;
	}

	.go-form {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
        }
	.go-input {
		width: 100px; /* Adjust the width as needed */
		padding: 5px;
		font-size: 16px;
	}
</style>

<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link rel="stylesheet" href="/resources/demos/style.css">
<script src="https://code.jquery.com/jquery-3.6.0.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script>
	$( function() {
	$( ".datepicker" ).datepicker();
	} );
</script>
<script>
	function myFunction() {
		alert("I am an alert box!"); // this is the message in ""
	}
</script>

</head>
<body>
<br>
<h1>Inbox</h1>


<!-- SEARCH TABLE -->
<table border="1">
<tr>
    <td style="background-color: #e9ebf4;">
<form name="inputForm" method="POST">
	<b>Sender:</b> <input name="sender" type="text" style="height:25pt;width:100pt;" value="<?php echo isset($_POST['sender']) ? $_POST['sender'] : '' ?>">
   	<b>Address:</b> <input name="address" type="text" style="height:25pt;width:100pt;" value="<?php echo isset($_POST['address']) ? $_POST['address'] : '' ?>">
  	<b> Title: </b> <input name="title" type="text" style="height:25pt;width:100pt;" value="<?php echo isset($_POST['title']) ? $_POST['title'] : '' ?>">
	<b> After (MM/DD/YYYY):	</b> <input name="afterdate" class="datepicker" style="height:25pt;width:100pt;" value="<?php echo isset($_POST['afterdate']) ? $_POST['afterdate'] : '' ?>">
  	<b> Before (MM/DD/YYYY):	</b> <input name="beforedate" class="datepicker" style="height:25pt;width:100pt;" value="<?php echo isset($_POST['beforedate']) ? $_POST['beforedate'] : '' ?>">
  	
   <input type="submit" name="searchbtn" value="Search" id="searchbtn" />
   <input type="submit" name="clearbtn" value="Clear Filters" id="clearbtn" />
	<br>
	
</form>
</td>
</tr>
</table>



<!-- DISPLAY TABLE -->
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
		$row[2] = truncate($row[2], 30);
		$row[3] = truncate($row[3], 30);
		$row[4] = truncate($row[4], 100);
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


<?php
	echo "<div class=\"pagination\">";
    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        echo "<a href=\"emailmonitorpage.php?page={$prevPage}\">◄ Previous</a>";
    }

	$minPage = max(1, $currentPage - 2);
    $maxPage = min($totalPages, $currentPage + 2);

	for ($page = $minPage; $page <= $maxPage; $page++) {
        if ($page == $currentPage) {
            echo "<span class=\"current-page\">$page</span>";
        } else {
            echo "<a href=\"emailmonitorpage.php?page={$page}\">$page</a>";
        }
    }

	if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        echo "<a href=\"emailmonitorpage.php?page={$nextPage}\">Next ►</a>";
    }

	echo "</div>";

    // Add option to go to a specific page below the numerical pages
    echo "<div class=\"go-form\">";
    echo "<form action=\"emailmonitorpage.php\" method=\"get\">";
    echo "<input type=\"number\" name=\"page\" min=\"1\" max=\"{$totalPages}\" class=\"go-input\" placeholder=\"Go to page\">";
    echo "<input type=\"submit\" value=\"Go\" class=\"go-btn\">";
    echo "</form>";
    echo "</div>";

?>
