<?php

require_once("config.php");
require_once("functions.php");

if (!isset($argv[1])) {
    echo "Usage: php edit-thumbnail.php <youtube-id> (that have been downloaded earlier)\n";
    exit(1);
}

$id=$argv[1];
if (!preg_match('#^[a-zA-Z0-9_-]{6,12}$#',$id)) {
    echo "Id invalid\n";
    exit(1);
}

chdir(__DIR__);

if (!is_file('cache/'.$id.'.info.json')) {
    echo "$id has not been downloaded before : I miss cache/".$id.".info.json\n";
    exit(1);
}

$data=json_decode(file_get_contents('cache/'.$id.'.info.json'),true);

// get the thumbnail :
$thumbnail=null;
if (!isset($data["thumbnail"])) {
    echo "no thumbnail in cache/".$id.".info.json\n";
    exit(1);
}

// now get the peertube channel
$fields=searchDataByYtId($id);
if (!$fields) {
    echo "can't find $id in cache/data\n";
    exit(1);
}

if (!isset($conf["sync"][$data["channel_id"]])) {
    echo "didn't find channel ".$data["channel_id"]." in config.php\n";
    exit(1);
}
$channel=$conf["sync"][$data["channel_id"]];
$uuid=$fields[4];

$bearer=apioAuth2($channel[0],$channel[1],$channel[2]);

$ext=pathinfo(parse_url($data["thumbnail"],PHP_URL_PATH), PATHINFO_EXTENSION);
if ($ext=="webp" || $ext=="jpg") {
    getThumbnail($data["thumbnail"],"cache/".$id."-thumb.".$ext);
    $thumbnail="cache/".$id."-thumb.".$ext;
    echo "Channel ".$channel[3]." on ".$channel[0]." peertube uuid ".$uuid."\n";
    echo "Got thumnbnail of size ".filesize($thumbnail)." uploading\n";;
    // save the thumbnail:
    $result=apiEditVideo($channel[0],$bearer, $uuid, null, null, $thumbnail);
    echo "After thumbnail upload: ".json_encode($result)."\n";
}    
