# WP Country tag
This is a Wordpress plugin which can show/hide content by country.

# Features
- Detect which country visitors are coming from based on IP address. 
- Add a shortcode that shows or hides content by country.
- You can use the free database GeoLite2 or the paid database GeoIP2 provided by Maxmind.
- Auto updates database

# Requirement
- Wordpress
- License key of GeoLite2 or GeoIP2.

# Install
1. Install this plugin for your website.

# Setting
1. Get a Maxmind license key.
1. Open Settings -> Country Tag settings page.
1. Choose a plan based on your plan. 
1. Input your license key.
1. Save settings.
1. Update DB now.

# Usage
[country_text in="Country code ex US,JP" altsc="Shortcoder name for the alternative.(Option)"]This content will be shown to visitors from countries listed as "in" property. [/country_text]

[country_text not_in="Country code ex US,JP" altsc="Shortcoder name for the alternative.(Option)"]This content will be hidden to visitors from countries listed as "not_in" property[/country_text]

# Note
To auto-update, make sure wp-cron is running normally.

# Screenshot
![Settings-Database](https://blog.shipweb.jp/wp-content/uploads/2022/02/CountryTag_01.jpg)

![Settings-Info](https://blog.shipweb.jp/wp-content/uploads/2022/02/CountryTag_02.jpg)

# Author
* ship [blog](https://blog.shipweb.jp/)

# License
GPLv3