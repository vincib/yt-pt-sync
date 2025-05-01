<?php

/** This is upload-one from yt-pt-sync, (C) Copyleft Benjamin Sonntag 2022-2025
 * This software is released under GPLv3+ license.
 */

require_once("config.php");
require_once("functions.php");

if (!isset($argv[1])) {
    echo "Usage: php upload-one.php <youtube-id> [f] upload one Youtube video to the right PT channel. add f to force already downloaded one\n";
    exit(1);
}

$id=$argv[1];
if (!preg_match('#^[a-zA-Z0-9_-]{6,12}$#',$id)) {
    echo "Id invalid\n";
    exit(1);
}

$force= (isset($argv[2]) && $argv[2]=="f");


$cache=false;
if (is_file("cache/cache.json")) {
    $cache=@json_decode(file_get_contents("cache/cache.json"),true);
}

// yt-dlp = timestamp of last yt-dlp update
// sync = array of youtube ID that I already synchronized. each ID is the key and the timestamp of download is the value
// channelid = array of peertube fqdn + - + channel name as key and channelID as value
if (!$cache) $cache=["yt-dlp"=>0, "sync" => [], "channelid" => []];

// default values just in case: 
if (!isset($conf["debug"])) $conf["debug"]=false;


chdir(__DIR__);

// get the list of the latest subscribed videos from my feed using my cookies
$out=[];
exec('yt-dlp  --cookies-from-browser '.$conf['browser'].' --dump-json --skip-download https://www.youtube.com/watch?v='.$id,$out,$res);
$data=json_decode($out[0],true);
if (!$data) {
    logme(LOG_ERR,"Sortie de yt-dlp invalide, merci de vérifier votre ID");
    exit(1);
}

// is it a video of a channel I want to mirror ?
if (isset($conf["sync"][$data["channel_id"]])) {
    // did we already take it?
    if (!$force && !isset($cache["sync"][$data["id"]])) {
        logme(LOG_DEBUG,"will download ".$data["id"]." for channel ".$conf["sync"][$data["channel_id"]][3]);
        if (download($data["id"],$conf["sync"][$data["channel_id"]])) {
            logme(LOG_INFO,"I downloaded ".$data["id"]." successfully.");
            $cache["sync"][$data["id"]]=time();
        } else {
            logme(LOG_ERR,"I failed to download ".$data["id"].". Please check details above.");
        }
    } else {
        logme(LOG_DEBUG,"skipping ".$data["id"]." already got it");
    }
} else {
    logme(LOG_DEBUG,"skipping ".$data["id"]." channel Unknown in config.php");
}


// save the cache in the end.
file_put_contents("cache/cache.json",json_encode($cache));



    
