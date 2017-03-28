library(stats);
geo <- read.table("/home/rvlab/jobs2/demo@gmail.com/job30/softLagoonEnv.csv", row.names=1, header = TRUE, sep=",");
aov.ex1<-aov(maximumDepthInMeters~Temp,geo);
png('rplot.png')
boxplot(maximumDepthInMeters~Temp,geo,xlab="Temp", ylab="maximumDepthInMeters")
dev.off()
summary(aov.ex1);
