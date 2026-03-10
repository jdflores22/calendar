# Route Fix - Office Index

## Issue
The admin navigation was using `office_index` route which doesn't exist.

## Solution
Updated `templates/base.html.twig` to use the correct route name `directory_offices` instead of `office_index`.

## Changes Made
- Replaced `path('office_index')` with `path('directory_offices')` in both mobile and desktop navigation sections
- The office management is handled by the DirectoryController, not a separate OfficeController

## Verification
Both occurrences in base.html.twig (lines 167 and 373) have been updated correctly.

## Result
The admin navigation now correctly links to:
- Clusters: `/admin/clusters` (admin_cluster_index)
- Offices: `/directory/offices` (directory_offices)
- Divisions: `/admin/divisions` (admin_division_index)

All admin navigation links are now working properly.
