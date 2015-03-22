
<?

function generate_data ($origins, $generations, $date_start, $date_end, $is_finfet, $verbose = '0') {

global $table, $dbh_pat, $dbh_local;

//Process the dates
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

//FinFET correction
if ($is_finfet == 1) {
	$string = "10/23/2000"; //Date of finfet patent application
	$compare = strtotime($string);
	$compare = date('Y-m-d',$compare);
	
	if ($end_date < $compare) {
		echo "Finfet correction negated, date before $compare<br><br>";
		$is_finfet = 0;}	
	if ($start_date > $compare) {
		echo "Finfet correction negated, date before $compare<br><br>";
		$is_finfet = 0;}
}
//End FinFET correction

$table = 'socialnetwork';

mysql_select_db(/*REMOVED*/, $dbh_pat) or die("Could not select uspto");
mysql_select_db(/*REMOVED*/, $dbh_local) or die("Could not select local");

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Functions

function substr_count_array( $haystack, $needle ) {
     $count = 0;
     foreach ($needle as $substring) {
          $count += substr_count( $haystack, $substring);
     }
     return $count;
}

function disambiguate_first($first) {
	$first = strtoupper($first); //Capitalize all
	$first = str_replace("-"," ",$first); //Remove hyphens
	$first = str_replace("'"," ",$first); //Remove apostrophes
	$first = rtrim($first, '.'); //Remove trailing periods
	
	if ($first == 'CHEN MING') {$first = 'CHENMING';}
	if ($first == 'HON SUM PHILIP') {$first = 'H. S. PHILIP';}

	return $first;
}

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

// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Functions that are the main body

//This function takes an array of inventor ids and return all patent ids associated
function patents_by_inventors($array) {
	global $date_start, $date_end, $dbh_pat;	
	
	$inventor_ids = $array;
	$inv_num = count($inventor_ids);
	
	$sql  = "SELECT * FROM patent_inventor ";
	$sql .= "JOIN application ON patent_inventor.patent_id = application.patent_id ";
	$sql .= "WHERE (inventor_id = '$inventor_ids[0]' ";
	for ($i=1; $i<$inv_num; $i++) {
		$sql .= "OR inventor_id = '$inventor_ids[$i]' ";
	}
	$sql .= ") AND application.date < '$date_end' AND application.date > '$date_start'";
	$result = mysql_query($sql, $dbh_pat) or die(mysql_error());

	$patents_found = mysql_num_rows($result);
	//echo "SQL $sql<br>Patents found: $patents_found<br>";
	
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

//Gather info
$sql  = "SELECT patent.date AS date_granted, application.date AS date_applied, patent.id AS patent_id, inventor.id AS inventor_id, inventor.name_last, inventor.name_first, assignee.organization FROM patent ";
$sql .= "JOIN application ON patent.id = application.patent_id ";
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

$index = 1;
while($row = mysql_fetch_array($result)) {
	$date_granted = $row['date_granted']; 
	$date_applied = $row['date_applied'];
	$patent_id = $row['patent_id'];
	$inventor_id = $row['inventor_id']; 
	$name_first  = $row['name_first']; 
	$name_last = $row['name_last'];
	$organization = $row['organization'];
	
	//Storing these for stats
	$final_patents[] = $patent_id;
	$final_names[] = "$name_first $name_last";
	$final_ids[] = $inventor_id;
	
	//Special disambiguation
	$name_first = disambiguate_first($name_first);
	$name_last = disambiguate_last($name_last);
	
	$fields = "index, date_granted, date_applied, patent_id, inventor_id, name_first, name_last, organization, future_cites, acad_if_one";
	
	//Check for duplicates
	$dupesql = "SELECT * FROM $table where (patent_id = '$patent_id' AND name_last = '$name_last' AND name_first = '$name_first')";
	
	//echo "Name: $name_last $name_first<br>"; 
	$duperaw = mysql_query($dupesql, $dbh_local);
	if (!(mysql_num_rows($duperaw) > 0)) { 
	//Not a duplicate, so
		
		//Special corrections for finfet example
   		if ($is_finfet == 1) { 
			if ($name_first == 'TSU JAE KING') {
				$name_first = 'TSU JAE';
				$name_last = 'King'; }
		}
	
		$insert_q = "INSERT INTO $table VALUES('$index','$date_granted','$date_applied','$patent_id','$inventor_id','$name_first','$name_last','$organization','1','0')";
		
		//echo "$insert_q<br>";
		$insert = mysql_query($insert_q, $dbh_local) or die(mysql_error());
	
		$index++;
	}
}
//echo "Patents entered into local DB successfully<br>";


//Future cites: For each unique patent, add them in
$fc_sql  = "SELECT citation_id, COUNT(*) AS f_cites FROM uspatentcitation ";
$fc_sql .= "WHERE (citation_id = '$patents[0]' ";
for ($i=1; $i<$count; $i++) {
	$fc_sql .= "OR citation_id = '$patents[$i]' ";
}
$fc_sql .= ") GROUP BY citation_id ";
	
$fc_result = mysql_query($fc_sql, $dbh_pat) or die(mysql_error());

$update_q = "";
while($fc_row = mysql_fetch_array($fc_result)) {
  	$patent = $fc_row['citation_id'];
	$cites = $fc_row['f_cites'];
		
	$update_q = "UPDATE $table SET future_cites = $cites WHERE patent_id = '$patent'";
}
$update = mysql_query($update_q, $dbh_local) or die(mysql_error());

//echo "Future cites entered into local DB successfully<br><br>";
//End future cites


// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Special section to temporarily correct for missing data in applications table
if ($is_finfet == 1) {

echo "Making correction for FinFET issues...<br><br>";

$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','4366555-1','CHENMING','Hu','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;

$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','4366555-1','CHENMING','Hu','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;

$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6413802-2','JEFFREY','Bokor','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;

$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6413802-3','WEN CHIN','Lee','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;

$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6413802-4','NICK','Lindert','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;
							
$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6413802-5','JAKUB TADEUSZ','Kedzierski','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;
							
$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6413802-6','LELAND','Chang','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;
							
$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6413802-7','XUEJUE','Huang','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;
							
$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','5783499-1','YANG KYU','Choi','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;
							
$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6210988-1','TSU JAE','King','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());
$index++;
							
$insert_q = "INSERT INTO $table VALUES($index, '2002-07-02','2000-10-23','6413802','6034882-4','VIVEK','Subramanian','The Regents of the University of California',554,1)";
$update = mysql_query($insert_q, $dbh_local) or die(mysql_error());

}
// -------------------------------------------------------------------
// -------------------------------------------------------------------
//Go back and figure out if the inventors are university-affiliated

$university_words = array("UNIVERSITY","REGENT","COLLEGE","SCHOOL");

//First, get the inventor IDs to be tested
//(Could just use data from above once FinFET correction no longer needed)
$sql  = "SELECT DISTINCT inventor_id FROM $table ";
	$result = mysql_query($sql, $dbh_local) or die(mysql_error());

while($row = mysql_fetch_array($result)) {
	$inv_ids[] = $row['inventor_id'];
}
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

//Update the university
$i = 0;
$sql = "UPDATE $table SET acad_if_one='1' ";
$sql .= " WHERE (inventor_id = '$univ_affiliated_ids[0]' ";
for ($i=1; $i<$univ_count; $i++) {
	$sql .= "OR inventor_id = '$univ_affiliated_ids[$i]' ";
}
$sql .= ")";

$result = mysql_query($sql, $dbh_local) or die(mysql_error());

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


//Now to return a value to decide how big the rendered image will be
if ($count_inv > 500)
	return 'big';
else return 'reg';

}
?>


