<?
/*
Notes:

This function takes the table (socialnetwork) and generates properly-rendered JSON for the visualizations.

Basically it adds everything step-by-step to a single string ($basic_render_string) and then write it to the file at the end.

3/6/15: Adding another string ($univ_acad_string) to make a different JSON file that color things according to those designations

*/


function generate_JSON($verbose = '0') {

global $dbh_local, $table;

echo "Processing underway...<br><br>";

$basic_render_string = "";
$univ_acad_string = "";

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//         START Links Section

$links = array();
//Going to cycle through all patents. First, array of all patents

$sql = "SELECT DISTINCT patent_id FROM $table";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());

$unique_patent_ids = array();
while($row = mysql_fetch_array($result)) {
  	$curr_pat = $row['patent_id'];
	$unique_patent_ids[] = $curr_pat;
}

//Now, starting the cycle through the unique patents
$patents_count = count($unique_patent_ids);
$links = array();
for ($x=0; $x<$patents_count; $x++) {
	$patent_id = $unique_patent_ids[$x];

	//Find inventors for this individual patent
	$this_sql = "SELECT * FROM $table WHERE patent_id = '$patent_id'";
	$this_result = mysql_query($this_sql) or die(mysql_error());

	$curr_patent_inventors = array();
	while($this_row = mysql_fetch_array($this_result)) { //Start fetching this patent's inventors
  		$name_last = $this_row['name_last'];
		$name_first = $this_row['name_first'];
		$name = "$name_last; $name_first";
	
		//Add this inventor to the current patent's temporary array of all its inventors
		$curr_patent_inventors[] = $name;
	} //End fetching this patent's inventors (while loop)

	//Now iterate across these inventors for the links section
	$ids = $curr_patent_inventors;
	//$combinations = array();
	$num_ids = count($ids);

	for ($i = 0; $i < $num_ids; $i++)  {
	  for ($j = $i+1; $j < $num_ids; $j++) {
    		$one = $ids[$i];
			$two = $ids[$j];
							
			$str = "\n\t{source:\"$one\", target:\"$two\",t1:4,t2:4,flag:0},";
			$links[] = $str;
	  }
	}
} //Ends loops for this unique patent

//We don't need repeats, so
$links = array_unique($links);
$link2 = array_values($links);
$links = $link2;

$count = count($links); //Remove comma after the last one
$links[$count-1] = rtrim($links[$count-1], ',');




//Print this section to the string for the JSON file
$basic_render_string .= "var links=[\n";
if ($verbose == 1) echo "var links=[<br>";

$links_size = count($links);
for ($i=0; $i<$links_size; $i++) {
	if (isset($links[$i])) {
		$basic_render_string .= "$links[$i]";
		if ($verbose == 1) echo "$links[$i]<br>";
	}
	
}
$basic_render_string .= "];\n\n";
if ($verbose == 1) echo "];<br><br>";

//Strings are still the same at this point
$univ_acad_string = $basic_render_string;

//     END of links section


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//     START of assignee_count
$assignee_count = array();

$sql = "SELECT organization, COUNT(organization) AS occurrences FROM $table  GROUP BY organization  ORDER BY occurrences DESC";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());					   
$n = 1;
$top_10 = array();
while($row = mysql_fetch_array($result)) {
	$assignee = $row['organization'];
	$occurrences = $row['occurrences'];
	$occurrences = intval($occurrences); //Change this into an integer rather than a string
	
	$top_10[$n-1] = "$assignee"; //Setting up the next section
	$n++;
	
	$assignee_count[$assignee] = $occurrences;
	//echo "\"$assignee\":$occurrences<br>";	
}

$basic_render_string .= "bubble_assignee_count=\n";
$basic_render_string .= json_encode($assignee_count);
$basic_render_string .= ";\n\n";

if ($verbose == 1) {
echo "<br>";
echo "bubble_assignee_count=<br>";
echo json_encode($assignee_count);
echo "<br>";
}
// --------------------------------
//How many industry, how many academia-related
$univ_count = array();

$sql = "SELECT DISTINCT inventor_id FROM $table";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());					   
$num_inventors = mysql_num_rows($result);

$sql = "SELECT DISTINCT inventor_id FROM $table WHERE acad_if_one='1'";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());					   
$num_acad_inv = mysql_num_rows($result);

$num_priv_inv = $num_inventors - $num_acad_inv;


$assignee_count_new['Industry'] = $num_priv_inv;
$assignee_count_new['Academia'] = $num_acad_inv;

$univ_acad_string .= "bubble_assignee_count=\n";
$univ_acad_string .= json_encode($assignee_count_new);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_assignee_count=<br>";
echo json_encode($assignee_count);
echo "<br>";
}


//     END of assignee_count
// -------------------------------------------------------------------
// -------------------------------------------------------------------
//     START of assignee_index

$assignee_index = array();
for ($x=0; $x<10; $x++) {
	$y = $x+1;
	
	$holder = $top_10[$x];
	$assignee_index[$holder] = $y;
   //echo "\"$top_10[$x]\":$y,<br>";
}

$basic_render_string .= "bubble_assignee_index=\n";
$basic_render_string .= json_encode($assignee_index);
$basic_render_string .= ";\n\n";

// --------------------------------
//Acad-industry version

$assignee_index_new['Industry'] = 1;
$assignee_index_new['Academia'] = 2;


$univ_acad_string .= "bubble_assignee_index=\n";
$univ_acad_string .= json_encode($assignee_index_new);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_assignee_index=<br>";
echo json_encode($assignee_index);
echo "<br>";
}
//     END of assignee_index
// -------------------------------------------------------------------
// -------------------------------------------------------------------

//     START inventor_assignee
//echo "bubble_inventor_assignee=";
$inventor_assignee = array();

//Need to cycle through unique names
$sql = "SELECT DISTINCT name_first,name_last FROM $table ORDER BY name_last ASC, name_first ASC";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());	

$multi_inventors = array();
while($row = mysql_fetch_array($result)) {
	$name_last = $row['name_last'];
	$name_first = $row['name_first'];
	$name = "$name_last; $name_first";
	
	//First, going to check if there are multiple assignees associated with this name
	$sql2 = "SELECT organization, acad_if_one FROM $table"
				." WHERE name_first='$name_first' AND name_last='$name_last' "
				." ORDER BY date_applied ASC";
	$result2 = mysql_query($sql2, $dbh_local) or die(mysql_error());
	
	$orgs = array();
	while ($row2 =  mysql_fetch_array($result2)) {
		$assignee = $row2['organization'];
		$acad_if_one = $row2['acad_if_one'];
		$orgs[] = $assignee;
	}
	$orgs = array_unique($orgs);
	$num_orgs = count($orgs);

	// Creates array of multiple assignees
	if ($num_orgs > 1) { $multi_inventors[] = $name;} 
	
	//Regardless, match the most recent assignee to the Top-10 assignee list
	$found = array_search($assignee, $top_10);
	if ($found !== false) { $code = $found + 1; } 
	   else { $code = 1000; }
	if ($code > 10) $code = 1000;
	
	$inventor_assignee[$name] = $code;
	//echo "\"$name_last; $name_first\":$code,<br>";
	
	if ($acad_if_one == 1) { $inv_acad[$name] = 2; }
	elseif ($acad_if_one == 0) { $inv_acad[$name] = 1; }
	
		unset($orgs);//Reset this array for the while loop						   		
} //Ends while loop - on to the next unique inventor

//$multiples = count($multi_inventors);
//echo "MULTIPLES count $multiples";

$basic_render_string .= "bubble_inventor_assignee=\n";
$basic_render_string .= json_encode($inventor_assignee);
$basic_render_string .= ";\n\n";
// --------------------------------
$univ_acad_string .= "bubble_inventor_assignee=\n";
$univ_acad_string .= json_encode($inv_acad);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_inventor_assignee=<br>";
echo json_encode($inventor_assignee);
echo "<br>";
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
// START new section: Inventors with multiple assignees associated to them

$basic_render_string .= "bubble_inventors_with_multiple_assignees=\n";
$basic_render_string .= json_encode($multi_inventors);
$basic_render_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_inventors_with_multiple_assignees=<br>";
echo json_encode($multi_inventors);
echo "<br>";
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//     START inventor_citation
//echo "bubble_inventor_citation=";
$inventor_citation = array();

$sql = "SELECT name_last, name_first, SUM(future_cites) as personal_cites FROM $table"
				." GROUP BY name_last, name_first";
	$result = mysql_query($sql, $dbh_local) or die(mysql_error());
	
	while($row = mysql_fetch_array($result)) {	
		$name_last = $row['name_last'];
	    $name_first = $row['name_first'];
		$name = "$name_last; $name_first";
		
		$cites = $row['personal_cites'];
		$cites = intval($cites);
		
		$inventor_citation[$name] = $cites;
		//echo "\"$name_last; $name_first\":$cites,<br>";
	}

$basic_render_string .= "bubble_inventor_citation=\n";
$basic_render_string .= json_encode($inventor_citation);
$basic_render_string .= ";\n\n";
// --------------------------------
$univ_acad_string .= "bubble_inventor_citation=\n";
$univ_acad_string .= json_encode($inventor_citation);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_inventor_citation=<br>";
echo json_encode($inventor_citation);
echo "<br>";
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Write the basic rendering JSON 

$json_file = 'src.json';
$fp = fopen($json_file, 'w');

$input = $basic_render_string;

file_put_contents($json_file, $input);

fclose($fp);


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Write the univeristy-industry rendering JSON 
$json_file = 'src_univ.json';
$fp = fopen($json_file, 'w');

$input = $univ_acad_string;

file_put_contents($json_file, $input);

fclose($fp);

}

?>
















