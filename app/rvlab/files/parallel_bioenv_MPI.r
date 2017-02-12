rm(list = ls())

# Initial Parameters given by the user.
args <- commandArgs(trailingOnly = TRUE)
dataMatrix <- args[1]
transposition <- as.logical(args[2])
Envdataset <- args[3]
datasetpath <- args[4]
method <- args[5]
index <- args[6]
upto <- as.numeric(args[7])
trace <- as.logical(args[8])
metric <- args[9]

library(vegan)
library(cluster)

setwd(datasetpath)

comm <- read.csv(dataMatrix, header = TRUE, row.names = 1, sep = ",")
env <- read.csv(Envdataset, header = TRUE, row.names = 1, sep = ",")

if(transposition) {
  dat <- t(comm)
} else if (tranposition != TRUE) {
  dat <- comm
} else {
  stop("Wrong choice. Choose to transpose the matrix (TRUE) or not (FALSE).")
}

if((upto <= 0) || (upto > ncol(env))) {
  stop("Wrong choice of upto parameter, transcends available upto choices.")
}

env <- env[1:nrow(comm), ]

partial = NULL
parallel = getOption("mc.cores")

# load libraries
library(pbdMPI, quiet = TRUE)

# Start the clock
fullProgramTimer <- proc.time()

init()

# TP: Get the size of the cluster
processors <- comm.size()
myrank <- comm.rank()

if (is.null(partial)) { # TRUE
  corfun <- function(dx, dy, dz, method) {
    cor(dx, dy, method = method)
  }
} else {
  corfun <- function(dx, dy, dz, method) {
    rxy <- cor(dx, dy, method = method)
    rxz <- cor(dx, dz, method = method)
    ryz <- cor(dy, dz, method = method)
    (rxy - rxz * ryz)/sqrt(1 - rxz * rxz)/sqrt(1 - ryz * 
                                                 ryz)
  }
}

if (!is.null(partial)) { # FALSE 
  partpart <- deparse(substitute(partial))
} else partpart <- NULL
if (!is.null(partial) && !inherits(partial, "dist")) # FALSE 
  partial <- dist(partial)
if (!is.null(partial) && !pmatch(method, c("pearson", "spearman"), 
                                 nomatch = FALSE)) # FALSE
  stop("method ", method, " invalid in partial bioenv")
  
# Main calculation start
mainCalcTimer <- proc.time()

 n <- ncol(env)
ntake <- 2^n - 1
ndone <- 0
if (n > 8 || trace) {
  if (upto < n) 
    cat("Studying", nall <- sum(choose(n, 1:upto)), "of ")
  cat(ntake, "possible subsets (this may take time...)\n")
  flush.console()
}
if (metric == "euclidean") {
  x <- scale(env, scale = TRUE)
  distfun <- function(x) dist(x)
} else if (metric == "mahalanobis") {
  x <- as.matrix(scale(env, scale = FALSE))
  distfun <- function(x) dist(veganMahatrans(x))
} else if (metric == "gower") {
  x <- env
  distfun <- function(x) daisy(x, metric = "gower")
} else if (metric == "manhattan") {
  x <- decostand(env, "range")
  distfun <- function(x) dist(x, "manhattan")
} else {
  stop("unknown metric")
}


best <- list()

if (inherits(comm, "dist")) { 
  comdis <- comm
  index <- attr(comdis, "method")
  if (is.null(index)) 
    index <- "unspecified"
} else if (is.matrix(comm) && nrow(comm) == ncol(comm) && isTRUE(all.equal(comm, 
                                                                         t(comm)))) { # FALSE
  comdis <- as.dist(comm)
  index <- "supplied square matrix"
} else {
  comdis <- vegdist(comm, method = index)
}

if(processors >= upto) {
   processors <-  upto
}

step <- floor(upto/processors)
step2 <- upto - (processors - 1)*step

if (myrank != (processors - 1)) {
  for (i in (myrank*step + 1):((myrank + 1)*step)) {
    if (trace) { # FALSE
      nvar <- choose(n, i)
      cat("No. of variables ", i, ", No. of sets ", nvar, 
          "...", sep = "")
      flush.console()
    }
    sets <- t(combn(1:n, i))
    if (!is.matrix(sets)) 
      sets <- as.matrix(t(sets))
    est <- numeric(nrow(sets))
    for (j in 1:nrow(sets)) est[j] <- corfun(comdis, dist(x[, 
                                                            sets[j, ]]), partial, method = method)


#    best[[i]] <- list(best = sets[which.max(est), ], est = max(est))

    best[[i - myrank*step]] <- list(best = sets[which.max(est), ], est = max(est))
    
#    best[is.na(best)] <- 0

    if (trace) {
      ndone <- ndone + nvar
      cat(" done (", round(100 * ndone/ntake, 1), "%)\n", 
          sep = "")
      flush.console()
    }
  }
} else {
  for (i in (upto - step2 + 1):upto) {
    if (trace) { # FALSE
      nvar <- choose(n, i)
      cat("No. of variables ", i, ", No. of sets ", nvar, 
          "...", sep = "")
      flush.console()
    }
    sets <- t(combn(1:n, i))
    if (!is.matrix(sets)) 
    sets <- as.matrix(t(sets))
    est <- numeric(nrow(sets))
    for (j in 1:nrow(sets)) est[j] <- corfun(comdis, dist(x[, 
                                                          sets[j, ]]), partial, method = method)
#    best[[i]] <- list(best = sets[which.max(est), ], est = max(est))

    best[[i - upto + step2]] <- list(best = sets[which.max(est), ], est = max(est))
    
#    best[is.na(best)] <- 0

    if (trace) {
      ndone <- ndone + nvar
      cat(" done (", round(100 * ndone/ntake, 1), "%)\n", 
          sep = "")
      flush.console()
    }
  }
}

best.all <- gather(best)
best.este <- list()

if(myrank == 0){
  best.este <- list()
  j = length(best.all[[1]])
  for(i in 1:j) {
    best.este[[i]] <- best.all[[1]][[i]]
  }
for(k in 2:length(best.all)) {
    j <- length(best.all[[k]])
    for(i in 1:j){
      best.este[[i]] <- best.all[[k]][[i]]
    }
  }
}
	
whichbest <- which.max(lapply(best.este, function(tmp) tmp$est))
out <- list(names = colnames(env), method = method, index = index, 
            metric = metric, upto = upto, models = best.este, whichbest = whichbest, 
            partial = partpart, x = x, distfun = distfun)
  	

out$call <- as.call(list(quote(bioenv), quote(comm), quote(env), 
                   method = method, index = index, metric = metric, 
                   upto = upto, trace = trace, partial = partial))

out$call[[1]] <- as.name("bioenv")
class(out) <- "bioenv"
comm.print(out)

# Stop the clock
comm.print("Full program execution time for each processor", rank.source = 0)
comm.print(proc.time() - fullProgramTimer, all.rank = T)

comm.print("Full program execution time for each processor", rank.source = 0)
comm.print(proc.time() - mainCalcTimer, all.rank = T)

comm.print("Names:", rank.source = 0)
comm.print(out[[1]], rank.source = T)

comm.print("Method:", rank.source = 0)
comm.print(out[[2]], rank.source = T)

comm.print("Index", rank.source = 0)
comm.print(out[[3]], rank.source = T)

comm.print("Metric", rank.source = 0)
comm.print(out[[4]], rank.source = T)

comm.print("upto:", rank.source = 0)
comm.print(out[[5]], rank.source = T)

comm.print("Model:", rank.source = 0)
comm.print(out[[6]], rank.source = T)

comm.print("whichbest:", rank.source = 0)
comm.print(out[[7]], rank.source = T)

comm.print("trace:", rank.source = 0)
comm.print(out[[8]], rank.source = T)

comm.print("x:", rank.source = 0)
comm.print(out[[9]], rank.source = T)

comm.print("Distance function:", rank.source = 0)
comm.print(out[[10]], rank.source = T)

comm.print("Call:", rank.source = 0)
comm.print(out[[11]], rank.source = 0)

comm.print("Final result's size", rank.source = 0)
comm.print((object.size(out)/(1024^2)), rank.source = T)

finalize()
