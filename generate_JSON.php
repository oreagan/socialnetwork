<?
/*
Notes:

This function takes the table (socialnetwork) and generates properly-rendered JSON for the visualizations.

Basically it adds everything step-by-step to a single string ($basic_render_string) and then write it to the file at the end.

3/6/15: Adding another string ($univ_acad_string) to make a different JSON file that color things according to those designations

*/


function generate_JSON($table_array) {

global $dbh_local;

$verbose = 0;

echo "Processing underway...<br><br>";

$basic_render_string = "";
$univ_acad_string = "";

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//         START Links Section

$links = array();
//Going to cycle through all patents. First, array of all patents

$all_patents = array();
foreach ($table_array as $key => $data) {
   	$all_patents[] = $data['patent_id'];
 } //Ends "foreach" loop

$unique_patent_ids = make_unique($all_patents);
$patents_count = count($unique_patent_ids);
//Now we have an array of all unique patents, and a count

//Now, starting the cycle through the unique patents
$patents_count = count($unique_patent_ids);
$links = array();
for ($x=0; $x<$patents_count; $x++) {
	$patent_id = $unique_patent_ids[$x];

	//Find inventors for this individual patent, add to temporary array
	$curr_patent_inventors = array();
	
	foreach ($table_array as $key => $data) {
   		if ($data['patent_id'] == $patent_id) {
				$name_last = $data['name_last'];
				$name_first = $data['name_first'];
				$name = "$name_last; $name_first";
		    	
				$curr_patent_inventors[] = $name;
		}
 	} //Ends "foreach" loop
	
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
	unset($curr_patent_inventors);
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


$all_organizations = array();
foreach ($table_array as $key => $data) {
   	$all_organizations[] = $data['organization'];
 } //Ends "foreach" loop

$assignee_count = array_count_values($all_organizations);

arsort($assignee_count);
//Now we have an array with the organizations as the key, and the number of occurances as value, descending

//Putting top 10 assignees into their own array for later usage
$keys = array_keys($assignee_count);
for ($x = 0; $x<10; $x++) {
	$top_10[$x] = $keys[$x];
}
//print_r($top_10);

$assignee_count = clean($assignee_count);

//var_dump($assignee_count);

$basic_render_string .= "bubble_assignee_count=\n";
$basic_render_string .= json_encode($assignee_count, JSON_NUMERIC_CHECK);
$basic_render_string .= ";\n\n";

if ($verbose == 1) {
echo "<br>";
echo "bubble_assignee_count=<br>";
echo json_encode($assignee_count, JSON_NUMERIC_CHECK);
echo "<br>";
}
// --------------------------------
//How many industry, how many academia-related
$univ_count = array();


$all_inventors = array();
$acad_inventors = array();
foreach ($table_array as $key => $data) {
   	    $all_inventors[] = $data['inventor_id'];
	if ($data['acad_if_one'] == 1) { 
	    $acad_inventors[] = $data['inventor_id']; 
		}
	
 } //Ends "foreach" loop

//Count unique inventors
$unique_inventors_ids = make_unique($all_inventors);
$num_inventors = count($unique_inventors_ids);

//Count academic inventors
$unique_acad = make_unique($acad_inventors);
$num_acad_inv = count($unique_acad);

$num_priv_inv = $num_inventors - $num_acad_inv;


$assignee_count_new['Industry'] = $num_priv_inv;
$assignee_count_new['Academia'] = $num_acad_inv;

$assignee_count_new = clean($assignee_count_new);

$univ_acad_string .= "bubble_assignee_count=\n";
$univ_acad_string .= json_encode($assignee_count_new, JSON_NUMERIC_CHECK);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_assignee_count=<br>";
echo json_encode($assignee_count, JSON_NUMERIC_CHECK);
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

$assignee_index = clean($assignee_index);

$basic_render_string .= "bubble_assignee_index=\n";
$basic_render_string .= json_encode($assignee_index, JSON_NUMERIC_CHECK);
$basic_render_string .= ";\n\n";

// --------------------------------
//Acad-industry version

$assignee_index_new['Industry'] = 1;
$assignee_index_new['Academia'] = 2;

$assignee_index_new = clean($assignee_index_new);

$univ_acad_string .= "bubble_assignee_index=\n";
$univ_acad_string .= json_encode($assignee_index_new, JSON_NUMERIC_CHECK);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_assignee_index=<br>";
echo json_encode($assignee_index, JSON_NUMERIC_CHECK);
echo "<br>";
}
//     END of assignee_index
// -------------------------------------------------------------------
// -------------------------------------------------------------------

//     START inventor_assignee
//echo "bubble_inventor_assignee=";

//Need to cycle through unique names
$inventor_names = array();
foreach ($table_array as $key => $data) {
   	    $first = $data['name_first'];
		$last = $data['name_last'];
		$name = "$last; $first";
		$inventor_names[] = $name;	
} //Ends "foreach" loop
$inventor_names = make_unique($inventor_names);
sort($inventor_names);

//print_r($inventor_names);

//Note: Unique inventor IDs are still stored in $unique_inventors_ids

//Need to find the most recent affiliation for each inventor
$multi_inventors = array();


//print_r($table_array);

$inventor_assignee = array();
foreach ($inventor_names as $inventor_name) {
	$affiliations = 0;
	$temp_date = '1800-01-01';
	$assignee = 'None';
	$acad_if_one = 0;
	
	//echo "Inventor going is $inventor_name<br>";

	foreach ($table_array as $key => $data) {
   		$first = $data['name_first'];
		$last = $data['name_last'];
		$name = "$last; $first";

		//First, we need to see if we're going by date_applied or date_granted. Only one should exist
		$curr_date = $data['date_granted'];
		$curr_date_app = $data['date_applied'];
		if ($curr_date_app > '1900-01-01') {$curr_date = $curr_date_app;}
		//echo "Current date is $curr_date<br>";

		//Now cycle through patents and figure out which match this inventor	
		if ($name == $inventor_name) {
			
			if ($data['acad_if_one'] == 1) { $acad_if_one = 1; }
			
			//We're found a patent with the correct inventor. Now see if it's the same org
			$org = $data['organization'];
			if ($org == $assignee) {  //Same assignee, so just update the date
				
				
				//echo "date $temp_date less than $curr_date<br>";
								
				if ($temp_date < $curr_date) {
					$temp_date = $curr_date;
					}
				
				 }			
			else {  //A different assignee. See if it's more recent
				if ($assignee == 'None') {
					$assignee = $org;
					$temp_date = $curr_date;
				}
				
				$affiliations++;
				//echo "date $temp_date less than $curr_date";
										
				if ($temp_date < $curr_date) {
					$assignee = $org;
					$temp_date = $curr_date;
					}			
			}
				
		}
		
	} //Ends "foreach" loop
	
	//echo "Current idea of assignee $inventor_name is assignee $assignee on date $temp_date <br>";
	
		// If an inventor with multiple assignees, add to that array
	if ($affiliations > 1) { $multi_inventors[] = $inventor_name; }
	
	//Regardless, match the most recent assignee to the Top-10 assignee list
	$found = array_search($assignee, $top_10);
	if ($found !== false) { $code = $found + 1; } 
	   else { $code = 1000; }
	if ($code > 10) $code = 1000;
	
	$inventor_assignee[$inventor_name] = $code;
	
	//For acad/industry view
	if ($acad_if_one == 1) { $inv_acad[$inventor_name] = 2; }
	elseif ($acad_if_one == 0) { $inv_acad[$inventor_name] = 1; }

	//Done with this inventor, on to the next one	
	
} //Ends foreach loop

//print_r($inventor_assignee);


//$multiples = count($multi_inventors);
//echo "MULTIPLES count $multiples";

$inventor_assignee = clean($inventor_assignee);
$inv_acad = clean($inv_acad);

$basic_render_string .= "bubble_inventor_assignee=\n";
$basic_render_string .= json_encode($inventor_assignee, JSON_NUMERIC_CHECK);
$basic_render_string .= ";\n\n";
// --------------------------------
$univ_acad_string .= "bubble_inventor_assignee=\n";
$univ_acad_string .= json_encode($inv_acad, JSON_NUMERIC_CHECK);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_inventor_assignee=<br>";
echo json_encode($inventor_assignee, JSON_NUMERIC_CHECK);
echo "<br>";
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
// START new section: Inventors with multiple assignees associated to them

$multi_inventors = clean($multi_inventors);

$basic_render_string .= "bubble_inventors_with_multiple_assignees=\n";
$basic_render_string .= json_encode($multi_inventors, JSON_NUMERIC_CHECK);
$basic_render_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_inventors_with_multiple_assignees=<br>";
echo json_encode($multi_inventors, JSON_NUMERIC_CHECK);
echo "<br>";
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//     START inventor_citation
//echo "bubble_inventor_citation=";
$inventor_citation = array();   //Array with inventor name as index, future cites as value

//Cycle through inventors, find their future citations
foreach ($inventor_names as $inventor_name) {
	$cites = 0;

	foreach ($table_array as $key => $data) {
		$first = $data['name_first'];
		$last = $data['name_last'];
		$name = "$last; $first";
		
		if ($name == $inventor_name) {						
			$cites = $cites + $data['future_cites'];
		}
		
	} //Ends "foreach" loop
	
	$inventor_citation[$inventor_name] = $cites;
	//Done with this inventor, on to the next one	
	
} //Ends foreach loop

$inventor_citation = clean($inventor_citation);

$basic_render_string .= "bubble_inventor_citation=\n";
$basic_render_string .= json_encode($inventor_citation, JSON_NUMERIC_CHECK);
$basic_render_string .= ";\n\n";
// --------------------------------
$univ_acad_string .= "bubble_inventor_citation=\n";
$univ_acad_string .= json_encode($inventor_citation, JSON_NUMERIC_CHECK);
$univ_acad_string .= ";\n\n";

// --------------------------------
if ($verbose == 1) {
echo "<br>";
echo "bubble_inventor_citation=<br>";
echo json_encode($inventor_citation, JSON_NUMERIC_CHECK);
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


$count_inv = count($inventor_names);
return $count_inv;

?>
















