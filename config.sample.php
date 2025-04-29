<?php

$conf=[
    
    /* list of the MAC address of the ROUTERs where your computer could be connected at, that would have FAST and CHEAP Internet 
       if you don't specify mac addresses, the script is always enabled 
       this allow you to prevent downloading from a 4G or restricted network on a laptop
       to know your router address, uses "ip route" to get the default IP, then "ip neighbor show <your default route IP address>" to get its MAC.
    */
    "good-macs" => [ "cd:6a:88:32:0c:58", "fd:a5:8c:c0:74:2b" ],
    
        /* List of Youtube channels that you want to mirror on Peertube channels.
           For each youtube channel, you need to tell the Peertube URL, Login, Password and Channel
           "my-yt-channel" should be the channel ID not the name. uses php get-channel-id.php <yt url> to know a yt channel ID from a channel name.
        */
    "sync" => [
        "my-yt-channel" => [ "https://mypeertube", "login", "password", "pt-channel-name"  ],
    ],

        /* how many videos we scan on our playlist each hour.
           recommended : 30 max if you want to get only 1 page */
    "max" => 100,
    
        /* shall we log DEBUG events ? */
    "debug" => true,
    
        /* which languages (separated by comma, see yt-dlp --help option "--sub-langs". Note that this applies to ALL syncs */
    "sub-langs" => "fr",

        /* Your browser name, to tell yt-dlp which cookies to search for */
    "browser" => "chromium",
    
        /* Log File to append to, if "syslog" will log to Syslog on LOCAL0. if unset, logs to stderr */
    "log-file" => "/var/log/yt-pt-sync/yt-pt-sync.log",

];

