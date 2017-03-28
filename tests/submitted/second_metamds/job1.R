library(vegan);
library(ecodist);
# replace missing data with 0;
# fourth root or any other transformation here, excluding first column with taxon names;
#transpose the matrices, bcdist needs rows as samples;
# calculate bray curtis for all;
mat1 <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/Macrobenthos-Classes-Adundance.csv", header = TRUE, sep=",",row.names=1);
mat1[is.na(mat1)]<-0;
mat1_2 <- sqrt(sqrt(mat1));
mat1_tr <- t(mat1_2);
bc1 <-bcdist(mat1_tr);
mat2 <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/Macrobenthos-Crustacea-Adundance.csv", header = TRUE, sep=",",row.names=1);
mat2[is.na(mat2)]<-0;
mat2_2 <- sqrt(sqrt(mat2));
mat2_tr <- t(mat2_2);
bc2 <-bcdist(mat2_tr);
mat3 <- read.table("/home/rvlab/jobs2/demo@gmail.com/job1/Macrobenthos-Femilies-Adundance.csv", header = TRUE, sep=",",row.names=1);
mat3[is.na(mat3)]<-0;
mat3_2 <- sqrt(sqrt(mat3));
mat3_tr <- t(mat3_2);
bc3 <-bcdist(mat3_tr);
#create an empty matrix to fill in the correlation coefficients;
bcs <- matrix(NA, ncol=3, nrow=3);
combs <- combn(1:3, 2);
for (i in 1:ncol(combs) ) {
bc1_t <- paste("bc",combs[1,i],sep="");
bc2_t <- paste("bc",combs[2,i],sep="");
bcs[combs[1,i],combs[2,i]] <- cor(get(bc1_t), get(bc2_t), method="spearman");
}
bcs <- t(bcs)
x <- c("Macrobenthos-Classes-Adundance.csv");
x <- append(x, "Macrobenthos-Crustacea-Adundance.csv");
x <- append(x, "Macrobenthos-Femilies-Adundance.csv");
colnames(bcs) <-x;
rownames(bcs) <-x;
#transform the matrix into a dissimlarity matrix of format "dis";
dist1 <- as.dist(bcs, diag = FALSE, upper = FALSE);
#dist2 <- as.dist(NA);
dist2<- 1-dist1;
#run the mds;
mydata.mds<- metaMDS(dist2,  k = 2, trymax = 20,distance="euclidean");
save(dist2, ascii=TRUE, file = "/home/rvlab/jobs2/demo@gmail.com/job1/dist_2nd_stage.csv");
png('legend.png',height = 700,width=350)
plot(mydata.mds, type = "n",ylab="",xlab="",yaxt="n",xaxt="n",bty="n")
n<- length(x);
rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);
labels <- rain;
legend("topright", legend=x, col=labels, pch = 16);
dev.off()
#plot the empty plot;
png('rplot.png',height = 600,width=600)
plot(mydata.mds, type="n");
par(mar=c(5.1, 8.1, 4.1, 8.1), xpd=TRUE);
#add the points for the stations, blue with red circle;
points(mydata.mds, display = c("sites", "species"), cex = 1.8, pch=19, col=labels);
# add the labels for the stations;
text(mydata.mds, display = c("sites", "species"), cex = 1.0 , pos=3 );
dev.off()
#alternative plotting:;
#ordipointlabel(mydata.mds, display ="spec");
#points(mydata.mds, display = "spec", cex = 1.0, pch=20, col="red", type="t"');
#alternative plotting - allows to drag the labels to a better position and then export the graphic as EPS;
#orditkplot(mydata.mds) ;
print("summary")
mydata.mds;
