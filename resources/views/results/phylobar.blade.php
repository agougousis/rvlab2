<!-- ***************************************************************
# Name:      PHYLObar.html
# Version:   0.1# Authors:   Umer Zeeshan Ijaz (Umer.Ijaz@glasgow.ac.uk)
#                 http://userweb.eng.gla.ac.uk/umer.ijaz
# Created:   2014-11-10
# License:   Copyright (c) 2014 Computational Microbial Genomics Group, University of Glasgow, UK
#
#            This program is free software: you can redistribute it and/or modify
#            it under the terms of the GNU General Public License as published by
#            the Free Software Foundation, either version 3 of the License, or
#            (at your option) any later version.
#
#            This program is distributed in the hope that it will be useful,
#            but WITHOUT ANY WARRANTY; without even the implied warranty of
#            MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#            GNU General Public License for more details.
#
#            You should have received a copy of the GNU General Public License
#            along with this program.  If not, see <http://www.gnu.org/licenses/>.
-->


<style>
.node {
  cursor: pointer;
}

.node circle {
  fill: #fff;
  stroke: steelblue;
  stroke-width: 1.5px;
}
.node text{
  font: 10px sans-serif;	
}
.link {
  fill: none;
  stroke: #ccc;
  stroke-width: 1.5px;
}

</style>


  
    <meta content='text/html;charset=UTF-8' http-equiv='content-type'>
    
    <script src="{{ asset('js/d3.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/newick.js') }}" type="text/javascript"></script>
	
    <script>
     d3.text("{{ $table_nwk }}",function(text){
         //console.log(text); 
		var newick=Newick.parse(text);
		var newickNodes = []
        	function buildNewickNodes(node, callback) {
          	newickNodes.push(node)
            //console.log(node);
          		if (node.branchset) {
            			for (var i=0; i < node.branchset.length; i++) {
                            buildNewickNodes(node.branchset[i])
                            //console.log(i);
            				}
          			}
        	}

	
		buildNewickNodes(newick);	


		// ==load annotation == //
		// Ref: http://stackoverflow.com/questions/14446447/javascript-read-local-text-file
		var allText;		
		function readTextFile(file)
			{			
                var rawFile = new XMLHttpRequest();
    			rawFile.open("GET", file, false);
    			rawFile.onreadystatechange = function ()
    				{
        			if(rawFile.readyState === 4)
        				{
            				if(rawFile.status === 200 || rawFile.status == 0)
            					{
                				allText = rawFile.responseText;
            					}
        				}
    				}
                    rawFile.send(null);
                
			}

		//readTextFile("{{ $table_csv }}");
		$.get("{{ $table_csv }}", function(data) {
        var allText = new String(data);
		// Split the lines
    		var lines = allText.split('\n');
    		var i = 0;
    		var j = 0;
    		var min_value=Number.MAX_VALUE;
    		var max_value=Number.MIN_VALUE;
    		var data_table= new Array();
    		var nRows=lines.length;
    		var nCols;

    		//first pass to populate min_value, max_value, data_table and nCols
    		for(i=0; i<lines.length; i++)
    			{
        		data_table[i]=new Array();
        		var records = lines[i].split(',');
        		if(i==0){
                		nCols=records.length;
                		}

        		for(j=0; j<records.length; j++)
                		{

                		if ((i>0) && (j>0)) {
                        		if (parseFloat(records[j]) >= max_value) {
                                		max_value=parseFloat(records[j]);
                                		}
                        		if (parseFloat(records[j]) <= min_value) {
                                		min_value=parseFloat(records[j]);
                                		}
                        		data_table[i][j]=parseFloat(records[j]).toString();
                        		}
                		else {
                        		data_table[i][j]=records[j];
                        		}

                		}	
    			}
        
		// ==/load annotation == //
	
		// == make key value pair == //	
		
		var annot_table={};
		for(i=1;i<nRows;i++){
			var tmp = data_table[i].slice(); //duplicate array
			key=tmp.shift();
			annot_table[key]=tmp;
			}
		
		//add list of 21 distinct colors
		var defColor=["#F0A3FF", "#0075DC", "#993F00","#4C005C","#2BCE48","#FFCC99","#808080","#94FFB5","#8F7C00","#9DCC00","#C20088","#003380","#FFA405","#FFA8BB","#426600","#FF0010","#5EF1F2","#00998F","#740AFF","#990000","#FFFF00"];

		var diameter = 1000;

		var margin = {top: 20, right: 120, bottom: 20, left: 120},
    		width = diameter,
    		height = 1600;
    
		var i = 0,
    		duration = 350,
    		root;


	var tree = d3.layout.tree()
    	.size([360, diameter / 2 - 80])
    	//.separation(function(a, b) { return (a.parent == b.parent ? 1 : 10) / a.depth; });
    	.separation(function(a, b) { 
    	if (a.depth == 0) {
      		return 1;
    	} else {
      	return (a.parent == b.parent ? 1 : 10) / a.depth;
    	}
  	});	



	var diagonal = d3.svg.diagonal.radial()
    		.projection(function(d) { return [d.y, d.x / 180 * Math.PI]; });

	var svg = d3.select("#svg").append("svg:svg")
    		.attr("width", 1600 )
    		.attr("height", height )
  		.append("g")
    		.attr("transform", "translate(" + 700 / 2 + "," + diameter / 2 + ")");

	// == create a save image function == //
	d3.select("#save").on("click", function(){
  		var html = d3.select("svg")
		.attr("version", 1.1)
        	.attr("xmlns", "http://www.w3.org/2000/svg")
        	.node().parentNode.innerHTML;

  		var imgsrc = 'data:image/svg+xml;base64,'+ btoa(html);
  		var img = '<img src="'+imgsrc+'">'; 
  		var canvas = document.querySelector("canvas"),
	  	context = canvas.getContext("2d");

  		var image = new Image;
  		image.src = imgsrc;
  		image.onload = function() {
	  		context.drawImage(image, 0, 0);

	  		var canvasdata = canvas.toDataURL("image/png");

	  		var pngimg = '<img src="'+canvasdata+'">'; 
	  		var a = document.createElement("a");
	  		a.download = "snapshot.png";
	  		a.href = canvasdata;
          		document.body.appendChild(a);
	  		a.click();
	  		context.clearRect(0, 0, diameter, diameter);	
  		};


	});
    
	// == /create a save image function == //


	// generate legends
	var legend_labels=data_table[0].slice();
	legend_labels.shift();

	for(i=0;i<legend_labels.length;i++)
		{
		svg.append("rect")
			.attr("x",-diameter/2+150)
            		.attr("y",(-diameter/2)+(i*20))
            		.attr("width",10)
            		.attr("height",10)
			.attr("fill",defColor[i]);
		svg.append("text")
			.attr("x",(-diameter/2)+15+150)
                        .attr("y",(-diameter/2)+(i*20)+10)
			.text(legend_labels[i])
			.attr("font-size", "10px");
		}
    


	root = newick;
	root.x0 = height / 2;
	root.y0 = 0;

	//root.children.forEach(collapse); // start with all children collapsed
	update(root);

	//d3.select(self.frameElement).style("height", "800px");

	function update(source) {

  		// Compute the new tree layout.
  		var nodes = tree.nodes(root),
      		links = tree.links(nodes);
		

  		// Normalize for fixed-depth.
  		nodes.forEach(function(d) { d.y = d.depth * 80; });

  		// Update the nodes…
  		var node = svg.selectAll("g.node")
      			.data(nodes, function(d) { return d.id || (d.id = ++i); });

  		// Enter any new nodes at the parent's previous position.
  		var nodeEnter = node.enter().append("g")
      			.attr("class", "node")
      			//.attr("transform", function(d) { return "rotate(" + (d.x - 90) + ")translate(" + d.y + ")"; })
      			.on("click", click);

  		nodeEnter.append("circle")
      			.attr("r", 1e-6)
      			.style("fill", function(d) { return d._children ? "lightsteelblue" : "#fff"; })
			.style("stroke","steelblue")
                        .style("stroke-width","1.5px");



// == pretty stringify for js arrays == //

// Upgrade for JSON.stringify, updated to allow arrays
// Ref:http://stackoverflow.com/questions/16196338/json-stringify-doesnt-work-with-normal-javascript-array
(function(){
    // Convert array to object
    var convArrToObj = function(array){
        var thisEleObj = new Object();
        if(typeof array == "object"){
            for(var i in array){
                var thisEle = convArrToObj(array[i]);
                thisEleObj[i] = thisEle;
            }
        }else {
            thisEleObj = array;
        }
        return thisEleObj;
    };
    var oldJSONStringify = JSON.stringify;
    JSON.stringify = function(input){
        if(oldJSONStringify(input) == '[]')
            return oldJSONStringify(convArrToObj(input));
        else
            return oldJSONStringify(input);
    };
})();

// ==/pretty stringify for js arrays == //


		// == code to draw stacked bars and text== //

		var freq_log={};	
		for (i=0;i<(nCols-1);i++){
                	nodeEnter.append("rect")
                        	//.attr("x",function(d){return (10*i)+5;})
				.attr("x",function(d){
                                        _t=annot_table[d.name];
                                        if (_t && d.name.length>0){
                                                if(d.name in freq_log){
                                                        freq_log[d.name]=parseFloat(freq_log[d.name])+parseFloat(annot_table[d.name][i]);
                                                        }
                                                else{freq_log[d.name]=parseFloat(annot_table[d.name][i]);};				
                                                return freq_log[d.name]-parseFloat(annot_table[d.name][i])+10;}
                                        else {return 10};})

                        	.attr("y",-6)
                                .attr("width",function(d){
                                        _t=annot_table[d.name];
                                        if (_t && d.name.length>0){
                                                return annot_table[d.name][i];}
                                        else {return 0};})
                        	.attr("height",10)
                        	.style("fill",function(d){
					_t=annot_table[d.name];
					if (_t && d.name.length>0){
						return defColor[i];} 
					else {return "white"};})
				.style("fill-opacity",function(d){_t=annot_table[d.name];if(_t && d.name.length>0){return 1;}else {return 0.2;}});
		}


  		nodeEnter.append("text")
      			.attr("x",function(d){
				_t=annot_table[d.name];
				if (_t && d.name.length>0){
					return freq_log[d.name]+10;
					} 
				else {return 10};
				})
      			.attr("dy", ".35em")
     			//.attr("text-anchor", "start")
      			//.attr("transform", function(d) { return d.x < 180 ? "translate(0)" : "rotate(180)translate(-" + (d.name.length * 10)  + ")"; })
      			.text(function(d) { if(d.length) {return "("+d3.format(",.3f")(d.length)+")"+d.name;}else {return ""} })
      			.style("fill-opacity", 1e-6);
		
		// == /code to draw stacked bars and text == //	
			


  		// Transition nodes to their new position.
  		var nodeUpdate = node.transition()
      			.duration(duration)
      			.attr("transform", function(d) { return "rotate(" + (d.x - 90) + ")translate(" + d.y + ")"; })

  		nodeUpdate.select("circle")
      			.attr("r", 4.5)
      			.style("fill", function(d) { return d._children ? "lightsteelblue" : "#fff"; });

  		nodeUpdate.select("text")
      			.style("fill-opacity", 1);
      			//.attr("transform", function(d) { return d.x < 180 ? "translate(0)" : "rotate(180)translate(-" + (d.name.length + 50)  + ")"; });

  		// TODO: appropriate transform
  		var nodeExit = node.exit().transition()
      			.duration(duration)
      			//.attr("transform", function(d) { return "diagonal(" + source.y + "," + source.x + ")"; })
      			.remove();

  		nodeExit.select("circle")
      			.attr("r", 1e-6);

  		nodeExit.select("text")
      			.style("fill-opacity", 1e-6);

  		// Update the links…
  		var link = svg.selectAll("path.link")
      			.data(links, function(d) { return d.target.id; });


  		// Enter any new links at the parent's previous position.
  		link.enter().insert("path", "g")
      			.attr("class", "link")
      			.attr("d", function(d) {
        			var o = {x: source.x0, y: source.y0};
        			return diagonal({source: o, target: o});
      			})
  			.style("fill","")
			.style("fill-opacity",1e-6)
  			.style("stroke","#ccc")
  			.style("stroke-width","1.5px");

  		// Transition links to their new position.
  		link.transition()
      			.duration(duration)
      			.attr("d", diagonal);

  		// Transition exiting nodes to the parent's new position.
  		link.exit().transition()
      			.duration(duration)
      			.attr("d", function(d) {
        			var o = {x: source.x, y: source.y};
        			return diagonal({source: o, target: o});
      			})
      			.remove();

  		// Stash the old positions for transition.
  		nodes.forEach(function(d) {
    			d.x0 = d.x;
    			d.y0 = d.y;
  		});
	}

	// Toggle children on click.
	function click(d) {
  		if (d.children) {
    			d._children = d.children;
    			d.children = null;
  		} else {
    			d.children = d._children;
    			d._children = null;
  		}
  
  	update(d);
	}

	// Collapse nodes
	function collapse(d) {
  		if (d.children) {
      			d._children = d.children;
      			d._children.forEach(collapse);
      			d.children = null;
    		}
	}

    },dataType='text');

	});
    </script>
    <style type="text/css" media="screen">
      body { font-family: "Helvetica Neue", Helvetica, sans-serif; }
      td { vertical-align: top; }
    </style>
  
  <body>
<div id="svg">
</div>
<button id="save">Create Snapshot (PNG)</button>	
<canvas width="1600" height="1600" style="display:none"></canvas>
  </body>


