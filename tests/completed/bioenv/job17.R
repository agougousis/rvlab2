library(vegan);
ENV <- read.table("/home/rvlab/jobs2/demo@gmail.com/job17/softLagoonEnv.csv",header = TRUE, sep=",",row.names=1);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job17/softLagoonAbundance.csv", header = TRUE, sep="," ,row.names=1);
mat <- t(mat);
otu.ENVFACT.bioenv <- bioenv(mat,ENV,method= "spearman",index = "euclidean",upto=2,trace=FALSE);
print("summary")
otu.ENVFACT.bioenv
