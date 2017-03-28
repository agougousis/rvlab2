rm(list = ls())

# Initial Parameters given by the user.
args <- commandArgs(trailingOnly = TRUE)
datasetName <- args[1] # The name of the community's csv file.
transposition <- as.logical(args[2]) # Choose to transpose the matrix (TRUE) or not (FALSE).
factorsetName <- args[3] # The name of the factor's csv file.
SelectFactor <- as.numeric(args[4]) # Choose Factor.
datasetPath <- args[5] # The directory (path) where the dataset is stored.
permutations <- as.numeric(args[6])
trace <- as.logical(args[7])

setwd(datasetPath)

comm <- read.csv(datasetName, header = TRUE, row.names = 1, sep = ",")
group <- read.csv(factorsetName, header = TRUE, row.names = 1, sep = ",")

# Transpose community matrix.
if (transposition) {
  comm <- t(comm)#; mt <- "comm ~ group[, 1]"
} else if (transposition != TRUE) {
  comm <- comm# ; mt <- "comm ~ group[, 1]"
} else {
  stop("Wrong choice. For matrix's transposition choose TRUE else FALSE.")
}

Ft <- ncol(group)

# Check if the arguments are greater than Factor's columns.
if (((SelectFactor <= 0) || (SelectFactor > Ft))) {
  stop("Wrong choice of Factors, transcends available Factors choices.")
} else {
  group <- group[, SelectFactor]
}

if((permutations < 0) || is.numeric(permutations) != TRUE) {
  stop("Negative permutations number. Use non-negative values.")
}


# Load libraries.
library(vegan)
library(pbdMPI, quiet = TRUE)

init()

# Start the clock.
fullProgramTimer = proc.time()

# TP: Get the size of the cluster.
processors <- comm.size()
myrank <- comm.rank()

parallel = getOption("mc.cores")

if (any(rowSums(comm, na.rm = TRUE) == 0)) 
  warning("you have empty rows: results may be meaningless")

pfun <- function(x, comm, comp, i, contrp) {
  groupp <- group[perm[x, ]]
  ga <- comm[groupp == comp[i, 1], , drop = FALSE]
  gb <- comm[groupp == comp[i, 2], , drop = FALSE]
  n.a <- nrow(ga)
  n.b <- nrow(gb)
  for (j in seq_len(n.b)) {
    for (k in seq_len(n.a)) {
      mdp <- abs(ga[k, , drop = FALSE] - gb[j, , drop = FALSE])
      mep <- ga[k, , drop = FALSE] + gb[j, , drop = FALSE]
      contrp[(j - 1) * n.a + k, ] <- mdp/sum(mep)
    }
  }
  colMeans(contrp)
}

getPermuteMatrix <- function(perm, N,  strata = NULL) {
  if (length(perm) == 1) {
    perm <- how(nperm = perm) 
  }

  if (!missing(strata) && !is.null(strata)) {
    if (inherits(perm, "how") && is.null(getBlocks(perm)))
      setBlocks(perm) <- strata
  }

  if (inherits(perm, "how"))
    perm <- shuffleSet(N, control = perm)
	
  if (is.null(attr(perm, "control")))
    attr(perm, "control") <-
    structure(list(within=list(type="supplied matrix"),
                   nperm = nrow(perm)), class = "how")
  perm
}

comm <- as.matrix(comm)
comp <- t(combn(unique(as.character(group)), 2))
outlist <- NULL
P <- ncol(comm)
nobs <- nrow(comm)
perm <- getPermuteMatrix(permutations, nobs)
if (ncol(perm) != nobs) 
  stop(gettextf("'permutations' have %d columns, but data have %d rows", 
                ncol(perm), nobs))
nperm <- nrow(perm)

if (nperm > 0) # TRUE
  perm.contr <- matrix(nrow = P, ncol = nperm)
if (is.null(parallel)) # TRUE 
  parallel <- 1
hasClus <- inherits(parallel, "cluster")
isParal <- hasClus || parallel > 1
isMulticore <- .Platform$OS.type == "unix" && !hasClus
if (isParal && !isMulticore && !hasClus) { # FALSE
  parallel <- makeCluster(parallel)
}

i <- 1
mainCalcTimer = proc.time()

group.a <- comm[group == comp[i, 1], , drop = FALSE]
group.b <- comm[group == comp[i, 2], , drop = FALSE]
n.a <- nrow(group.a)
n.b <- nrow(group.b)
contr <- matrix(ncol = P, nrow = n.a * n.b)
for (j in seq_len(n.b)) {
  for (k in seq_len(n.a)) {
    md <- abs(group.a[k, , drop = FALSE] - group.b[j, 
                                                   , drop = FALSE])
    me <- group.a[k, , drop = FALSE] + group.b[j, 
                                               , drop = FALSE]
    contr[(j - 1) * n.a + k, ] <- md/sum(me)
  }
}
average <- colMeans(contr)	


if (nperm > 0) {
  if (trace) # FALSE
    cat("Permuting", paste(comp[i, 1], comp[i, 2], 
                           sep = "_"), "\n")
  
  contrp <- matrix(ncol = P, nrow = n.a * n.b)

  step1 <- floor(nperm/processors)
  step2 <- nperm - step1*(processors - 1)
  
  if (myrank != (processors - 1)) {
    p2 <- sapply(((myrank*step1) + 1):((myrank + 1)*step1), function(d) pfun(d, comm, comp, i, contrp))
    p.partial <- rowSums(apply(p2, 2, function(x) x >= average))
  } else {
    p2 <- sapply((nperm - step2 + 1):nperm, function(d) pfun(d, comm, comp, i, contrp))
    p.partial <- rowSums(apply(p2, 2, function(x) x >= average))
  }
} else {
  p <- NULL
}

# p1 <- gather(p2)
p.all <- gather(p.partial)

if(myrank == 0) {
#   p3 <- NULL
  p1 <- 0
  for(j in 1:processors){
#     p3 <- cbind(p3, p1[[j]])
    p1 <- p1 + p.all[[j]]
  }
  p.all <- (p1 + 1)/(nperm + 1); rm(p1)
}

comm.print(length(p.all))

comm.print("permutations", rank.print = 0)
comm.print(permutations, rank.print = 0)

overall <- sum(average)
sdi <- apply(contr, 2, sd)
ratio <- average/sdi
ava <- colMeans(group.a)
avb <- colMeans(group.b)
ord <- order(average, decreasing = TRUE)
cusum <- cumsum(average[ord]/overall)
out <- list(species = colnames(comm), average = average, 
             overall = overall, sd = sdi, ratio = ratio, ava = ava, 
             avb = avb, ord = ord, cusum = cusum, p = p.all)
outlist[[paste(comp[i, 1], "_", comp[i, 2], sep = "")]] <- out

if (isParal && !isMulticore && !hasClus) # FALSE#
   stopCluster(parallel)
attr(outlist, "permutations") <- nperm
attr(outlist, "control") <- attr(perm, "control")
class(outlist) <- "simper"
outlist

# Stop the clock
comm.print("Time for each processor to perform the main calculation loop",rank.source = 0)
comm.print(proc.time() - mainCalcTimer, all.rank=T)

comm.print("Full program execution time for each processor", rank.source = 0)
comm.print(proc.time() - fullProgramTimer, all.rank = T)

comm.print("Size of the final result (Mb)", rank.source = 0)
comm.print(object.size(outlist)/(1024^2), all.rank = T)

finalize()
