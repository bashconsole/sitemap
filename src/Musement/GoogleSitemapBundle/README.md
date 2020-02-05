# MusementGoogleSitemapBundle


## Overview

This bandle was developer as a test task for Musement company.
It provides command to generate Google sitemap using data provided by Musement API.

## Versions

This bundle is compatible with all Symfony versions since `2.3.0`.


## Features

 * Creates sitemap as file in specified folder
 * Sends sitemap file to multiple recipients


## Documentation and usage

- Help:
bin/console musement:googlesitemap --help

- Send sitemap by email:
bin/console musement:googlesitemap -r "user@example.com" -- fr-FR

- Save sitemap as file
bin/console musement:googlesitemap -d /home/user/sitemap -- fr-FR


## Configuration

The bundle uses swiftmailer bundle.

You might prefer to send emails using Gmail instead of setting up a regular SMTP server. To do that, update the MAILER_URL of your .env file to this:

# username is your full Gmail or Google Apps email address
MAILER_URL=gmail://username:password@localhost

If your Gmail account uses 2-Step-Verification, you must generate an App password and use it as the value of the mailer password. You must also ensure that you allow less secure applications to access your Gmail account.


# Default configuration parameters 
Tune default configuration values in config/services.yaml.

