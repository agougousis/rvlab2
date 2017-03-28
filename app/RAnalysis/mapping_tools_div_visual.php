<?php

namespace App\RAnalysis;

use Session;
use Validator;
use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes an mapping_tools_div_visual analysis
 *
 * BaseAnalysis initializes the following properties:
 *   $form
 *   $job_id
 *   $job_folder
 *   $remote_job_folder
 *   $user_workspace
 *   $remote_user_workspace
 *   &$inputs
 *   &$params
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class mapping_tools_div_visual extends BaseAnalysis implements RAnalysis {

    /**
     * The first input file to be used for the analysis
     *
     * @var string
     */
    private $box;

    /**
     * The second input file to be used for the analysis
     *
     * @var string
     */
    private $box2;

    /**
     * The third input file to be used for the analysis
     *
     * @var string
     */
    private $box3;

    /**
     * The transpose parameter
     *
     * @var string
     */
    private $transpose;

    /**
     * The transf_method_select parameter
     *
     * @var string
     */
    private $transf_method_select;

    /**
     * The top_species parameter
     *
     * @var int
     */
    private $top_species;

    /**
     * The validation rules for mapping_tools_div_visual submission form
     *
     * @var array
     */
    private $formValidationRules = [
        'box'       =>  'required|string|max:250',
        'box2'      =>  'required|string|max:250',
        'box3'      =>  'required|string|max:250',
        'transpose' => 'string|max:250',
        'transf_method_select'  =>  'required|string|max:250',
        'top_species'           =>  'required|int',
        'column_select'         =>  'required|string|max:250'
    ];

    /**
     * Runs a mapping_tools_div_visual analysis
     *
     * @return boolean
     */
    public function run()
    {
        try {
            $this->validateForm();

            $this->getInputParams();

            $this->copyInputFiles();

            $this->buildRScript();
        } catch (Exception $ex) {
            if (!empty($ex->getMessage())) {
                $this->log_event($ex->getMessage(), "error");
            }

            return false;
        }

        // Execute the bash script
        system("chmod +x $this->job_folder/$this->job_id.pbs");
        system("$this->job_folder/$this->job_id.pbs > /dev/null 2>&1 &");

        return true;
    }

    /**
     * Validates the submitted form
     *
     * @throws \Exception
     */
    private function validateForm()
    {
        $validator = Validator::make($this->form, $this->formValidationRules);

        if ($validator->fails()) {
            // Load validation error messages to a session toastr
            Session::flash('toastr', implode('<br>', $validator->errors()->all()));
            throw new \Exception('');
        }
    }

    /**
     * Moved input files from workspace to job's folder
     *
     * @throws Exception
     */
    private function copyInputFiles()
    {
        $workspace_filepath = $this->user_workspace . '/' . $this->box;
        $job_filepath = $this->job_folder . '/' . $this->box;

        if (!copy($workspace_filepath, $job_filepath)) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $workspace_filepath = $this->user_workspace . '/' . $this->box2;
        $job_filepath = $this->job_folder . '/' . $this->box2;

        if (!copy($workspace_filepath, $job_filepath)) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $workspace_filepath = $this->user_workspace . '/' . $this->box3;
        $job_filepath = $this->job_folder . '/' . $this->box3;

        if (!copy($workspace_filepath, $job_filepath)) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $image_filepath = public_path().'/images/world_3.kml';
        $job_filepath = $this->job_folder.'/world_3.kml';
        if(!copy($image_filepath,$job_filepath)){
            throw new Exception('Moving public image to job folder, failed.');
        }
    }

    /**
     * Retrieves input parameters from form data
     *
     * @throws Exception
     */
    private function getInputParams()
    {
        $this->box = $this->form['box'];

        $this->box2 = $this->form['box2'];
        $this->inputs .= ";" . $this->box2;

        if (empty($this->form['transpose'])) {
            $this->transpose = "";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->transf_method_select = $this->form['transf_method_select'];
        $this->params .= ";transf_method_select:" . $this->transf_method_select;

        $this->top_species = $this->form['top_species'];
        $this->params .= ";top_species:".$this->top_species;

        $this->column_select = $this->form['column_select'];
        $this->params .= ";column_select:".$this->top_species;
    }

    /**
     * Builds the required executables for the job execution
     *
     * @throws Exception
     */
    private function buildRScript()
    {
        // Build the R script
        if (!($fh = fopen("$this->job_folder/$this->job_id.R", "w"))) {
            throw new \Exception("Unable to open file $this->job_folder/$this->job_id.R");
        }

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "library(stringr);\n");
        fwrite($fh, "library(maptools);\n");
        fwrite($fh, "library(sp);\n");
        fwrite($fh, "library(plyr);\n");
        fwrite($fh, "library(dplyr);\n");
        fwrite($fh, "library(tidyr);\n");

        fwrite($fh, "x <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "coords <- read.table(\"$this->remote_job_folder/$this->box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "colnames(coords) <- gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(coords)));\n");
        fwrite($fh, "colnames(coords)[1] <- \"Longitude\";\n");
        fwrite($fh, "colnames(coords)[2] <- \"Latitude\";\n");
        fwrite($fh, "indices <- read.table(\"$this->remote_job_folder/$this->box3\",header = TRUE, sep=\",\",row.names=1);\n");
        if($this->transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($this->transf_method_select != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$this->transf_method_select\");\n");
        }

        fwrite($fh, "tkml <- getKMLcoordinates(kmlfile=\"world_3.kml\", ignoreAltitude=T);\n");

        fwrite($fh, "#Create polygon from coordinates;\n");
        fwrite($fh, "p1<-list();\n");
        fwrite($fh, "for (i in 1:length(tkml))  p1[[i]] <- Polygon(tkml[[i]])   #loop nodig\n");

        fwrite($fh, "#Create Polygon class;\n");
        fwrite($fh, "p2 = Polygons(p1, ID=\"z\");\n");

        fwrite($fh, "#Create Spatial Polygons class en referentie systeem is nodig;\n");
        fwrite($fh, "p3= SpatialPolygons(list(p2),proj4string=CRS(\"+proj=longlat +datum=WGS84 +ellps=WGS84 +towgs84=0,0,0\"));\n");

        fwrite($fh, "polys<-list(\"sp.polygons\", p3, fill = \"lightgreen\");\n");

        fwrite($fh, "sub_points<-coords%>%\n");
        fwrite($fh, "select(Longitude,Latitude);\n");
        fwrite($fh, "sub_points_coords<-sub_points[,1:2];\n");
        fwrite($fh, "sub_points_SP<-SpatialPoints(sub_points_coords);\n");
        fwrite($fh, "sub_points_SPDF<-SpatialPointsDataFrame(sub_points_coords, indices);\n");

        fwrite($fh, "#Set color set to be used for classes of data  ;\n");
        fwrite($fh, "colorset6<-c(\"#FFFF00\", \"#FFCC00\", \"#FF9900\", \"#FF6600\", \"#FF3300\", \"#FF0000\");\n");
        fwrite($fh, "plottest <- spplot(sub_points_SPDF, zcol=c(\"$this->column_select\"), xlab=\"\",\n");
        fwrite($fh, "scales=list(draw = TRUE), sp.layout=list(polys), cuts = 6, col.regions=colorset6,xlim=c(-180,180),ylim=c(-80,80),par.settings = list(panel.background=list(col=\"lightblue\")));\n");

        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$this->top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        fwrite($fh, "names<-gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(new_x)));\n");
        fwrite($fh, "rownames(new_x) <- gsub(\"\\\.\",\"-\",gsub(\" \",\"_\",rownames(new_x)));\n");
        fwrite($fh, "sink(\"dataMapDiv.js\");\n");
        fwrite($fh, "cat(\"var freqData=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(new_x)[1])){  \n");
        fwrite($fh, "if(!is.na(coords[rownames(new_x)[i],1]) && !is.na(coords[rownames(new_x)[i],2])) {\n");
        fwrite($fh, "  cat(paste(\"{Samples:\'\",rownames(new_x)[i],\"\',\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"freq:{\",paste(paste(names,\":\",new_x[i,],sep=\"\"),collapse=\",\"),\"},\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"MDS:{\",paste(paste(colnames(coords),coords[rownames(new_x)[i],],sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\"));\n");
        fwrite($fh, "  if(i!=dim(new_x)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\\n\");\n");

        fwrite($fh, "cat(\"var legendDiv=[\\n\");\n");
        fwrite($fh, "labels <- as.data.frame(c(\"Up\",\"Down\"));\n");

        fwrite($fh, "rownames(labels) <- labels[,1];\n");
        fwrite($fh, "for (i in (1:length(plottest\$legend\$bottom\$args\$key\$text[[1]]))){  \n");
        fwrite($fh, "legend<-gsub(\"\\\[\",\"\",gsub(\"\\\(\",\"\",gsub(\"\\\]\",\"\",gsub(\"\\\)\",\"\",plottest\$legend\$bottom\$args\$key\$text[[1]][i]))));\n");
        fwrite($fh, "legend2 <- as.data.frame(strsplit(legend, \",\"));\n");
        fwrite($fh, "rownames(legend2) <- labels[,1];\n");
        fwrite($fh, "legend2<- t(legend2);\n");

        fwrite($fh, "cat(paste(\"{fact:{\",paste(paste(rownames(labels),legend2,sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\") );\n");
        fwrite($fh, "if(i!=length(plottest\$legend\$bottom\$args\$key\$text[[1]])){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\\n\");\n");
        fwrite($fh, "cat(\"var indices=[\\n\");\n");
        fwrite($fh, "for (i in (1:length(sub_points_SPDF\$$this->column_select))){  \n");
        fwrite($fh, "if(!is.na(coords[rownames(new_x)[i],1]) && !is.na(coords[rownames(new_x)[i],2])) {\n");
        fwrite($fh, "cat(paste(\"{fact:\",paste(paste(sub_points_SPDF\$".$$this->column_select."[i],sep=\":\"),collapse=\",\"),\"}\\n\",sep=\"\"))     ;\n");
        fwrite($fh, "if(i!=length(sub_points_SPDF\$$this->column_select)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\");\n");
        fwrite($fh, "cat(\"var indiceslabel=[\\n\");\n");
        fwrite($fh, "cat(paste(\"{fact:\\\"\",paste(paste(\"$$this->column_select\",sep=\":\"),collapse=\",\"),\"\\\"}\\n\",sep=\"\")) ;\n");
        fwrite($fh, "cat(\"];\n\");\n");

        fwrite($fh, "sink();\n");

        fclose($fh);
        // Build the bash script
        if (!($fh2 = fopen($this->job_folder . "/$this->job_id.pbs", "w"))) {
            throw new \Exception("Unable to open file $this->job_folder/$this->job_id.pbs");
        }

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $this->job_id\n");
        fwrite($fh2, "#PBS -d $this->remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $this->job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $this->remote_job_folder/$this->job_id.R > $this->remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);
    }
}
