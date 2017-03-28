library(vegan);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job35/softLagoonAbundance.csv", header = TRUE, sep=",",row.names=1);
mat <- t(mat);
ENV <- read.table("/home/rvlab/jobs2/demo@gmail.com/job35/softLagoonFactors.csv", header = TRUE, sep="," ,row.names=1);
labels <- as.factor(ENV$Country);
otu.nmds <- metaMDS(mat,distance="euclidean");
par(xpd=TRUE);
png('legend.png',height = 700,width=350)
plot(otu.nmds, type = "n",ylab="",xlab="",yaxt="n",xaxt="n",bty="n")
legend("topright", legend=unique(ENV$Country), col=unique(labels), pch = 16);
dev.off()
png('rplot.png',height = 600,width=600)
plot(otu.nmds, type = "n")
points(otu.nmds, col = labels, pch = 16,cex = 1.7);
dev.off()
print("summary")
otu.nmds;
