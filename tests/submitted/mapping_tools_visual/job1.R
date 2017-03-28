library(vegan);
x <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonAbundance.csv", header = TRUE, sep=",",row.names=1);
coords <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonCoordinatesTransposedLong-Lat.csv",header = TRUE, sep=",",row.names=1);
x <- t(x);
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
colnames(coords)[1] <- "Longitude";
colnames(coords)[2] <- "Latitude";
sink("dataMap.js");
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
sink();
