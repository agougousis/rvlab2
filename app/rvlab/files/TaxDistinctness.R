# taxDistinctness.R
#   This is a wrapper script for taxa2dist/taxondive. Usage:
#     Rscript taxDistinctness.R abundanceDataMatrix.csv taxonomicRelationshipData.csv tdValuesOutputFileName.csv funnelImage.jpg
#   csv files are expected to have a header

rm(list=ls())
library(vegan)
#setwd(workdir)

args <- commandArgs(trailingOnly=TRUE)
# trailingOnly=TRUE means that only your arguments are returned, check: print(commandsArgs(trailingOnly=FALSE))
#print(args)
#workdir <- args[1]
dataIn <- args[1]
taxRelDataIn <- args[2]
dataOut <- args[3]
funnelPicOut <-args[4]

agg<- read.table( taxRelDataIn, header = TRUE, sep=",")
agg2<-data.frame(agg[,-1])
rownames(agg2) <- agg[,1]
comm<- read.table(dataIn, header = TRUE, sep=",")
comm[is.na(comm)]<-0
comm2<-t(comm[,-1])
comm2<-data.frame(comm2)
names(comm2) <- comm[,1]
taxdis <- taxa2dist(agg2, varstep=TRUE)
resu <- taxondive(comm2, taxdis, match.force = FALSE)
labels<-names(comm[,-1])
maxSpecNr <- max(resu$Species)
minSpecNr <- min(resu$Species)
tdvalues <- matrix(ncol=ncol(comm[,-1]),nrow=2)
colnames(tdvalues) <-labels
rownames(tdvalues) <- c("Delta+", "Lambda+")
tdvalues[1,] <- resu$Dplus
tdvalues[2,] <- resu$Lambda
write.csv(tdvalues, dataOut)
jpeg(file=funnelPicOut, res=600, width=20, height=20, units="cm", pointsize=7)
plot(resu, xlim=c(minSpecNr-2, maxSpecNr+2))
text(resu$Species+0.5,resu$Dplus,labels)
dev.off()
