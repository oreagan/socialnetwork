<?
//Functions for the social network diagrams that are useful for multiple spots

//This function takes an array of inventor ids and return all patent ids associated
function patents_by_inventors($array) {
	global $date_start, $date_end, $dbh_pat, $use_apps_table_for_dates;	
		
	//echo "USE APPS IS $use_apps_table_for_dates<BR><BR><BR>";
		
	$inventor_ids = $array;
	$inv_num = count($inventor_ids);
	
	if ($use_apps_table_for_dates == 1) {
		$sql  = "SELECT * FROM patent_inventor ";
		$sql .= "JOIN application ON patent_inventor.patent_id = application.patent_id ";
	}
	else {
		$sql  = "SELECT patent_inventor.patent_id, patent_inventor.inventor_id, patent.date FROM patent_inventor ";
		$sql .= "JOIN patent ON patent_inventor.patent_id = patent.id ";
	}
	
	$sql .= "WHERE (inventor_id = '$inventor_ids[0]' ";
	for ($i=1; $i<$inv_num; $i++) {
		$sql .= "OR inventor_id = '$inventor_ids[$i]' ";
	}
	
	if ($use_apps_table_for_dates == 1) {
		$sql .= ") AND application.date < '$date_end' AND application.date > '$date_start' ";
	} else { 
		$sql .= ") AND patent.date < '$date_end' AND patent.date > '$date_start' ";  }
	
	//echo "SQL is $sql<br><br>";
	
	$result = mysql_query($sql, $dbh_pat) or die(mysql_error());

	$patents_found = mysql_num_rows($result);
	
	echo "Patents found: $patents_found<br>";
	
	$patents = array();
	while($row = mysql_fetch_array($result)) {
		$patents[] = $row['patent_id'];
	}
	
	if ($is_finfet == 1) $patents[] = '6413802';

	$patents = make_unique($patents);
	
	return $patents;
}

//This function takes an array of patent ids and return the inventor ids associated with them
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

//Counts occurrances of needs in haystack
function substr_count_array( $haystack, $needle ) {
     $count = 0;
     foreach ($needle as $substring) {
          $count += substr_count( $haystack, $substring);
     }
     return $count;
}

//Some manual lumping to correct for splitting problems  in the data
function disambiguate_first($first) {
	$first = strtoupper($first); //Capitalize all
	$first = str_replace("-"," ",$first); //Remove hyphens
	$first = str_replace("'"," ",$first); //Remove apostrophes
	$first = rtrim($first, '.'); //Remove trailing periods
	
	if ($first == 'CHEN MING') {$first = 'CHENMING';}
	if ($first == 'HON SUM PHILIP') {$first = 'H. S. PHILIP';}
	if ($first == 'HON SUM PHILLIP') {$first = 'H. S. PHILIP';}
	if ($first == 'HON SUM P') {$first = 'H. S. PHILIP';}

	return $first;
}

//Some manual lumping to correct for splitting problems  in the data
function disambiguate_last($last) {
	$last = ucfirst(strtolower($last)); //Capitalize first letter, lower-case others
	$last = str_replace("'"," ",$last); //Remove apostrophes

	return $last;
}

//This re-indexes an array after removing duplicates
function make_unique($array) {
	$array = array_unique($array);
	$temp = array_values($array);
	$array = $temp;
	
	return $array;	
}

//Strip out all special characters
function clean_unicode($string) {
	$string = str_replace(array('u201c'), array(' '), $string);
	$string = str_replace("\u201c", ' ', $string);
	$string = str_replace("\u201D", ' ', $string);
	$string = str_replace("\u2018", ' ', $string);
	$string = str_replace("\u2019", ' ', $string);
	$string = str_replace("\u2032", ' ', $string);
	$string = str_replace("\u2033", ' ', $string);
	$string = str_replace("\u2013", ' ', $string);
	$string = str_replace("\u2014", ' ', $string);
	return $string;
}

function clean($string) {
	$string = str_replace(array( '(', ')' ), ' ', $string);
	$string = str_replace('"', ' ', $string);
	$string = str_replace("'", ' ', $string);
	$string = str_replace("/", ' ', $string);
	$string = str_replace('\\', ' ', $string);
	$string = str_replace('}', ' ', $string);
	$string = str_replace('{', ' ', $string);
	$string = str_replace('-', ' ', $string);
	$string = clean_unicode($string);
   	return preg_replace('/[^A-Za-z0-9\-]/', ' ', $string); // Removes special chars.
}



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



//Confirm_ids takes in a string of comma-separated inventor IDs, verifies they look okay, then returns them (or an error)
function confirm_ids($origins) {
	global $def_ids;
	
	//Remove spaces
	$origins=str_replace(" ","",$origins);

	//Make sure they're properly formatted (and not an insertion)
	$origins=str_replace("'","",$origins);
	$origins=str_replace("/t","",$origins);
	
	$origins = explode(",", $origins);
	$ids_count = count($ids);
	$good = 0;
	foreach ($origins as $id) {
		$split = explode("-", $id);
		//echo "SPLIT $split[0] - $split[1]<br>";
		if (strlen($split[0]) > 8) { $good = 1; }
		if (strlen($split[0]) < 5) { $good = 1; }
		if (strlen($split[1]) > 2) { $good = 1; }
	}
	
	if ($good == 0) {
		$origins = implode(",", $origins);
		$origin_ids = $origins;
	}
	else {
		$output_string = "Error: IDs don't seem to be in the right format. Using defaults<br><br>";
		//echo "Error: IDs don't seem to be in the right format. Using defaults<br><br>";
		$def_ids = implode(",", $def_ids);
		$origin_ids = $def_ids;
	}
	
	return $origin_ids;
}




?>