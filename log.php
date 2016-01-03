<link rel="stylesheet" href="style.css">

<?

$ip = $_SERVER['REMOTE_ADDR'];
date_default_timezone_set('America/Los_Angeles');
$current_datetime = date("Y-m-d H:i:s");
$last_day = date("Y-m-d H:i:s", strtotime("-1 day"));
$last_week = date("Y-m-d H:i:s", strtotime("-1 week"));
$last_month = date("Y-m-d H:i:s", strtotime("-1 month"));

//DB for holding this info
$user_local = "wbtmougo_oreagan";
$pass_local = "bruno204"; 
$host_local = "localhost";

$dbh_local = mysql_connect($host_local, $user_local, $pass_local, true) or die("Unable to connect to MySQL");

mysql_select_db("wbtmougo_dblood", $dbh_local) or die("Could not select local");


//------------------------------------------------------
//------------------------------------------------------
//Counter table

$sql  = "SELECT * FROM counter";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());

$generated_time = array();
$ip = array();
$date_start = array();
$date_end = array();
$generation = array();
$origin_ids = array();

$n = 0;
while ($row = mysql_fetch_array($result)) {
	$generated_time[$n] = $row['generated'];
	$ip[$n] = $row['ip'];
	$date_start[$n] = $row['date_start'];
	$date_end[$n] = $row['date_end'];
	$generation[$n] = $row['generation'];
	$origin_ids[$n] = $row['origin_ids'];
	
	//echo "IP was $ip[$n], time was $generated_time[$n]<br>";
	
	$n++;
}

$unique_ips_array = array_unique($ip);
$unique_ips_count = count($unique_ips_array);

$total_generated_count = count($generated_time);

$last_day_count = 0;
$last_week_count = 0;
$last_month_count = 0;
for ($i = 0; $i <= $n; $i++) {
	if ($generated_time[$i] > $last_day) $last_day_count++;
	if ($generated_time[$i] > $last_week) $last_week_count++;
	if ($generated_time[$i] > $last_month) $last_month_count++;
}
echo "Unique IP addresses: $unique_ips_count<br>";
echo "Total diagrams generated: $last_month_count<br><br>";

echo "Number of diagrams generated in the PAST DAY: $last_day_count<br>";
echo "Number of diagrams generated in the PAST WEEK: $last_week_count<br>";
echo "Number of diagrams generated in the PAST MONTH: $last_month_count";

echo "<br>--------------------------------------------<br>";
//------------------------------------------------------
//------------------------------------------------------
//Inventor search table

$sql  = "SELECT * FROM inventors_searched";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());

$name = array();
$c = 0;
while ($row = mysql_fetch_array($result)) {
	$first_name = $row['name_first'];
	$last_name = $row['name_last'];
	
	$first = ucfirst(strtolower($first_name)); //Capitalize all
	$first = str_replace("-"," ",$first); //Remove hyphens
	$first = rtrim($first, '.'); //Remove trailing periods
	
	$last = ucfirst(strtolower($last_name)); //Capitalize first letter, lower-case others
		
	$name[$c] = "$first $last";
		
	$c++;
}

$names_searched = array_unique($name);
$names_searched_count = count($names_searched);

$counts = array_count_values($name);
arsort($counts);

echo "<b>Most searched-for inventors:</b><br>";
echo "<table border='1'>";
echo "<tr><td><b>Inventor</b></td><td><b>Times Searched</b></td></tr>";
while (list ($key, $val) = each ($counts)) {
	echo "<tr><td>$key</td><td>$val</td></tr>";
}
echo "</table>";

echo "<br>--------------------------------------------<br>";
//------------------------------------------------------
//------------------------------------------------------
//Classes searched

$sql  = "SELECT * FROM classes_searched";
$result = mysql_query($sql, $dbh_local) or die(mysql_error());

$class = array();
$d = 0;
while ($row = mysql_fetch_array($result)) {
	$class_main = $row['class_main'];
	$class_sub = $row['class_sub'];
	
	$class[$d] = "$class_main/$class_sub";
	
	$d++;
}

$class_searched_count = $d;

$counts = array_count_values($class);
arsort($counts);

echo "<b>Most searched-for class/sub-class:</b><br>";
echo "<table border='1'>";
echo "<tr><td><b>Class</b></td><td><b>Times Searched</b></td></tr>";
while (list ($key, $val) = each ($counts)) {
	echo "<tr><td>$key</td><td>$val</td></tr>";
}
echo "</table>";

echo "<br>--------------------------------------------<br>";
//------------------------------------------------------
//------------------------------------------------------
//Print log of last 50 searches

//Geo-lookup section
$ips = $ip;
$ips = array_unique($ips);
$geo_lookup = array();
foreach ($ips as $ip_here) {
	$url = "http://api.db-ip.com/addrinfo?api_key=d3c06d1c71bf5eacd74229b163b58c38ef4cc37b&addr=$ip_here";
	$json_temp = file_get_contents($url);
	$data = json_decode($json_temp);
	
	$city = $data->{'city'};
	$state = $data->{'stateprov'};
	
	$geo_lookup[$ip_here] = "$city, $state";
}

//Print
echo "Previous 50 searches<br>";
echo "<table border='1'>";

echo "<tr>";
echo "<td><b>Time generated</b></td>";
echo "<td><b>IP address</b></td>";
echo "<td><b>Location</b></td>";
echo "<td><b>Date_start</b></td>";
echo "<td><b>Date_end</b></td>";
echo "<td><b>Generations</b></td>";
echo "<td><b>Origin IDs</b></td>";
echo "</tr>";

if ($n > 50) $j = $n-50;
else $j = 0;

for ($i = $n-1; $i>= $j; $i--) {
	$ip_temp = $ip[$i];
	
	echo "<tr>";
	echo "<td>$generated_time[$i]</td>";
	echo "<td>$ip_temp</td>";
	echo "<td>$geo_lookup[$ip_temp]</td>";
	echo "<td>$date_start[$i]</td>";
	echo "<td>$date_end[$i]</td>";
	echo "<td>$generation[$i]</td>";
	echo "<td>$origin_ids[$i]</td>";
	echo "</tr>";	
	
}
echo "</table>";

echo "<br><Br>IP lookup provided by <a href='https://db-ip.com/'>https://db-ip.com/</a>";
?>

