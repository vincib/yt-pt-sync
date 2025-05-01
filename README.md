# What does this script do?

This script search for videos in a [personal subscription feed](https://www.youtube.com/feed/subscriptions) using your browser's cookies, and upload the videos of some youtube channels to some peertube channels.

It's using php-cli, [yt-dlp](https://github.com/yt-dlp/yt-dlp) and curl to do so.

Since it's using your subscription timeline, you need to be subscribed to the youtube channels you want to mirror, but that means that this script is doing only 1 call to youtube api to know which video have been recently uploaded, therefore preventing a blacklist from youtube (hopefully).

Please note that this program is aimed at being installed on your daily computer, the one where you have a browser (like Firefox or Chromium) where you are surfing youtube on a google-connected account, since this program launch yt-dlp with the option that uses your browser-cookies to be seen as "friendly" and "not bot" to youtube.

All this maximizes the chances that youtube will not block your downloads (enshittification++, yay...). 

## How to use it.

I'm using it under Linux. This should work on any distribution. 

First, clone this repository, using `git clone https://github.com/vincib/yt-pt-sync.git`

Then install php-cli and php-curl (could be anything from php5.6 to 8.4). On Debian it's done with `sudo apt install php-cli php-curl`

Install [yt-dlp from the official repository](https://github.com/yt-dlp/yt-dlp) and put the yt-dlp binary in the cloned folder. Note that yt-dlp will be updated automatically (once a day) to ensure you always have the same version.

Copy `config.sample.php` to `config.php` and fill it properly. You'll need to tell which browser you are using (for yt-dlp autodetection of cookies path) and the list of youtube channels you want to mirror, along with the url, login, password and channel of the peertube channels where you want to copy those youtube videos.

Note that for each youtube channel, you need to tell the youtube channel-id as key in the configuration file. To get a youtube channel-id, uses this command with the youtube channel url as parameter. Example: `php get-channel-id.php https://www.youtube.com/@3blue1brown`

Then, test the script by launching it first with : `php yt-pt-sync.php`

If you want to launch it periodically, define a crontab for your user (the same user that uses a browser connected to a youtube account ;) ). Uses the "crontab" file as an example. To edit your user's crontab, use the command `crontab -e`


## Other commands

There are other commands you can use :

* `php upload_one.php <youtube-id>` to upload 1 youtube video from its ID to the automatically chosen good peertube channel
* `edit-thumbnail.php <youtube-id>` to upload the thumbnail of a video (you will need the file <id>.info.json in the cache folder).





