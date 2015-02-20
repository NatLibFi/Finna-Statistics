# Finna Statistical Analysis #

This project contains tools and utilities related to statistical analysis of
Finna.

## Included utilities ##

### Search index count statistics ###

Usage `php src/IndexCounts/stats.php <settings-file> <output-file>`

This script gathers various numbers of indexed entries.

The `<settings-file>` path should point to a json file that contains the search
settings. See `src/IndexCounts/settings.json` for default configuration.

The output will be appended as CSV rows to the provided `<output-file>` path.