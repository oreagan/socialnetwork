
<?



function generate_data ($origins, $generations, $date_start, $date_end, $is_finfet, $verbose = '0') {

global $table, $dbh_pat, $dbh_local;

$is_finfet = 0;

//Process the dates, PHP being stupid
$start = explode("/", $date_start);
$Y = $start[0];
$M = $start[1];
$D = $start[2];  
$string = "$M/$D/$Y";
$start_date = strtotime($string);
$start_date = date('Y-m-d',$start_date); 

$end = explode("/", $date_end);
$Y = $end[0];
$M = $end[1];
$D = $end[2];  
$string = "$M/$D/$Y";
$end_date = strtotime($string);
$end_date = date('Y-m-d',$end_date); 

//This hacky solution to the applications table problem should still be somewhat smart
if ($is_finfet == 1) {
	$string = "10/23/2000"; //Date of finfet patent application
	$compare = strtotime($string);
	$compare = date('Y-m-d',$compare);
	
	if ($end_date < $compare) {
		echo "Finfet correction negated, date before $compare<br><br>";
		$is_finfet = 0;}	
	if ($start_date > $compare) {
		echo "Finfet correction negated, date after $compare<br><br>";
		$is_finfet = 0;}
}
//End hacky solution

$table = 'socialnetwork';

mysql_select_db("uspto", $dbh_pat) or die("Could not select uspto");
mysql_select_db("wbtmougo_dblood", $dbh_local) or die("Could not select local");

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Loop through these as many generations as indicated above

$inventors = $origins;
$orig_count = count($inventors);
echo "Number of starting inventors: $orig_count<br>";

$patents = patents_by_inventors($inventors);
$count = count($patents);
echo "Starting patents: $count<br><br>";
echo "...<br><br>";

//The final stage below is actually one more iteration, that's why only $i < $gen
$n = 0;
for ($i = 1; $i < $generations; $i++) {
	for ($j = 0; $j < $i; $j++) {
			echo "co-";
	}
	echo "inventors:<br>";

	$inventors = inventors_of_patents($patents);
	$count = count($inventors);
	$count = $count;
	echo "Number of inventors: $count<br>";

	$patents = patents_by_inventors($inventors);
	$count = count($patents);
	echo "Patents applied for by them in time frame: $count<br><br>";
	echo "...<br><br>";
$n++;
}

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Final grab, insert data into the local DB, then add future_cites

//Empty the local table that currently exists
$sql = "TRUNCATE $table";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());

//Gather info, applications.date version
if ($use_apps_table_for_dates == 'apps') {
	$sql  = "SELECT patent.date AS date_granted, application.date AS date_applied, patent.id AS patent_id, inventor.id AS inventor_id, 
								   inventor.name_last, inventor.name_first, assignee.organization FROM patent ";
	$sql .= "JOIN application ON patent.id = application.patent_id ";
} else {
	$sql  = "SELECT patent.date AS date_granted, patent.id AS patent_id, inventor.id AS inventor_id, inventor.name_last, inventor.name_first, 
								   assignee.organization FROM patent ";
}
$sql .= "JOIN patent_inventor ON patent.id = patent_inventor.patent_id ";
$sql .= "JOIN inventor ON inventor.id = patent_inventor.inventor_id ";
$sql .= "JOIN patent_assignee ON patent.id = patent_assignee.patent_id ";
$sql .= "JOIN assignee ON assignee.id = patent_assignee.assignee_id ";

$sql .= "WHERE (patent.id = '$patents[0]' ";
for ($i=1; $i<$count; $i++) {
	$sql .= "OR patent.id = '$patents[$i]' ";
}
$sql .= ") ";
$result = mysql_query($sql, $dbh_pat) or die(mysql_error());

$row_count = mysql_num_rows($result);

$table_array = array();
$index = 1;
while($row = mysql_fetch_array($result)) {
	$date_granted = $row['date_granted']; 
	
	if ($use_apps_table_for_dates == 'apps') { $date_applied = $row['date_applied']; }
	else { $date_applied = ''; }
		
	$patent_id = $row['patent_id'];
	$inventor_id = $row['inventor_id']; 
	$name_first  = clean($row['name_first']); 
	$name_last = clean($row['name_last']);
	$organization = clean($row['organization']);
	
	//Storing these for stats
	$final_patents[] = $patent_id;
	$final_names[] = "$name_first $name_last";
	$final_ids[] = $inventor_id;
	
	//Special disambiguation
	$name_first = disambiguate_first($name_first);
	$name_last = disambiguate_last($name_last);
	
	
	//Check if duplicate. If so, skip. If not, add to the $table_array
	$found = false;
	if ($table_array) {  //If the table_array exists (ie this isn't the first entry),
		foreach ($table_array as $key => $data) {
   			 if ($data['patent_id'] == $patent_id && 
		 	     $data['name_last'] == $name_last &&
			     $data['name_first'] == $name_first ) {  
				     $found = true;
					 $break;// This is a duplicate, skip it
				} //Ends "if it's a match"
   	 	} //Ends "foreach" loop
	} //Ends "if $table_array exists" check 


	//Not a duplicate, so:
	if ($found === false) { 
    
		//Special corrections for finfet example
   		if ($is_finfet == 1) { 
			if ($name_first == 'TSU JAE KING') {
				$name_first = 'TSU JAE';
				$name_last = 'King'; }
		}
	
		//Add to the table (in memory)
		$table_array[$index] = array("date_granted"=>$date_granted,
								 "date_applied"=>$date_applied,
								 "patent_id"=>$patent_id,
								 "inventor_id"=>$inventor_id,
								 "name_first"=>$name_first,
								 "name_last"=>$name_last,
								 "organization"=>$organization,
								 "future_cites"=>0,
								 "acad_if_one"=>0);	
	
	      $index++;
	 } //Ends "not a duplicate" area
	
}  //Ends processing for individual  patents

//echo "Patents entered into table in memory<br>";



//Future cites: For each unique patent, add them in
$fc_sql  = "SELECT citation_id, COUNT(*) AS f_cites FROM uspatentcitation ";
$fc_sql .= "WHERE (citation_id = '$patents[0]' ";
for ($i=1; $i<$count; $i++) {
	$fc_sql .= "OR citation_id = '$patents[$i]' ";
}
$fc_sql .= ") GROUP BY citation_id ";

//echo "FC_SQL is $fc_sql<Br><Br>";
	
$fc_result = mysql_query($fc_sql, $dbh_pat) or die(mysql_error());




while($fc_row = mysql_fetch_array($fc_result)) {
  	$patent_id = $fc_row['citation_id'];
	$cites = $fc_row['f_cites'];
		
	//echo "Patent is $patent_id with cites $cites<br>";
	
	for ($i=1; $i<=$index; $i++) {
		$test = $table_array[$i][patent_id];
		//echo "Patent is $patent_id, does it match $test?<br>";
		
		if ($test == $patent_id) {
			$table_array[$i][future_cites] = $cites;		
		}
	}

} //Ends while loop

//echo "Future cites entered into table successfully<br><br>";
//End future cites


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Special section to temporarily correct for missing data in applications table
$is_finfet = 1;
if ($is_finfet == 1) {

	//echo "Making correction for temporary FinFET data issue...<br><br>";
	$table_array = finfet_correction($table_array,$index);
	
	$final_ids[] = '4366555-1';
	$final_ids[] = '6413802-2';
	$final_ids[] = '6413802-3';
	$final_ids[] = '6413802-4';
	$final_ids[] = '6413802-5';
	$final_ids[] = '6413802-6';
	$final_ids[] = '6413802-7';
	$final_ids[] = '5783499-1';
	$final_ids[] = '6210988-1';
	$final_ids[] = '6034882-4';
		
}
// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Go back and figure out if the inventors are university-affiliated
//   Def.: If inventor has patented something assigned to a univ. (w/in time frame), is 'univ.-affiliated'
//            If assignee includes $university_words, is a university.
//   Plan: Find all patents (w/in time frame) by inventors, search for words in assignee, tag or not.

$university_words = array("UNIVERSITY","REGENT","COLLEGE","SCHOOL");

//Need an array of all inventors. Happily, we have one already
$inv_ids = make_unique($final_ids);
$total_invs = count($inv_ids);

//Grab all affiliations for all inventors (minimizing SQL requests)
$sql_inv  = "SELECT * FROM patent_inventor ";
$sql_inv .= "JOIN patent_assignee ON patent_inventor.patent_id = patent_assignee.patent_id ";
$sql_inv .= "JOIN assignee ON assignee.id = patent_assignee.assignee_id ";
$sql_inv .= "WHERE patent_inventor.inventor_id = '$inv_ids[0]' ";
for ($i=1; $i<$total_invs; $i++) {
	$sql_inv .= "OR patent_inventor.inventor_id = '$inv_ids[$i]' ";
}
$sql_inv .= "ORDER BY inventor_id";

$result_inv = mysql_query($sql_inv, $dbh_pat) or die(mysql_error());

//Now find the individual inventors' affiliations
$i = 0;
$assignees = array();
while($row_inv = mysql_fetch_array($result_inv)) {
	//print_r($row_inv);
	
	$inv_id = $row_inv['inventor_id'];
	$all_ids[$i] = $inv_id;
	
	//Special thing for the first one
	if ($i == 0) { echo "Inventor ID: $inv_id<br>";}
	
	//If it's the same inventor as previously, add the asignee to an array
	if (($all_ids[$i] == $all_ids[$i-1]) || ($i == 0)) {	
		$assignees[] = $row_inv['organization'];
	}
	
	//If it's a new inventor,
	if (!($all_ids[$i] == $all_ids[$i-1]) && !($i == 0)) {
		
		//---------------------------
		//It's a new inventor, so print the unique assignees
		$old_inv = $all_ids[$i-1];
		$assignees = array_unique($assignees);
	
		$count = 0;
		foreach ($assignees as $assignee) {
			$assignee = strtoupper($assignee);
			
			//Check if assignee contains university words
			$affiliated = substr_count_array($assignee, $university_words);
			$count = $count + $affiliated;
			//echo "Assignee:   $assignee    $affiliated<br>";	
		}
		//Count now contains a value if the inventor is university affiliated, 0 if not
		if ($count >= 1) {
			$univ_affiliated_ids[] = $old_inv;
		}
			
		//---------------------------
		//Now, start off the next inventor
		//echo "<br>Inventor ID: $inv_id<br>";
		
		//Reset the holder array
		$assignees = array();
		$assignees[] = $row_inv['organization'];
	}
		
	//echo "Assignee = $assignee<br>";
	$i++;
}

$univ_count = count($univ_affiliated_ids);
$priv_count = $total_invs - $univ_count;

echo "Final university   affailiated: $univ_count<br>";
echo "Final private ind. affailiated: $priv_count<br><br>";
// --------------------------
//Now update the table to have a 1 in acad_if_one for those univ.-affiliated
// for all in $univ_affiliated_ids (array)

//echo "Univ affiliated ids:"; print_r($univ_affiliated_ids);

for ($i=1; $i<=$index; $i++) {
		$inventor = $table_array[$i][inventor_id];
//		echo "Inventor is $inventor, does it match $test?<br>";
		
		if (in_array($inventor,$univ_affiliated_ids)) {
			$table_array[$i][acad_if_one] = 1;		
		}
	}


foreach ($table_array as $key => $data) {
   	if (in_array($data['inventor_id'],$univ_affiliated_ids)) { 
	   $data['acad_if_one'] = 1;
	} //Ends "if it's a match"
  } //Ends "foreach" loop

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Print stats

$final_names = array_unique($final_names);
$count_inv = count($final_names);

for ($k = 0; $k < ($n+1); $k++) {
		echo "co-";
}
echo "inventors:<br>";

echo "Number of inventors: $count_inv<br>";

echo "-------------------------------------------------------------------<br><br>";

echo "Finished gathering data, moving on to formatting...<br><br>";

//echo "<BR><BR><BR>NOW for the final table";
//print_r($table_array);

//Now to return a value to decide how big the rendered image will be
return $table_array;


}
?>


