<?

include 'generate_data.php';
include 'generate_JSON.php';
include 'premade_invs.php';

//User data for logging purposes
$ip=$_SERVER['REMOTE_ADDR'];
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

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Database connections

//NOTE: Editing these out for security reasons

//Patents DB
$user_pat = /*REMOVED*/
$pass_pat = /*REMOVED*/
$host_pat = /*REMOVED*/

//DB for holding this info
$user_local = /*REMOVED*/
$pass_local = /*REMOVED*/ 
$host_local = /*REMOVED*/

$table = 'socialnetwork';

$dbh_pat = mysql_connect($host_pat, $user_pat, $pass_pat, true) or die("Unable to connect to MySQL");
$dbh_local = mysql_connect($host_local, $user_local, $pass_local, true) or die("Unable to connect to MySQL");

mysql_select_db(/*REMOVED*/, $dbh_pat) or die("Could not select uspto");
mysql_select_db(/*REMOVED*/, $dbh_local) or die("Could not select local");


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//This section processes the information if someone has pressed "Generate" already

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

//User inputs:

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

//Seed inventor IDs
if(isset($_POST['origins'])) {
	$origins = $_POST['origins'];

	//Remove spaces
	$origins=str_replace(" ","",$origins);

	//Make sure they're properly formatted (and not an insertion)
	$ids = explode(",", $origins);
	$ids_count = count($ids);
	$good = 0;
	foreach ($ids as $id) {
		$split = explode("-", $id);
		//echo "SPLIT $split[0] - $split[1]<br>";
		if (!((strlen($split[0]) == 7 || strlen($split[0]) == 6) && strlen($split[1]) == 1)) $good = 1;
	}
	
	if ($good == 0) {
		$ids = implode(",", $ids);
		$origin_ids = $ids;
	}
	else {
		echo "Error: IDs don't seem to be in the right format. Using defaults<br><br>";
		$def_ids = implode(",", $def_ids);
		$origin_ids = $def_ids;
	}

	//Since someone apparently pressed "generate" for us to be here, let's add to the log
	//   This is for later stats on how often, how many visualization we make
	$counter_query = "INSERT INTO counter (generated,ip) VALUES ('$current_datetime','$ip')";
	$counter = mysql_query($counter_query, $dbh_local) or die(mysql_error());

}
else {
	$def_ids = implode(",", $def_ids);
	$origin_ids = $def_ids;	
}


//Is FinFET? Checking if any of the origin IDs are FinFET inventors (a specific project we're working on)
//This correction is necessary until we correct a minor error in this specific data point
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
			echo "<font color='red'>Name added: $orig_name $last  ID: $id</font><br>";
			$people[] = $id;
			}
	}
	echo "<br><br>";

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
	
	//Strip out apostophes as a basic security precaution
	$main_class = str_replace("'"," ",$main_class);
	$sub_class = str_replace("'"," ",$sub_class);

	//echo "Class selected is $main_class/$sub_class";
	
	$sql  = "SELECT * FROM application JOIN uspc ON uspc.patent_id = application.patent_id ";
	$sql .=           "				   JOIN patent_inventor ON application.patent_id = patent_inventor.patent_id ";
	$sql .=           "WHERE mainclass_id = '$main_class' AND subclass_id = '$sub_class' ";
	$sql .= 	      "AND application.date < '$date_end' AND application.date > '$date_start' ";
	$result = mysql_query($sql, $dbh_pat) or die(mysql_error());

	$ids = array();
	while($row = mysql_fetch_array($result)) {
		$ids[] = $row['inventor_id'];
	}
	$ids = array_unique($ids);
	$count = count($ids);
	
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
//Allow user choices

echo "<h2><b>Patent Co-Inventor Network Visualization Tool</b></h2>";
echo "<b><a href='User_Guide_Patent_Network_Tool.pdf'>User Guide is available here</a> with details on using this tool</b>";
echo "<br><br>";

//Generations
echo "<table border='1'>";

echo "<form action='$PHP_SELF' method='post'>";
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
echo "3 generations finds co-inventors of these co-co-inventors.<br><br></td></tr>";

//State date
echo "<tr><td>Start date (YYYY/MM/DD):</td>";
echo "<td><input maxlength='10' type='text' name='startdate' value='$date_start'></td></tr>";

//End date
echo "<tr><td>End date:</td>";
echo "<td><input maxlength='10' type='text' name='enddate' value='$date_end'></td></tr>";

//Inventor IDs
echo "<tr><td valign='top'>Inventor IDs (enter manually, or enter name below):<br>";

//    In this section, print a table explaining what the origin IDs are
	echo "<br><Names selected:<br>";
	echo "<table cellpadding='4'>";
	echo "<tr><td><b>Last</b></td><td><b>First</b></td><td><b>ID</b></td></tr>";

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
	
		echo "<tr><td>$temp_last</td><td>$temp_first</td><td>$temp_id</td></tr>";
	}

echo "</table>";

//    Done printing this table

echo "</td>";

echo "<td><textarea rows='10' cols='35' name='origins'>$origin_ids</TEXTAREA></td></tr>";

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
echo "<b>Search by Inventor Name</b><br>";

echo "Name to insert as an origin ID (Note: The first time you search, it will replace the defaults. Subsequent searches will add to the list)";
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

echo "<br><br>";
echo "<b>Search by Patent Sub-class</b><br>";
echo "-These results get really big really fast, <u>so generations 1 or 2 at most are recommended</u>, and a short time span. This will replace existing patent IDs:<br>";
echo "-This search uses the dates above to generate the origin_ids<br><br>";


echo "<form action='$PHP_SELF' method='post'>";

echo "Patent subclass (eg 438/283, found at <a href='http://www.uspto.gov/web/patents/classification/'>http://www.uspto.gov/web/patents/classification/</a>)<br>";
echo "For an example of how to find this on a google patent search here this image: <a href='classifications_guide.png'>classifications_guide.png</a> <br><br>";
echo "<table border='1'>";

echo "<tr><td>Main class</td><td>Sub-class</td></tr>";
echo "<tr>";
echo "<td><input type='text' name='main_class' value=''></td>";
echo "<td><input type='text' name='sub_class' value=''></td>";
echo "</tr>";

echo "</table>";

echo "<input type='hidden' name='subclass_search' value='TRUE'>";
echo "<br><input type='submit' name='search' value='Search'/>";
echo "</form>";


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
	echo "Is finfet:   "; 
		if ($is_finfet == 1) echo "Yes";
		else echo "No";
	echo "<br><br>";

// -------------------------------------------------------------------
//Generate Data

$rendersize = generate_data ($origin_ids, $generation, $date_start, $date_end, $is_finfet);

// -------------------------------------------------------------------
//Now to generate the JSON

generate_JSON();

// -------------------------------------------------------------------
//Print links to the rendered versions
echo "<br><br>";

	if ($rendersize == 'big')  {
		echo "<b><h2>Success! Please follow <a href='render_big.html' target='_blank'>this link</a> to your patent network map.</h2></b>";
		echo "<br><br>";
		echo "<b><h2>Or the industry/academy version: <a href='render_big_univ.html' target='_blank'>Link</a></h2></b>";
		
	}
	else {
		echo "<b><h2>Success! Please follow <a href='render.html' target='_blank'>this link</a> to your patent network map.</h2></b>";
		echo "<br><br>";
		echo "<b><h2>Or the industry/academy version: <a href='render_univ.html' target='_blank'>Link</a></h2></b>";
	}
	
	

} //End "IF Generate button section

// -------------------------------------------------------------------
// -------------------------------------------------------------------

?>
