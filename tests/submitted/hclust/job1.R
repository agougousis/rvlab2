library(vegan);
library(dendextend);
dist <- get(load("/home/rvlab/jobs2/demo@gmail.com/job1/vegdist_job12.csv"));
clust.average <- hclust(dist, method = "ward.D")
dend <- as.dendrogram(clust.average);
png('rplot.png',height = 600, width = 600)
plot(dend)
dev.off()
summary(clust.average);
