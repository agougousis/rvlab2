library(vegan);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonAbundance.csv", row.names=1, header = TRUE, sep=",");
ENV <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonFactors.csv",header = TRUE, sep=",",row.names=1);
mat <- t(mat);
vare.cca <- cca(mat ~ Country+Location, data=ENV);
png('rplot.png',height=600,width=600)
plot(vare.cca);
summary(vare.cca);
dev.off()
