<link rel="stylesheet" href="style.css">

<?

include 'generate_data.php';
include 'generate_JSON.php';
include 'premade_invs.php';
include 'random_inventors.php';

$ip=$_SERVER['REMOTE_ADDR'];
date_default_timezone_set('America/Los_Angeles');
$current_datetime=date("Y-m-d H:i:s");

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Defaults

//Set number of iterations to go through.
//Co-inventors: 1
//Co-co-inventors: 2
//Co-co-co-inventors: 3  etc.
$def_gen = '2';

//All UC Berkeley FinFET PIs
//Described in included premade_invs.php
$def_ids = $UCB_finfet_ids; 
//$def_ids = $DARPA_AME_ids;
//def_ids = $code_438_151_ids;

$def_start = '1998/01/01';
$def_end = '2001/01/01';

$is_finfet = 1; //Puts in (on 1) a special correction since finfet patent isn't in apps table

//Main JSON file
$json_file = 'src.json';
//JSON file for the version that charge industry/academy
$univ_json = 'src_univ.json';

//Use application date or patent_granted date
$use_apps_table_for_dates = 1; // Use the app table
//$use_apps_table_for_dates = 0; //Use the granted date

$output_string = "Defaults loaded! Press Generate to see results, or enter new selections.<br><Br>";
// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Database connections

//Making this a separate file to keep security while allowing me to share the rest of the code easily
include 'db_connections.php';
//Users and passwords loaded

$table = 'socialnetwork';

$dbh_pat = mysql_connect($host_pat, $user_pat, $pass_pat, true) or die("Unable to connect to MySQL");
$dbh_local = mysql_connect($host_local, $user_local, $pass_local, true) or die("Unable to connect to MySQL");

mysql_select_db("uspto", $dbh_pat) or die("Could not select uspto");
mysql_select_db("wbtmougo_dblood", $dbh_local) or die("Could not select local");


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Retrieve user choices from earlier input, check for problems in inputs

//Validation functions
function sanityCheck($string, $type, $length){
  // assign the type
  $type = 'is_'.$type;

  if(!$type($string))  {return FALSE;}
  // then we check how long the string is
  elseif(strlen($string) > $length)
    {return FALSE; }
  else  { // if all is well, we return TRUE
    return TRUE;
    }
}

function checkNumber($num, $length){
  if($num > 0 && strlen($num) == $length)  {return TRUE;}
}
//End validation functions


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

	//Remove spaces
	$origins=str_replace(" ","",$origins);

	//Make sure they're properly formatted (and not an insertion)
	$ids=str_replace("'","",$ids);
	$ids=str_replace("/t","",$ids);
	
	$ids = explode(",", $origins);
	$ids_count = count($ids);
	$good = 0;
	foreach ($ids as $id) {
		$split = explode("-", $id);
		//echo "SPLIT $split[0] - $split[1]<br>";
		if (strlen($split[0]) > 8) { $good = 1; }
		if (strlen($split[0]) < 5) { $good = 1; }
		if (strlen($split[1]) > 2) { $good = 1; }
	}
	
	if ($good == 0) {
		$ids = implode(",", $ids);
		$origin_ids = $ids;
	}
	else {
		$output_string = "Error: IDs don't seem to be in the right format. Using defaults<br><br>";
		//echo "Error: IDs don't seem to be in the right format. Using defaults<br><br>";
		$def_ids = implode(",", $def_ids);
		$origin_ids = $def_ids;
	}

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
//Processing section for the name search section below
if(isset($_POST['search_name'])) {
	
	if(!isset($_POST['has_searched'])) {
		$has_searched = 1;
	}
	else {
		$has_searched = $_POST['has_searched'] + 1;
		$previous_ids = $_POST['previous_ids'];
	}

	//First name
	if(isset($_POST['first'])) {
		$first = $_POST['first'];
	}
	else $first = "";


	//Last name
	if(isset($_POST['last'])) {
		$last = $_POST['last'];
	}
	else $last = "";

	$people = array();

	$first = strtoupper($first); //Capitalize all
	$first = str_replace("-"," ",$first); //Remove hyphens
	$first = str_replace("'"," ",$first); //Remove apostrophes
	$first = rtrim($first, '.'); //Remove trailing periods
	
	//echo "Origin name: $first $last<br><br>";
	
	$last1 = $last;
	$last2 = str_replace("'"," ",$last);
	$last3 = str_replace(" ","",$last);
	
	$sql  = "SELECT * FROM inventor WHERE name_last = '$last1' OR ";
	$sql .=           "name_last = '$last2' OR name_last = '$last3' ORDER BY name_first ";
	$result = mysql_query($sql, $dbh_pat) or die(mysql_error());

	while($row = mysql_fetch_array($result)) {
		$temp_first  = $row['name_first'];
		$orig_name = $temp_first;
		$id = $row['id'];
	
		$temp_first = strtoupper($temp_first);
		$temp_first = str_replace("-"," ",$temp_first); //Remove hyphens
		$temp_first = str_replace("'"," ",$temp_first); //Remove apostrophes
		$temp_first = rtrim($temp_first, '.'); //Remove trailing periods
		
		$smashed_first = str_replace(" ","",$temp_first);
		
		if (($first == $temp_first) || ($first == $smashed_first)) { 
			
			$output_string .= "Name added: $orig_name $last  ID: $id<br>";
			//echo "<font color='red'>Name added: $orig_name $last  ID: $id</font><br>";
			$people[] = $id;
			}
	}
	//echo "<br><br>";

	//echo "Input people is "; print_r($people); echo "<br><br>";

	$people = implode(",", $people);
	
	$origin_ids = $people;
	if(isset($_POST['has_searched'])) {
		$origin_ids = "$previous_ids,$people";
	}
	else $origin_ids = $people;
	
	//echo "User IDs to add: $people<br><br>";

	//Log this
	$counter_query = "INSERT INTO inventors_searched (name_first,name_last,datetime,ip) VALUES ('$first','$last','$current_datetime','$ip')";
	$counter = mysql_query($counter_query, $dbh_local) or die(mysql_error());


}
//End of dealing with name search input

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Process the sub-class search if someone did that

elseif(isset($_POST['subclass_search'])) {

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
//Process the sampled full class search if someone did that

elseif(isset($_POST['random_search'])) {
	
	$main_class = $_POST['main_class'];
	$year = $_POST['year'];
	
	$print = random_inventors ($main_class, $year);
	
	$origin_ids = $print;
	
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//What's printed on the screen

//Header
echo "<table><tr >";
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


echo "<body>";


// -------------------------------------------------------------------
//First cell (left half of screen) is the user choices, right half will be processing area
//Start the overall table
echo "<table border='1'><tr><td width='600'>";

// -------------------------------------------------------------------
//Start user choices area

//User choices
echo "<div class='CSSTableGenerator' >";


echo "<table border='1'>";
echo "<form action='$PHP_SELF' method='post'>";
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
//This section is for users to search for inventors


echo "<form action='$PHP_SELF' method='post'>";
echo "<b>Search by Inventor Name (Optional)</b><br>";

echo "<ul><li>Note: The first time you search, it will replace the defaults. Subsequent searches will add to the list</li></ul>";

echo "Name to insert as an origin ID (eg Steve Jobs):";
echo "<table border='1'>";

echo "<tr><td>First</td><td>Last</td></tr>";
echo "<tr>";
echo "<td><input type='text' name='first' value='$first'></td>";
echo "<td><input type='text' name='last' value='$last'></td>";
echo "</tr>";

echo "</table>";

if($has_searched >= 1) {
	echo "<input type='hidden' name='has_searched' value='$has_searched'>";
	echo "<input type='hidden' name='previous_ids' value='$origin_ids'>";
}

echo "<input type='hidden' name='search_name' value='TRUE'>";
echo "<br><input type='submit' name='Search' value='Search'/>";
echo "</form>";
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

// -------------------------------------------------------------------
// -------------------------------------------------------------------

//Section to use sampled inventors from patent class
echo "<b>Search for a sample of inventors in an entire patent class (Optional)</b><br>";
echo "<ul>";
echo "<li>This will find (up to) 10 patents/month granted in the closen patent class and year, then find their inventors</li>";
echo "<li>More information on USPTO classifications, and full documentation of them, can be found at <a href='http://www.uspto.gov/web/patents/classification/'>http://www.uspto.gov/web/patents/classification/</a></li></ul>";

echo "<form action='$PHP_SELF' method='post'>";

echo "Patent subclass (eg 257):<br>";

echo "<table border='1'>";

echo "<tr><td>Main class</td><td>Year (eg 1980)</td>";
echo "<tr>";
echo "<td><input type='text' name='main_class' value=''></td>";
echo "<td><input maxlength='10' type='text' name='year'></td>";
echo "</tr>";

echo "</table>";

echo "<input type='hidden' name='random_search' value='TRUE'>";
echo "<br><input type='submit' name='search' value='Search'/>";
echo "</form>";

echo "</td>";




// -------------------------------------------------------------------
//End left half of screen (user choices)



// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Start right half (user feedback)
echo "<td valign='top' >";

echo "<div class='CSSTableGenerator' >";
echo "<table>";
echo "<tr><td>Output</td></tr>";
echo "<tr><td><b><font color='blue'>"; echo $output_string; 


// -------------------------------------------------------------------
// -------------------------------------------------------------------

//If the Generate button has been pressed, generate the social network.
if(isset($_POST['generate'])) {

$origin_ids = explode(",", $origin_ids);	
	//Confirm
	echo "<br><br>";
	echo "<b>Generating a visualization for the following selections:</b><br>";
	echo "Generations:	$generation<br>";
	echo "Start date:	$date_start<br>";
	echo "End date:	    $date_end<br>";
	echo "Origins:   "; echo print_r($origin_ids); echo " <br>";
	//echo "Is finfet:   "; 
	//	if ($is_finfet == 1) echo "Yes";
	//	else echo "No";
	echo "<br><br>";

// -------------------------------------------------------------------
//Generate Data

//   First, check if it's using default values. If so, use cached info:

//if ( ($date_start == $def_start) &&
//	 ($date_end == $def_end) &&
//	 ($origin_ids == $def_ids) ) {

if (1 == 0) { //Temporarily removing this check
		 
		 echo "Default values detected - using cached information to save bandwidth...<br>";

	 if ($generation == 1) { 
	 	$json = 'default_1gen'; 
		$json_univ = 'default_1gen_univ'; 
		$count_inv = 23;  
		}
	 if ($generation == 2) { 
	 	$json = 'default_2gen'; 
		$json_univ = 'default_2gen_univ';
		$count_inv = 70;  
		}
	 if ($generation == 3) { 
	 	$json = 'default_3gen'; 
		$json_univ = 'default_3gen_univ';
		$count_inv = 303;  
		}
		 
}

//for everything else,
else {

//Generate the data, returning the number of final inventors
$count_inv = generate_data ($origin_ids, $generation, $date_start, $date_end, $is_finfet);
$json = 'src'; 
$json_univ = 'src_univ';

//Generate the JSON
generate_JSON();


}

//I've found through approximations that the follow scaling works for the rendering
$scale = 0.0025 * $count_inv * $count_inv + 3.38 * $count_inv + 1753;


// -------------------------------------------------------------------
//Print links to the rendered versions

echo "<br><br>";

echo "<b><h2>Success! Please follow <a href='render.php?screen_width=$scale&screen_height=$scale&charge=7000&json=$json' target='_blank'>this link</a> to your patent network map.</h1></b>";

echo "<br><br>";

echo "<b><h2>Or the industry/academy version: <a href='render.php?screen_width=$scale&screen_height=$scale&charge=7000&json=$json_univ' target='_blank'>this link</a></h1></b>";


} //End "IF Generate button pressed" section

// -------------------------------------------------------------------
// -------------------------------------------------------------------

echo "</b></font></td></tr>";
echo "</table>";
echo "</div>";

echo "</td></tr></table>";


echo "</body>";

?>
