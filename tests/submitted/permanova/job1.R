library(vegan);
ENV <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonFactors.csv",header = TRUE, sep=",",row.names=1);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonAbundance.csv", header = TRUE, sep="," ,row.names=1);
mat <- t(mat);
otu.ENVFACT.adonis <- adonis(mat ~ ENV$Country,data=ENV,permutations = 999,distance = "euclidean");
print("summary")
otu.ENVFACT.adonis
