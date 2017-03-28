library(vegan);
taxdis <- get(load("/home/rvlab/jobs2/demo@gmail.com/job33/taxadis_job1.csv"));
mat <- read.table("/home/rvlab/jobs2/demo@gmail.com/job33/softLagoonAbundance.csv", header = TRUE, sep="," ,row.names=1);
mat <- t(mat);
taxondive <- taxondive(mat,taxdis,match.force=FALSE);
save(taxondive, ascii=TRUE, file = "/home/rvlab/jobs2/demo@gmail.com/job33/taxondive.csv");
labels <- as.factor(rownames(mat));
n<- length(labels);
rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);
labels <- rain;
png('legend.png',height = 700, width = 350)
plot(mat, type = "n",ylab="",xlab="",yaxt="n",xaxt="n",bty="n")
legend("topright", legend=rownames(mat), col=labels, pch = 16);
dev.off()
png('rplot.png',height = 600, width = 600)
if(min(taxondive$Dplus) < min(taxondive$EDplus-taxondive$sd.Dplus*2)){
plot(taxondive,pch=19,col=labels,cex = 1.7, ylim = c(min(taxondive$Dplus),max(taxondive$sd.Dplus*2+taxondive$EDplus)), xlim = c(min(taxondive$Species),max(taxondive$Species)));
}else if(max(taxondive$Dplus) > max(taxondive$sd.Dplus*2+taxondive$EDplus)){
plot(taxondive,pch=19,col=labels,cex = 1.7, ylim = c(min(taxondive$EDplus-taxondive$sd.Dplus*2),max(taxondive$Dplus)), xlim = c(min(taxondive$Species),max(taxondive$Species)))
}else{
plot(taxondive,pch=19,col=labels,cex = 1.7,xlim = c(min(taxondive$Species),max(taxondive$Species)), ylim = c(min(taxondive$EDplus-taxondive$sd.Dplus*2),max(taxondive$sd.Dplus*2+taxondive$EDplus)))
}
with(taxondive, text(Species-.3, Dplus-1, as.character(rownames(mat)),pos = 4, cex = 0.9))
dev.off()
summary(taxondive);
summary(taxondive);
