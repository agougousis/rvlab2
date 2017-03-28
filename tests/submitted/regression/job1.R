library(stats);
library(vegan);
fact <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonEnv.csv", row.names=1, header = TRUE, sep=",");
attach(fact);
fit<-lm(maximumDepthInMeters~Temp);
png('rplot.png')
plot(maximumDepthInMeters~Temp)
abline(fit, col="red")
dev.off()
png('rplot2.png')
layout(matrix(c(1,2,3,4),2,2))
plot(fit)
dev.off()
summary(fit);
