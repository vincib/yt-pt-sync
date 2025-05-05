<?php

/** This is yt-pt-sync.php, (C) Copyleft Benjamin Sonntag 2022-2025
 * This software is released under GPLv3+ license.
 */

require_once("config.php");
require_once("functions.php");

// no internet or not fast internet ? does nothing
if (!is_my_gateway_fast()) {
    logme(LOG_INFO,"No Internet or no fast Internet, doing nothing");
    exit(2);
}

// read the cache file from last time
chdir(__DIR__);
@mkdir('cache');
if (!is_file('yt-dlp')) {
    die('Please install yt-dlp in the same folder');
}

$cache=false;
if (is_file("cache/cache.json")) {
    $cache=@json_decode(file_get_contents("cache/cache.json"),true);
}

// yt-dlp = timestamp of last yt-dlp update
// sync = array of youtube ID that I already synchronized. each ID is the key and the timestamp of download is the value
// channelid = array of peertube fqdn + - + channel name as key and channelID as value
if (!$cache) $cache=["yt-dlp"=>0, "sync" => [], "channelid" => []];

// Try to update yt-dlp every day
if ($cache['yt-dlp']< (time()-86400)) {
    exec("./yt-dlp -U");
    $cache['yt-dlp']=time();
}

// default values just in case: 
if (!isset($conf["debug"])) $conf["debug"]=false;
if (!isset($conf["max"])) $conf["max"]=30;

$didsomething=0;
// get the list of the latest subscribed videos from my feed using my cookies
$out=[];
exec('yt-dlp --flat-playlist --cookies-from-browser '.$conf['browser'].' --dump-json --skip-download --playlist-items 1:'.$conf['max'].' https://www.youtube.com/feed/subscriptions',$out,$res);
logme(LOG_DEBUG,"got ".count($out)." videos...");

foreach($out as $one) {
    $data=json_decode($one,true);
    // is it a video of a channel I want to mirror ?
    if (isset($conf["sync"][$data["channel_id"]])) {
        // did we already take it?
        if (!isset($cache["sync"][$data["id"]])) {
            logme(LOG_DEBUG,"will download ".$data["id"]." for channel ".$conf["sync"][$data["channel_id"]][3]);
            $didsomething++;
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
        logme(LOG_DEBUG,"skipping ".$data["id"]." not a channel I want");
    }
}


// save the cache in the end.
file_put_contents("cache/cache.json",json_encode($cache));

logme(LOG_INFO,"Finishing after downloading ".$didsomething." videos");



    
