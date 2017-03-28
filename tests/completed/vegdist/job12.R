library(vegan);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job12/softLagoonAbundance.csv", header = TRUE, sep=",",row.names=1);
mat <- t(mat);
vegdist <- vegdist(mat, method = "euclidean",binary=FALSE, diag=FALSE, upper=FALSE,na.rm = FALSE)
save(vegdist, ascii=TRUE, file = "/home/rvlab/jobs2/demo@gmail.com/job12/vegdist.csv");
summary(vegdist);
