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
//Defaults
include 'defaults.php';

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


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Printed on page
echo "<div class='CSSTableGenerator' >";
echo "<table>";
echo "<tr><td>Output</td></tr>";
echo "<tr><td><b><font color='blue'>"; echo $output_string; 

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//If the Generate button has been pressed, generate the social network.
if(isset($_POST['generate'])) {
	
	$origins = $_POST['origins'];

	//Confirm they're formatted right
	$origin_ids = confirm_ids($origins);
	$origin_ids = explode(",", $origin_ids);	


	//Is FinFET? Checking if any of the origin IDs are FinFET inventors
	$ids = $origin_ids;
	$holder_array = array_intersect($ids,$UCB_finfet_ids);
	if(!empty($holder_array) ){	$is_finfet = 1; }
	else{ $is_finfet = 0; }



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

//Generate the data, returning the number of final inventors
$table_array = generate_data ($origin_ids, $generation, $date_start, $date_end, $is_finfet);
$json = 'src'; 
$json_univ = 'src_univ';

//Generate the JSON
$count_inv = generate_JSON($table_array);

//I've found through approximations that the follow scaling works for the rendering
$scale = 0.0025 * $count_inv * $count_inv + 3.38 * $count_inv + 1753;
if ($scale > 8000){ $scale = 8000;}

// -------------------------------------------------------------------
//Print links to the rendered versions

echo "<br><br>";

echo "<b><h2>Success! Please follow <a href='render.php?screen_width=$scale&screen_height=$scale&charge=7000&json=$json' target='_blank'>this link</a> to your patent network map.</h1></b>";

echo "<br><br>";

echo "<b><h2>Or the industry/academy version: <a href='render.php?screen_width=$scale&screen_height=$scale&charge=7000&json=$json_univ' target='_blank'>this link</a></h1></b>";


} //End "IF Generate button pressed" section
else echo "Error: No data passed to the page. Please go back and try again!<BR><BR>Link: <a href='index.php'>index.php</a>";
// -------------------------------------------------------------------
// -------------------------------------------------------------------

echo "</b></font></td></tr>";
echo "</table>";
echo "</div>";

echo "</body>";


// -------------------------------------------------------------------
// -------------------------------------------------------------------

?>
