x <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonAbundance.csv", header = TRUE, sep=",",row.names=1);
x <- t(x);
library(vegan);
mod <- radfit(x)
png('rplot.png')
plot(mod)
dev.off()
summary(mod);
