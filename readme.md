# R vLab - LifeWatchGreece Project

## Requirements

* Apache 
  * rewrite module
* PHP >= 5.6.4
  * OpenSSL PHP Extension
  * Mcrypt PHP Extension 
  * PDO PHP Extension
  * Mbstring PHP Extension
  * XML PHP Extension
  * Tokenizer PHP Extension 
  * cURL PHP Extension  
  * short_tags shound be enabled in php.ini  
* MySQL >= 5.0

## Installation

#### Database Schema and configuration

R vLab requires a MySQL database with a schema described in schema.sql file in the documentation directory. 
This file does not contain only the schema but also a few basic settings needed by R vLab. 

The credentials that are used for database connection should be defined in a .env file inside the 
document root directory. A sample .env file hould probably looks like this:

APP_ENV=production
APP_KEY=base64:G01sJAF8rt0vl5SmBHw3wb8VpPD9tLIy7/e33/XoHko=
APP_DEBUG=false
APP_LOG_LEVEL=debug
APP_URL=http://myapp.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rvlabdb
DB_USERNAME=rvlabdbuser
DB_PASSWORD=mypassword

JOBS_PATH=/mnt/cluster/jobs
REMOTE_JOBS_PATH=/home/ruser/jobs
WORKSPACE_PATH=/mnt/cluster/workspace
REMOTE_WORKSPACE_PATH=/home/ruser/workspace

#### File directories

Generally, R vLab uses two separate directories for user files. One for storing jobs and one for storing input files. 
The latter is often named as the 'workspace' directory. Each user has its own sub-directory in these two directories. 
A new directory is created for each job submitted by a user. Assuming that these two folders are:  /.../jobs   and  
/.../workspace , the user area directory structure will look like:

```
/.../jobs
/.../jobs/user1@gmail.com
/.../jobs/user1@gmail.com/job12
/.../jobs/user1@gmail.com/job17
/.../jobs/user2@gmail.com
/.../jobs/user2@gmail.com/job75
/.../jobs/user2@gmail.com/job76

/.../workspace
/.../workspace/user1@gmail.com
/.../workspace/user1@gmail.com/softLagoon.csv
/.../workspace/user2@gmail.com
/.../workspace/user2@gmail.com/softLagoon.csv
```

, where e.g /.../jobs/user1@gmail.com/job12  is a job-folder. A job-folder is created for each new job and contains all the 
necessery files for a job to be executed. The two folders mentioned above are designated to reside on a cluster and should 
get mounted to local directories. So, the web application uses the local paths to read/write, but it also uses the remote 
paths when building the R scripts (because these scripts will be executed remotely). The local and remote paths used in 
production should be defined in the .env file. The respective paths for testing should be defined in the phpunit.xml file.

If each PHP application is executed under a different user (e.g in case PHP-FPM is used), all application files should be 
owned by the relevant user. For the same reason, mounting of cluster directories should take place under this user, so that 
the application can write to the directories.

#### Cron jobs

A single cron job should be added:

* * * * * php /path_to_rvlab_application/artisan schedule:run >> /dev/null 2>&1

and the following three tasks are executed regularly:

`every 1 minute:`  Update the status of every job that has been submitted and its execution has not finished yet (its status is different than creating, failed or completed). The script that accomplishes that task is located at /app/commands/RefreshStatusCommand.php. 

`every 30 minutes:`  Deletes from the file system and the database, all the jobs that have exceeded the  maximum storage time for R vLab (this time is defined by the parameter job_max_storagetime of settings table). The script that accomplishes that task is located at /app/commands/RemoveOldJobsCommand.php. 

`every 30 minutes:` Checks if the total storage space that is available for users is below the security limit. If not, it deletes job folders from users that exceed their personal storage limit. Τ The script that accomplishes that task is located at /app/commands/StorageUtilizationCommand.php. 

These three tasks should be executed by the same user that R vLab web application is executed and so by the user who is owner of the application files.  

#### Authentication

Authentication mechanism can be substituted by providing an alternative authenticator class. The authenticator class should implement the App\Contracts\Authenticator interface and
be placed in the app/Authenticators directory. In order to switch between the available authenticators, just change the bind in the app/Providers/AuthenticatorServiceProvider.php file. 

A default/dummy authenticator has been included and can be used for testing purposes. The user credentials for this authenticator are:

username: demo@gmail.com 
password: oooooo

and are hard-coded in the authenticator.

In order for the default authenticator to work, the following files has been added:

app/Http/Controllers/DefaultController.php
resources/views/default_external_wrapper.php
resources/views/default_internal_wrapper.php
resources/views/login.php

and 3 relevant routes has been added in the routes/web.php

Though not necessery, these files and routes can be removed in case your are
using an alternative authenticator.

## License

The R vLab is open-sourced software licensed under the MIT license.

## Current Code Metrics

This section may not be updated for every commit. 
The following diagrams refer to commit 733bd9dd9fdeb4bb595ff713aca39e088f8e4803 (9 April 2017).

#### Abstraction Instability Chart & Overview Pyramid (PHP Depend)

![pdepend](https://cloud.githubusercontent.com/assets/5471589/24837664/ef3dca2a-1d41-11e7-8469-6fd16891e236.png)

#### Code Coverage diagram (PHPUnit & XDebug)

![coverage](https://cloud.githubusercontent.com/assets/5471589/24837663/df6e9de0-1d41-11e7-9f58-27b7aa2bcfd9.png)

#### Maintainability/Complexity diagram (PhpMetrics tool)

![cyclo](https://cloud.githubusercontent.com/assets/5471589/24837665/ff065c42-1d41-11e7-9239-d587161efe23.png)

The worst maintainability index found is 52.65 