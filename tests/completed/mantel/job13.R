library(vegan);
dist1 <- get(load("/home/rvlab/jobs2/demo@gmail.com/job13/vegdist_job12.csv"));
dist2 <- get(load("/home/rvlab/jobs2/demo@gmail.com/job13/vegdist_job12.csv"));
print("summary")
mantel.out <- mantel(dist1,dist2, method = "spearman",permutations = 999)
mantel.out
