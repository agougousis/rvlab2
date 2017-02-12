library(vegan);
agg <- read.table("/home/rvlab/jobs/xayate@yahoo.com/job2575/softLagoonAggregation.csv", header = TRUE, sep=",");
taxdis <- taxa2dist(agg, varstep=FALSE, check=TRUE);
save(taxdis, ascii=TRUE, file = "/home/rvlab/jobs/xayate@yahoo.com/job2575/taxadis.csv");
summary(taxdis);
