<?php

require_once("config.php");
require_once("functions.php");

if (!isset($argv[1])) {
    echo "Usage: php get-channel-id.php <channel url>\n";
    exit(1);
}

exec('yt-dlp --flat-playlist --cookies-from-browser '.$conf['browser'].' --dump-json --skip-download --playlist-items 1:1 '.$argv[1],$out,$res);
$data=json_decode($out[0],true);

echo $data["playlist_channel_id"]."\n";

