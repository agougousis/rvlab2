library(vegan);
library(stringr);
library(maptools);
library(sp);
library(plyr);
library(dplyr);
library(tidyr);
x <- read.table("/home/rvlab/jobs2/demo@gmail.com/job48/osd2014-16s-formated.csv", header = TRUE, sep=",",row.names=1);
coords <- read.table("/home/rvlab/jobs2/demo@gmail.com/job48/16S-ODV-input-corrected-dec15-formatted-coords.csv",header = TRUE, sep=",",row.names=1);
colnames(coords) <- gsub("\\.","_",gsub(" ","_",colnames(coords)));
colnames(coords)[1] <- "Longitude";
colnames(coords)[2] <- "Latitude";
indices <- read.table("/home/rvlab/jobs2/demo@gmail.com/job48/16S-ODV-input-corrected-dec15-formatted.csv",header = TRUE, sep=",",row.names=1);
x <- t(x);
tkml <- getKMLcoordinates(kmlfile="world_3.kml", ignoreAltitude=T);
#Create polygon from coordinates;
p1<-list();
for (i in 1:length(tkml))  p1[[i]] <- Polygon(tkml[[i]])   #loop nodig
#Create Polygon class;
p2 = Polygons(p1, ID="z");
#Create Spatial Polygons class en referentie systeem is nodig;
p3= SpatialPolygons(list(p2),proj4string=CRS("+proj=longlat +datum=WGS84 +ellps=WGS84 +towgs84=0,0,0"));
polys<-list("sp.polygons", p3, fill = "lightgreen");
sub_points<-coords%>%
select(Longitude,Latitude);
sub_points_coords<-sub_points[,1:2];
sub_points_SP<-SpatialPoints(sub_points_coords);
sub_points_SPDF<-SpatialPointsDataFrame(sub_points_coords, indices);
#Set color set to be used for classes of data  ;
colorset6<-c("#FFFF00", "#FFCC00", "#FF9900", "#FF6600", "#FF3300", "#FF0000");
plottest <- spplot(sub_points_SPDF, zcol=c("Shannon.Index..ln...H."), xlab="",
scales=list(draw = TRUE), sp.layout=list(polys), cuts = 6, col.regions=colorset6,xlim=c(-180,180),ylim=c(-80,80),par.settings = list(panel.background=list(col="lightblue")));
x<-x/rowSums(x);
x<-x[,order(colSums(x),decreasing=TRUE)];
#Extract list of top N Taxa;
N<-21;
taxa_list<-colnames(x)[1:N];
#remove "__Unknown__" and add it to others;
taxa_list<-taxa_list[!grepl("__Unknown__",taxa_list)];
N<-length(taxa_list);
new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));
names<-gsub("\\.","_",gsub(" ","_",colnames(new_x)));
rownames(new_x) <- gsub("\\.","-",gsub(" ","_",rownames(new_x)));
sink("dataMapDiv.js");
cat("var freqData=[\n");
for (i in (1:dim(new_x)[1])){  
if(!is.na(coords[rownames(new_x)[i],1]) && !is.na(coords[rownames(new_x)[i],2])) {
  cat(paste("{Samples:\'",rownames(new_x)[i],"\',",sep=""));
  cat(paste("freq:{",paste(paste(names,":",new_x[i,],sep=""),collapse=","),"},",sep=""));
  cat(paste("MDS:{",paste(paste(colnames(coords),coords[rownames(new_x)[i],],sep=":"),collapse=","),"}}\n",sep=""));
  if(i!=dim(new_x)[1]){cat(",")};
};
};
cat("];\n\n");
cat("var legendDiv=[\n");
labels <- as.data.frame(c("Up","Down"));
rownames(labels) <- labels[,1];
for (i in (1:length(plottest$legend$bottom$args$key$text[[1]]))){  
legend<-gsub("\\[","",gsub("\\(","",gsub("\\]","",gsub("\\)","",plottest$legend$bottom$args$key$text[[1]][i]))));
legend2 <- as.data.frame(strsplit(legend, ","));
rownames(legend2) <- labels[,1];
legend2<- t(legend2);
cat(paste("{fact:{",paste(paste(rownames(labels),legend2,sep=":"),collapse=","),"}}\n",sep="") );
if(i!=length(plottest$legend$bottom$args$key$text[[1]])){cat(",")};
};
cat("];\n\n");
cat("var indices=[\n");
for (i in (1:length(sub_points_SPDF$Shannon.Index..ln...H.))){  
if(!is.na(coords[rownames(new_x)[i],1]) && !is.na(coords[rownames(new_x)[i],2])) {
cat(paste("{fact:",paste(paste(sub_points_SPDF$Shannon.Index..ln...H.[i],sep=":"),collapse=","),"}\n",sep=""))     ;
if(i!=length(sub_points_SPDF$Shannon.Index..ln...H.)[1]){cat(",")};
};
};
cat("];\n");
cat("var indiceslabel=[\n");
cat(paste("{fact:\"",paste(paste("Shannon.Index..ln...H.",sep=":"),collapse=","),"\"}\n",sep="")) ;
cat("];
");
sink();
