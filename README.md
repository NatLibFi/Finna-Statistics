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

### User account count statistics ###

Usage `php src/UserCounts/user_counts.php <settings-file> <output-file>`

This script counts the number of user accounts per insitution and per
authentication method.

The `<settings-file>` path should point to a json file that contains the
counting settings. See `src/UserCounts/settings.json` for sample configuration.

The results will be appended as CSV rows to the provided `<output-file>` path.

Due to the fact the authentication methods are retrieved from the user table,
the CSV file will always contain the list of headers before the other statistics
in the output.
