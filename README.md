# drupal-active-issues
Drupal 8 test environment for displaying recently active issues for drupal.org projects

## Installation

 - `git clone`
 - `composer install`
 - `drush si --locale=de` (standard profile)
 - enable custom module "Project issues"

## Usage

 - For anonymous users there is a demo block automatically shown on the frontpage after enabling the custom module.
 - As admin you can place the custom block "Active issues on drupal.org" anywhere you like via the block layout page.
 - When placing the block you have to specify a valid machine name of a project on drupal.org and the maximum number of issues to be shown.

## Known issues
 - The automatically installed demo block depends on the default `Bartik` theme being used.
 - According to the original task the block should display the "most active" issues in the queue. There's some room for interpretation here:
   - My first choice was to sort the issues by the number of comments because many comments are a strong indicator for activity. Unfortunately the API doesn't seem to support it.
   - Now the listing shows only active issues, starting with the most recently updated - which is the closest I could get to "most active".
