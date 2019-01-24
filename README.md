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

### View statistics ###

Usage `php src/ViewStatistics/get_statistics.php <settings-file> --date=<period> --ids=<Piwik ids> --institution=<institution names> --output=<output-dir> --debug`

This script fetches statistics for views and generates Excel files of them.

The `<settings-file>` path should point to a json file that contains the
statistic settings. See `src/ViewStatistics/settings.json` for sample configuration.

The results for a view will be collected to a Excel spreadsheet document, each Piwik call placed on its own worksheet.

The list of views is collected by traversing the view root directory and checking for each found view that:
- the view is not disabled
- Piwik site id is defined in <view-dir>/local/config/vufind.ini

To list all views run

`php src/ViewStatistics/list_views.php <settings-file>`

#### Configuration ####

The `settings.json` file contains the following settings:

  * `piwik` : Piwik URL and user token
  * `views` : Base directory for all views
  * `statistics` : List of Piwik API calls with the following parameters:
  *                  `method`: Piwik API method name
  *                  `label`:  Worksheet label
  *                  `limit`:  Optional limit for result rows
  *                  `flip`:   Should the table rows and columns be flipped (default false)

#### Startup parameters #####

* `--date` : Report period in YYYY-MM-DD,YYYY-MM-DD format
* `--ids` : Piwik ids to generate reports for (example: --ids=1,2,3)
* `--institutions` : Institution names to generate reports for (example: --institutions=foo.bar)
* `--output` : Path to the output directory where the Excel files are saved
* `--debug` : Print debug info to console
