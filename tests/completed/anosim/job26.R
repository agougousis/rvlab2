library(vegan);
ENV <- read.table("/home/rvlab/jobs2/demo@gmail.com/job26/softLagoonFactors.csv",header = TRUE, sep=",",row.names=1);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job26/softLagoonAbundance.csv", header = TRUE, sep="," ,row.names=1);
mat <- t(mat);
otu.ENVFACT.anosim <- anosim(mat,ENV$Country,permutations = 999,distance = "euclidean");
png('rplot.png')
plot(otu.ENVFACT.anosim)
dev.off()
print("summary")
otu.ENVFACT.anosim
