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
