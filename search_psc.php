<link rel="stylesheet" href="style/style.css">

<div id="header">
<?
include 'style/header.php';
?>
</div>

<?

//-------------------------------------------------------------------
//-------------------------------------------------------------------
//Includes
include 'generate_data.php';
include 'generate_JSON.php';
include 'premade_invs.php';
include 'random_inventors.php';
include 'finfet_correction.php';
include 'network_functions.php';

$ip=$_SERVER['REMOTE_ADDR'];
date_default_timezone_set('America/Los_Angeles');
$current_datetime=date("Y-m-d H:i:s");

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Defaults
include 'defaults.php';

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Database connections

//Making this a separate file to keep security while allowing me to share the rest of the code easily
include 'db_connections.php';
//Users and passwords loaded

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Retrieve user choices from earlier input, check for problems in inputs

echo "<br><br>";

//Generations
if(isset($_POST['gensend'])) {
	$gensend = $_POST['gensend'];
	//Sanity check:
	if ($gensend == 1 || $gensend == 2 || $gensend == 3) $generation = $gensend;
}
else $generation = $def_gen;

//Start date
if(isset($_POST['startdate'])) {
	$date_start = $_POST['startdate'];
}
else $date_start = $def_start;

//End date
if(isset($_POST['enddate'])) {
	$date_end = $_POST['enddate'];
}
else $date_end = $def_end;

//Which type of date
if(isset($_POST['use_apps'])) {
	$use_apps_table_for_dates = $_POST['use_apps'];
}

//Origin inventor IDs
if(isset($_POST['origins'])) {
	$origins = $_POST['origins'];

	//Confirm they're formatted right
	$origin_ids = confirm_ids($origins);
	
	//Since someone apparently pressed "generate" for us to be here, let's add to the log
	$log_ids = str_replace("'","",$origin_ids); // To prevent SQL injection
	
	$counter_query = "INSERT INTO counter (generated,ip,date_start,date_end,generation,origin_ids) VALUES ('$current_datetime','$ip','$date_start','$date_end','$generation','$log_ids')";
	$counter = mysql_query($counter_query, $dbh_local) or die(mysql_error());

}
else {
	$def_ids = implode(",", $def_ids);
	$origin_ids = $def_ids;	
}


//Is FinFET? Checking if any of the origin IDs are FinFET inventors
$ids = explode(",", $origin_ids);
$holder_array = array_intersect($ids,$UCB_finfet_ids);
if(!empty($holder_array) ){	$is_finfet = 1; }
else{ $is_finfet = 0; }


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Process the sub-class search if someone did that

if(isset($_POST['subclass_search'])) {

	$main_class = $_POST['main_class'];
	$sub_class = $_POST['sub_class'];
	
	$date_start = $_POST['class_start_date'];
	$date_end = $_POST['class_end_date'];
	
	$use_apps_dates = $_POST['class_use_apps'];
	
	//Strip out apostophes as a basic security precaution
	$main_class = str_replace("'"," ",$main_class);
	$sub_class = str_replace("'"," ",$sub_class);

    $output_string .= "Class selected is $main_class/$sub_class<br>";
	
	if ($use_apps_dates == 1) {
		$output_string .= "Finding inventors applying for patents in this sub-class between $date_start and $date_end... <br>";
	}
	else {
		$output_string .= "Finding inventors granted patents in this sub-class between $date_start and $date_end... <br>";
	}
	//echo "Class selected is $main_class/$sub_class";
	
	if ($use_apps_dates == 1) {
	
		$sql  = "SELECT * FROM application JOIN uspc ON uspc.patent_id = application.patent_id ";
		$sql .=           "				   JOIN patent_inventor ON application.patent_id = patent_inventor.patent_id ";
		$sql .=           "WHERE mainclass_id = '$main_class' AND subclass_id = '$sub_class' ";
		$sql .= 	      "AND application.date < '$date_end' AND application.date > '$date_start' ";
	}
	else {
		$sql  = "SELECT * FROM patent JOIN uspc ON uspc.patent_id = patent.id ";
		$sql .=           "				   JOIN patent_inventor ON patent.id = patent_inventor.patent_id ";
		$sql .=           "WHERE mainclass_id = '$main_class' AND subclass_id = '$sub_class' ";
		$sql .= 	      "AND patent.date < '$date_end' AND patent.date > '$date_start' ";	
	}
	
	$output_string .= "<br><br>SQL is $sql<br><br>";
	
	$result = mysql_query($sql, $dbh_pat) or die(mysql_error());

	$ids = array();
	while($row = mysql_fetch_array($result)) {
		$ids[] = $row['inventor_id'];
	}
	$ids = array_unique($ids);
	$count = count($ids);
	
	$output_string .= "Number of inventors found in this subclass and date range: $count<br>";
	//echo "Testing: Count of inventors in this subclass is $count<br><br>";
	//print_r($ids);
	
	$people = implode(",", $ids);
	
	$origin_ids = $people;
	
	//Log this
	$counter_query = "INSERT INTO classes_searched (class_main,class_sub,datetime,ip) VALUES ('$main_class','$sub_class','$current_datetime','$ip')";

	$counter = mysql_query($counter_query, $dbh_local) or die(mysql_error());
	
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//What's printed on the screen

//Header
/*echo "<table><tr >";
echo "<td width='600'>";
echo "<h2><b>Patent Co-Inventor Network Visualization Tool</b></h2>";
echo "<font color='red'>Beta version 0.82 (updated 3 April 2015) - Please report bugs to oreagan@berkeley.edu<br>-Note: We are having some underlying data issues discussed in the User Guide. This means diagrams will be accurate, but possibly slightly incomplete when using 'Application Date' mode. 'Granted date' mode should be fine</font><br>";
echo "<b><a href='User_Guide_Patent_Network_Tool.pdf'>User Guide is available here</a> with details on using this tool</b>";
echo "</td>";

echo "<td align='right'>";
echo "<img src='http://funglab.berkeley.edu/mobility/fung.png'><br>";
echo "</td></tr>";

echo "<tr>";
echo "<td>Sample Images:<br><a href='../1990-2001_2gen_FinFET.png'><img src='../1990-2001_2gen_FinFET_shrunk.png'></a></td>";
echo "<td><br><a href='../1996-2001_3gen_FinFET.png'><img src='../1996-2001_3gen_FinFET_shrunk.png'></a></td>";
echo "</tr>";

echo "</table>";
//End header 
*/


echo "<body>";


// -------------------------------------------------------------------
//First cell (left half of screen) is the user choices, right half will be processing area
//Start the overall table
echo "<table border='1'><tr><td>";

// -------------------------------------------------------------------
//Start user choices area

//User choices
echo "<div class='CSSTableGenerator' >";


echo "<table border='1'>";
echo "<form action='processing.php' method='post'>";
echo "<tr><td>Select Parameters</td><td>  </td></tr>";

//Generations
echo "<tr><td><b>Number of generations to iterate.</b></td>";
echo "<td><select name='gensend'>";
	if ($generation == 1) echo "<option value='1' selected='selected'>1</option>";
		else echo "<option value='1'>1</option>";
	if ($generation == 2) echo "<option value='2' selected='selected'>2</option>";
		else echo "<option value='2' selected='selected'>2</option>";
	if ($generation == 3) echo "<option value='3' selected='selected'>3</option>";
		else echo "<option value='3'>3</option>";
echo "</select></td></tr>";

echo "<tr><td>1 generation finds co-inventors on patents filed in the chosen period.<br>";
echo "2 generations finds co-inventors of these co-inventors, on patents filed in the chosen period. Etc.<br>";
echo "3 generations finds co-inventors of these co-co-inventors.<br><br></td><td></td></tr>";

//State date
echo "<tr><td>Start date (YYYY/MM/DD):</td>";
echo "<td><input maxlength='10' type='text' name='startdate' value='$date_start'></td></tr>";

//End date
echo "<tr><td>End date:</td>";
echo "<td><input maxlength='10' type='text' name='enddate' value='$date_end'></td></tr>";

//Application date, or date granted?
echo "<tr><td>Use the date the patent was applied for, or the date the USPTO granted it?</td>";
echo "<td>";
if ($use_apps_table_for_dates == 1) {echo "
<input type='radio' value='1' name='use_apps' checked='checked'>Application date<br />
<input type='radio' value='0' name='use_apps'>Granted date<br /> ";
}
else { echo "
<input type='radio' value='1' name='use_apps'>Application date<br />
<input type='radio' value='0' name='use_apps' checked='checked'>Granted date<br /> ";
}
echo "</td></tr>";


//Inventor IDs
echo "<tr><td valign='top'>Inventor IDs (enter manually, or enter name below):<br>";

//    In this section, print a table explaining what the origin IDs are
//       To make the manual entry area match this ordering, also re-index the origin IDs
	echo "<br><Names selected:<br>";
	echo "<table cellpadding='4'>";
	echo "<tr><td><b>Last</b></td><td><b>First</b></td><td><b>ID</b></td><td></td></tr>";

	$temp_ids = explode(",", $origin_ids);
	$temp_count = count($temp_ids);

	$sql1  = "SELECT * FROM inventor WHERE id = '$temp_ids[0]' ";
	for ($i = 1; $i<$temp_count; $i++) {
			$sql1 .= "OR id = '$temp_ids[$i]' ";
	}
	$sql1 .= "ORDER BY name_last";
	//echo "SQL is $sql1";
	
	$result1 = mysql_query($sql1, $dbh_pat) or die(mysql_error());
	while ($row1 = mysql_fetch_array($result1)) {
		$temp_first  = $row1['name_first'];
		$temp_last  = $row1['name_last'];
		$temp_id  = $row1['id'];
		//Want the first part of the ID separately so I can generate a link to Google Patents
			$temp_split = explode("-", $temp_id);
			$sample_patent = $temp_split[0];
	
		$origin_ids_reindexed[] = $temp_id;
	
		echo "<tr><td>$temp_last</td><td>$temp_first</td><td>$temp_id</td><td><a href='http://www.google.com/patents/US$sample_patent'  target='_blank'>Sample Patent</a></td></tr>";
	}
		$origin_ids = implode(",", $origin_ids_reindexed);

echo "</table>";
echo "</div>";
//    Done printing this table

echo "</td>";

echo "<td>Comma-separated values:<br><textarea rows='10' cols='35' name='origins'>$origin_ids</TEXTAREA></td></tr>";

echo "</table>";

echo "<input type='hidden' name='generate' value='TRUE'>";
echo "<input type='submit' name='Submit' value='Generate'/>";
echo "</form>";
//End form

echo "<br><br>";

// -------------------------------------------------------------------
// -------------------------------------------------------------------

//Section to search by USPTO patent sub-class


echo "<b>Search by Patent Sub-class (Optional)</b><br>";
echo "<ul>";
echo "<li>These results get really big really fast, <u>so generations 1 or 2 at most are recommended</u>, and a short time span.</li>";
echo "<li>This search will replace existing patent IDs above</li>";
echo "<li>You can find this information by going to <a href='http://www.google.com/patents/'>Google Patents</a>, finding the patent you wish to know its classifications, and looking in the Classifications section. That section is highlighted in the following example image: <a href='classifications_guide.png'>classifications_guide.png</a></li>";
echo "<li>More information on USPTO classifications, and full documentation of them, can be found at <a href='http://www.uspto.gov/web/patents/classification/'>http://www.uspto.gov/web/patents/classification/</a></li></ul>";

echo "<form action='$PHP_SELF' method='post'>";

echo "Patent subclass (eg 438/283):<br>";

echo "<table border='1'>";

echo "<tr><td>Main class</td><td>Sub-class</td></tr>";
echo "<tr>";
echo "<td><input type='text' name='main_class' value=''></td>";
echo "<td><input type='text' name='sub_class' value=''></td>";
echo "</tr>";

//State date
echo "<tr><td>Start date (YYYY/MM/DD):</td>";
echo "<td><input maxlength='10' type='text' name='class_start_date' value='$date_start'></td></tr>";

//End date
echo "<tr><td>End date:</td>";
echo "<td><input maxlength='10' type='text' name='class_end_date' value='$date_end'></td></tr>";

echo "<tr><td></td><td>
<input type='radio' value='1' name='class_use_apps' checked='checked'>Application date<br />
<input type='radio' value='0' name='class_use_apps'>Granted date<br /> 
</td></tr>";

echo "</table>";

echo "<input type='hidden' name='subclass_search' value='TRUE'>";
echo "<br><input type='submit' name='search' value='Search'/>";
echo "</form>";


echo "</body>";

?>
