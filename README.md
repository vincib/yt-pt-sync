

# YouTube to Peertube synchronization script

This scripts uses yt-dlp, but with more options available than peertube usual synchronization
(like using cookies from a local browser !)

Also, it optimizes the number of calls to YouTube to prevent them from blacklisting your IP address... (hopefully)

## Dependencies :

* php-cli php-curl (could be anything from php5.6 to 8.4, should work :) )
* yt-dlp from here https://github.com/yt-dlp/yt-dlp
* a crontab
* a local browser with cookies connected to a Youtube account with subscriptions to the channels you want to mirror.
* util-linux to get flock for the crontab locking ;)



