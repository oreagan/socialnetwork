<?

function random_inventors ($class, $year, $how_many_per_month=10) {

global $dbh_pat, $output_string;

/*
//Manual Settings

//Patent class:
$class = '164';

//Years:
$year = 1980;

//How many to sample per month
$how_many_per_month = 10;
*/

date_default_timezone_set('America/Los_Angeles');

mysql_select_db("uspto", $dbh_pat) or die("Could not select uspto");

//---------------------------------------------
//---------------------------------------------
$total_patents = 0;

$date = "$year-01-01";
$end_date = date('Y-m-d', strtotime("+1 years", strtotime($date)));

$current_date = $date;
while ($current_date < $end_date) {
	//echo "<br><BR>Date is $current_date<br>";
	$next_date = date('Y-m-d', strtotime("+1 months", strtotime($current_date)));
	
	$sql = "
SELECT patent.id FROM patent 
JOIN uspc ON patent.id = uspc.patent_id
WHERE patent.date > '$current_date' AND patent.date < '$next_date'
AND uspc.mainclass_id = '$class'
	";
	
	//echo "<br>SQL is $sql<br>";

	$result = mysql_query($sql, $dbh_pat) or die(mysql_error());

	$patents = array();
	while($row = mysql_fetch_array($result)) {
		$patents[] = $row['id'];
	}
	$distinct_patents = array_values(array_unique($patents));
	$num_patents = count($distinct_patents);

	//echo "Number of distinct patents in month of $current_date is $num_patents<br>";
	$output_string .= "Number of distinct patents in month of $current_date is $num_patents<br>";
		
	for ($i = 0; $i < $how_many_per_month; $i++) {
		$chosen = $distinct_patents[$i];
		//echo "Chosen is $chosen<br>";
		if (isset($chosen)) $random_patents[] = $chosen;
	}

	$total_patents += $num_patents;

	$current_date = $next_date;	
}

//echo "Final tally is $total_patents<br><Br>";
$output_string .= "Final tally is $total_patents<br><Br>";

//echo "Gathering inventors for these randomly-chosen patents...<br><BR>";
$output_string .= "Gathering inventors for these randomly-chosen patents...<br><BR>";


//foreach ($random_patents as $patent) {
//echo "'$patent',";	
//}
function make_unique($array) {
	$array = array_unique($array);
	$temp = array_values($array);
	$array = $temp;
	
	return $array;	
}

function inventors_of_patents($array) {
	global $date_start, $date_end, $dbh_pat;
	
	$patents = $array;
	$pat_count = count($patents);
	//echo "Number of patents passed: $pat_count<br>";echo print_r($patents);
	
	$sql  = "SELECT * FROM patent_inventor ";
	$sql .= "WHERE (patent_inventor.patent_id = '$patents[0]' ";
	for ($i=1; $i<$pat_count; $i++) {
		$sql .= "OR patent_inventor.patent_id = '$patents[$i]' ";
	}
	$sql .= ") ";

	$result = mysql_query($sql, $dbh_pat) or die(mysql_error());
	
	$pat_found = mysql_num_rows($result);
	//echo "SQL $sql<br>Patents found: $pat_found<br><br>";

	$inventors = array();
	while($row = mysql_fetch_array($result)) {
  		$inventors[] = $row['inventor_id'];
	}

	$inventors = make_unique($inventors);

	return $inventors;	
}

$inventors = inventors_of_patents($random_patents);
$inventors_string = "";

foreach ($inventors as $inventor) {
//echo "$inventor,";	
	$inventors_string .= "$inventor,";
}

$inventors_string = substr($inventors_string, 0, -1);

$output_string .= "Inventors found!";

return $inventors_string;

}

?>
