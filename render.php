<?
//---------------------------------------
//---------------------------------------
//Defaults

//Default JSON file
$json = 'src';

//Screen dimensions
$screen_width = 5000;
$screen_height = 5000;

//Charge - pushes apart nodes
$charge = '-7000';

//---------------------------------------
//---------------------------------------
//Digest passed info

//JSON file
if(isset($_GET['json'])) {
	$json_temp = $_GET['json'];
	
	if (!($json_temp == 'src') && !($json_temp == 'src_univ')) {
		//Must be a cached version, so look in that folder
		$json = "Cached/$json_temp";
	}
	else $json = $json_temp;
}

//Screen dimensions
if(isset($_GET['screen_width'])) {
	$screen_width = $_GET['screen_width'];
}
if(isset($_GET['screen_height'])) {
	$screen_height = $_GET['screen_height'];
}

//Charge (mostly put in so people can manipulate it via URL)
if(isset($_GET['charge'])) {
	$charge = $_GET['charge'];
	$charge = -$charge;
}


?>

<!DOCTYPE html>
<meta charset="utf-8">
<script src="./d3.v3.js"></script>
<style>

.link {
  fill: none;
}

.node circle {
  stroke: #fff;
  //stroke: red;
  stroke-width: 1.5px;
}

text {
  font: 15px sans-serif;
	opacity:0.83;
  pointer-events: none;
}

</style>
<body>

<script src="<? echo $json; ?>.json"></script>

<script>

function onlyUnique(value, index, self) { 
    return self.indexOf(value) === index;
}

colorx=d3.scale.category10().range();


//alert(JSON.stringify(colorx));
//alert(JSON.stringify(links));
//alert(JSON.stringify(bubble_inventor_assignee));

cc=[];
//alert(JSON.stringify(cc));
//alert(links.length);
for(i=0;i<links.length;i++)
{
	cc.push(links[i].source);
}

//alert(JSON.stringify(cc));
//alert(cc.length);



//alert(cc.length);
cc=cc.filter( onlyUnique );

//alert(cc.length);

var nodes = {};

// Compute the distinct nodes from the links.
links.forEach(function(link) {
  link.source = nodes[link.source] || (nodes[link.source] = {name: link.source});
  link.target = nodes[link.target] || (nodes[link.target] = {name: link.target});
  //link.source = nodes[link.source] || (nodes[link.source] = {name: link.sourcex});
  //link.target = nodes[link.target] || (nodes[link.target] = {name: link.targetx});
});


//alert(JSON.stringify(nodes));


var width = <? echo $screen_width; ?>,
    height = <? echo $screen_height; ?>;

var force = d3.layout.force()
    .nodes(d3.values(nodes))
    .links(links)
    .size([width, height])
    //.linkDistance(500)
    .charge(<? echo $charge; ?>)
		.friction(0.3)
		.chargeDistance(1500)
    .on("tick", tick)
    .start();

var svg = d3.select("body").append("svg")
    .attr("width", width)
    .attr("height", height);

var link = svg.selectAll(".link")
		.data(force.links())
		.enter().append("line")
		.attr("stroke-width",function(d){return (d.s1)*15*3;})
		.attr("stroke-dasharray",function(d){return "none"; if(d.flag==2) return ("2,2"); else return "none";})
    .attr("class", "link")
		.attr("stroke", function(d){
							value=d.t1;
							if(value==0) return "#D0D0D0";
							if(value==1) return "#B8B8B8";
							if(value==2) return "gray";
							if(value==4) return "black"; // impossible due to tie calculation
						})
		.attr("stroke-opacity",function(d)
						{
							if(d.t1<=1)
								return 0;
							else
								return 0.2;
							if(d.flag==0) return 0;
							if(d.flag==1) return 1;
							if(d.flag==2) return 1;
						});

var node = svg.selectAll(".node")
    .data(force.nodes())
  .enter().append("g")
    .attr("class", "node")
    .on("mouseover", mouseover)
    .on("mouseout", mouseout)
    .call(force.drag);

node.append("circle")
		.attr("fill",function(d,i){
						color_index=bubble_inventor_assignee[d.name];
						if(color_index>100) return "#A8A8A8";
						return colorx[color_index-1];
					})
		//.style("opacity",0.83)
    .attr("r", function(d,i){
						color_index=bubble_inventor_citation[d.name];
						return Math.pow(color_index,0.3)*1+3+0;
						return Math.sqrt(color_index)*0.7+3;
						return Math.log(color_index)*0.7+3;
						if(color_index==100) return 2;
});

node.append("text")
    .attr("x", 12)
    .attr("dy", ".35em")
		.style("opacity",0.6)
    //.text(function(d) {if(bubble_inventor_citation[d.name]>100) return d.name+"("+bubble_inventor_citation[d.name]+" citations)"; else return ""; });
    .text(function(d) {if(bubble_inventor_citation[d.name]>=0) { return d.name; return d.name+"("+bubble_inventor_citation[d.name]+")";} else return ""; });

var textx=svg.selectAll("text.legend")
			.data(Object.keys(bubble_assignee_count))
			.enter()
			.append("svg:text")
			.style("font-weight", "bold")
			.attr("fill",function(d,i){if(i>colorx.length-1) return "#A8A8A8"; return colorx[i];})
			.attr("x",20)
			.attr("y",function(d,i){return i*20+40;})
			.text(function(d,i){return (i+1)+". "+d+" ("+bubble_assignee_count[d]+" patents)";});


function tick() {
  link
      .attr("x1", function(d) { return d.source.x; })
      .attr("y1", function(d) { return d.source.y; })
      .attr("x2", function(d) { return d.target.x; })
      .attr("y2", function(d) { return d.target.y; });

  node
      .attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; });
}

function mouseover() {
  d3.select(this).select("circle").transition()
      .duration(750)
      .attr("r", 16);
}

function mouseout() {
  d3.select(this).select("circle").transition()
      .duration(750)
      .attr("r", 8);
}

</script>







