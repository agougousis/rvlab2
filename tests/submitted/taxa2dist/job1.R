library(vegan);
agg <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonAbundance.csv", header = TRUE, sep=",");
taxdis <- taxa2dist(agg, varstep=FALSE, check=TRUE);
save(taxdis, ascii=TRUE, file = "/home/rvlab/jobs2/demo@gmail.com/job1/taxadis.csv");
summary(taxdis);
