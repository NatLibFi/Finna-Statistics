# Finna Statistical Analysis #

This project contains tools and utilities related to statistical analysis of
Finna.

## Included utilities ##

### Search index count statistics ###

Usage `php src/IndexCounts/stats.php <settings-file> <output-file>`

This script gathers various numbers of indexed entries.

The `<settings-file>` path should point to a json file that contains the search
settings. See `src/IndexCounts/settings.json` for default configuration.

The results will be appended as CSV rows to the provided `<output-file>` path.
Each result row contains the fields `date` and `query` appended by the number
of entries for each filter set.

#### Configuration ####

The `settings.json` file contains following settings:

   * `url` : That path to the polled Solr index.
   * `filters` : List of filters that can be used in queries
   * `filterSets` : List of filter combinations that will be applied to each
      query
   * `queries` : List of custom queries performed in addition sector and format
     queries

### User account count statistics ###

Usage `php src/UserCounts/user_counts.php <settings-file> <output-file>`

This script counts the number of user accounts per organisation and per
authentication method.

The `<settings-file>` path should point to a json file that contains the
counting settings. See `src/UserCounts/settings.json` for sample configuration.

The results will be appended as CSV rows to the provided `<output-file>` path.
Each result row contains the fields `date`, `organisation` and combined `total`
number of accounts appended by the number of accounts for each authentication
method.

To get the list of different authentication methods in the database, you can run
the command:

`php src/UserCounts/list_methods.php <settings-file>`

#### Configuration ####

The `settings.json` file contains the following settings:

  * `username` : The database username used to access the database
  * `password` : The password for the database user
  * `hostname` : The database hostname
  * `database` : The name of the database
  * `table` : Name of the table that contains the user data
  * `maxAge` : If included, the results contain additional rows for each
    institution, which include the number of active accounts. The value
    indicates the maximum number of seconds since last login.
  * `authMethods` : List of auth methods that are included in the statistics.
    Empty array can be used for all methods. (The order of columns in the
    results in not guaranteed, however).
  * `institutions` : List of institutions included in the statistics. Empty
    array can be used for all institutions.
