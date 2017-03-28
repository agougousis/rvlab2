library(vegan);
ENV <- read.table("/home/rvlab/jobs2/demo@gmail.com/job14/softLagoonFactors.csv",header = TRUE, sep=",",row.names=1);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job14/softLagoonAbundance.csv", header = TRUE, sep="," ,row.names=1);
mat <- t(mat);
otu.ENVFACT.simper <- simper(mat,ENV$Location,permutations = 0,trace = FALSE);
print("summary")
otu.ENVFACT.simper
