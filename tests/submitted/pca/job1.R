library(vegan);
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonAbundance.csv", header = TRUE, sep=",",row.names=1);
mat <- t(mat);
ENV <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/softLagoonFactors.csv", header = TRUE, sep="," ,row.names=1);
labels <- as.factor(ENV$Country);
otu.pca <- rda(mat);
par(xpd=TRUE);
png('/home/rvlab/jobs2/demo@gmail.com/job1/legend.png',height = 700,width=350)
plot(otu.pca, type = "n",ylab="",xlab="",yaxt="n",xaxt="n",bty="n")
abline(h=0,col="white",lty=1,lwd=3);
abline(v=0,col="white",lty=1,lwd=3);
legend("topright", legend=unique(ENV$Country), col=unique(labels), pch = 16);
dev.off()
png('/home/rvlab/jobs2/demo@gmail.com/job1/rplot.png',height = 600,width=600)
plot(otu.pca, type = "n")
points(otu.pca, col = labels, pch = 16,cex = 1.7);
dev.off()
pdf(file='/home/rvlab/jobs2/demo@gmail.com/job1/rplot.pdf',width=10, height=10)
plot(otu.pca, type = "n")
points(otu.pca, col = labels, pch = 16,cex = 1.7);
plot(otu.pca, type = "n",ylab="",xlab="",yaxt="n",xaxt="n",bty="n")
abline(h=0,col="white",lty=1,lwd=3);
abline(v=0,col="white",lty=1,lwd=3);
legend("topright", legend=unique(ENV$Country), col=unique(labels), pch = 16);
dev.off()
print("summary")
otu.pca;
