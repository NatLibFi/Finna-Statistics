# Finna Statistical Analysis #

This project contains tools and utilities related to statistical analysis of
Finna.

## Running the script ##

Script can be run by using command `php src/statistics_run.php Argument1 Argument2`.
Multiple arguments can be entered at the same time.
If you want to run a more specific method, please use `Argument1=Method`

All the settings are declared inside `Settings/settings.json` file.
Key is the name of class to declare settings to.
Global settings db holds the data for database connections.
Personal database connection can be declared for an individual setting by adding key db for correct setting.
See comment in `settings.json` for more info.

### Database connection settings ###
The `settings.json` file contains following settings for "db":
  * `username` : The database username used to access the database
  * `password` : The password for the database user
  * `hostname` : The database hostname
  * `database` : The name of the database

### Search index count statistics ###

Usage `php src/statistics_run.php StatsProcessor`

This script gathers various numbers of indexed entries.

The results will be appended as CSV rows to the provided `output setting` in settings.
Each result row contains the fields `date` and `query` appended by the number
of entries for each filter set.

#### Configuration ####

The `settings.json` file contains following settings:
  * `output` : Filepath to output results
  * `url` : That path to the polled Solr index.
  * `filters` : List of filters that can be used in queries
  * `filterSets` : List of filter combinations that will be applied to each
    query
  * `queries` : List of custom queries performed in addition sector and format
    queries

### User account count statistics ###

Usage `php src/statistics_run.php UserCount`

This script counts the number of user accounts per organisation and per
authentication method.

The results will be appended as CSV rows to the provided `output setting` in settings.
Each result row contains the fields `date`, `organisation` and combined `total`
number of accounts appended by the number of accounts for each authentication
method.

To get the list of different authentication methods in the database, you can run
the command:

`php src/statistics_run.php UserCount=listAuthMethods`

#### Configuration ####

The `settings.json` file contains the following settings:

  * `table` : Name of the table that contains the user data
  * `output` : Filepath to output results
  * `maxAge` : If included, the results contain additional rows for each
    institution, which include the number of active accounts. The value
    indicates the maximum number of seconds since last login.
  * `authMethods` : List of auth methods that are included in the statistics.
    Empty array can be used for all methods. (The order of columns in the
    results in not guaranteed, however).
  * `institutions` : List of institutions included in the statistics. Empty
    array can be used for all institutions.

### User list statistics ###

Usage `php src/statistics_run.php UserListCount`

The script returns the amount of lists made by users and how many of those lists
are public.

The results will be appended as CSV rows to the provided `output setting` in settings.
Each result row contains timestamps in format `UTC+0` with fields `total` and 
`public` amount of lists.

### Configuration ###

  * `table` : Name of the table that contains the user list data
  * `output` : Filepath to output results
