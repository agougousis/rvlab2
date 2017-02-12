<!-- ***************************************************************
# Name:      HEATcloud.html
# Purpose:   This web page generates a heapmap from a comma separated
#            table N X E abundance table "table.csv" located in the current directory,
#            where N specifies the Samples and E specifies features.
#            For each sample, a word cloud displaying the most significant features can be
#            visualized by moving mouse on the sample name.
# Version:   0.1
# Authors:   Umer Zeeshan Ijaz (Umer.Ijaz@glasgow.ac.uk)
#                 http://userweb.eng.gla.ac.uk/umer.ijaz
# Created:   2014-02-19
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

<style type="text/css">
body {
    font-family: "Helvetica Neue", Helvetica, sans-serif;
     }
    
.r90 {
-webkit-transform: rotate(90deg);
-moz-transform: rotate(90deg);
-o-transform: rotate(90deg);
-ms-transform: rotate(90deg);
transform: rotate(90deg);
width: 1em;
padding-left: 1em;
line-height: 1ex; 
}

div#pop-up {
display: none;
position: absolute;
width: 1000px;
padding: 10px;
color: #000000;
font-size: 90%;
}
.hiddendiv{
display:none;
}
   
</style>

<div id="msgid">
</div>

<div id="pop-up">
    <div id="myCanvasContainer">
        <canvas width="500" height="500" id="myCanvas">
            <p>Anything in here will be replaced on browsers that support the canvas element</p>
        </canvas>
    </div>
    <div id="tags">
        <ul>
            <li><a href="http://www.google.com" target="_blank">Google</a></li>
            <li><a href="/fish">Fish</a></li>
            <li><a href="/chips">Chips</a></li>
            <li><a href="/salt">Salt</a></li>
            <li><a href="/vinegar">Vinegar</a></li>
        </ul>
    </div>
</div>

<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>

    
<script src="{{ asset('js/jquery-1.6.2.min.js') }}"></script>
<script src="{{ asset('js/jquery.tagcanvas.min.js') }}" type="text/javascript"></script>

<script>

String.prototype.paddingLeft = function (paddingValue) {
   return String(paddingValue + this).slice(-paddingValue.length);
};

function ScaleValue(v,omin,omax,nmin,nmax){
	return (v/((omax-omin)/(nmax-nmin)))+nmin;
}

function GetColour(v,vmin,vmax)
{
   var c=[1.0,1.0,1.0];
   var dv;

   if (v < vmin)
      v = vmin;
   if (v > vmax)
      v = vmax;
   dv = vmax - vmin;

   if (v < (vmin + 0.25 * dv)) {
      c[0] = 0;
      c[1] = 4 * (v - vmin) / dv;
   } else if (v < (vmin + 0.5 * dv)) {
      c[0] = 0;
      c[2] = 1 + 4 * (vmin + 0.25 * dv - v) / dv;
   } else if (v < (vmin + 0.75 * dv)) {
      c[0] = 4 * (v - vmin - 0.5 * dv) / dv;
      c[2] = 0;
   } else {
      c[1] = 1 + 4 * (vmin + 0.75 * dv - v) / dv;
      c[2] = 0;
   }

   return '#'+Number(parseInt( c[0]*255 , 10)).toString(16).paddingLeft('00')+Number(parseInt( c[1]*255 , 10)).toString(16).paddingLeft('00')+Number(parseInt( c[2]*255 , 10)).toString(16).paddingLeft('00');
}


$(document).ready(function(){

    if(!$('#myCanvas').tagcanvas({
          textColour: '#ff0000',
          outlineColour: '#ff00ff',
          reverse: true,
          depth: 0.8,
          maxSpeed: 0.05
        },'tags')) {
          // something went wrong, hide the canvas container
          $('#myCanvasContainer').hide();
    }

 

    $.get("{{ $table_csv }}", function(data) {
    var dataStr = new String(data);	
    // Split the lines
    var lines = dataStr.split('\n');
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
			if (Math.sqrt(parseFloat(records[j])) >= max_value) {
				max_value=Math.sqrt(parseFloat(records[j]));
				}
			if (Math.sqrt(parseFloat(records[j])) <= min_value) {
				min_value=Math.sqrt(parseFloat(records[j]));
				}			
			data_table[i][j]=Math.sqrt(parseFloat(records[j])).toString();
			}
		else {
			data_table[i][j]=records[j];
			}
		
		}      
    }

    $("#msgid").append('<table class="table">');
    for(i=1;i<nRows;i++){
	$("#msgid").append("<tr>");
	var hidden_string="";
    	for(j=1;j<nCols;j++){
		if (parseFloat(data_table[i][j])>0.0){
          hidden_string=hidden_string+data_table[0][j]+','+data_table[i][j]+'<BR>';
			}	
		$("#msgid").append('<td bgcolor="'+GetColour(ScaleValue(parseFloat(data_table[i][j]),min_value,max_value,0.0,1.0),0.0,1.0)+'">'+'</td>');
		}
	$("#msgid").append('<td><a href="#" class="trigger">'+data_table[i][0]+'<div class="hiddendiv">'+hidden_string+'</div></a></td></tr>');
    }
    $("#msgid").append("<tr>");	
    for(j=1;j<nCols;j++){
	$("#msgid").append('<td><div class="r90">'+data_table[0][j]+'</div></td>');
	}	
    $("#msgid").append("</tr></table>"); 

    $(function() {
        var moveLeft = -1000;
        var moveDown = 10;
        
      $('.trigger').hover(function(e) {
      var test=$(this).find('.hiddendiv').html().split('<br>');
      var mystr='';
      var k;
      for (k=0;k<(test.length-1);k++){
             var record=test[k].split(',');
             mystr=mystr+'<a style="font-size: '+parseFloat(record[1]).toFixed(2).toString()+'ex" href="#">'+record[0]+'</a>';
      }
                            
                            
     $('#tags').html(mystr);
                            
	  if(!$('#myCanvas').tagcanvas({
          textColour: '#ff0000',
          textFont:'Impact,Arial Black,sans-serif',
          outlineColour: '#ff00ff',
          reverse: true,
          depth: 0.8,
          weight:true,
          weightMode:"both",
          initial:[0.1,-0.1],
          maxSpeed: 0.05
        },'tags')) {
          $('#myCanvasContainer').hide();
    	  }
                            
          $('div#pop-up').show();

        }, function() {
          $('div#pop-up').hide();
        });
        
        $('.trigger').mousemove(function(e) {
          $("div#pop-up").css('top', e.pageY + moveDown).css('left', e.pageX+1150 + moveLeft);
        });

      }); 		

    },dataType='text');
    

});
</script>



