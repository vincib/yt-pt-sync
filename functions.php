<?php


/* -------------------------------------------------------------------------------- */
/** Uses $conf["good-macs"] and ip route list / ip neigh list on Linux
 * to determine if the current Internet gateway is fast 
 * returns true if it is
 */
function is_my_gateway_fast() {
    global $conf;
    if (!isset($conf["good-macs"]) || !is_array($conf["good-macs"]) || !count($conf["good-macs"])) {
        // no good mac : always true
        return true;
    }

    $out=[];
    exec("ip route list",$out,$res);
    if ($res!=0) {
        logme(LOG_ERR,"Can't list the routes. is-my-gateway-fast will be inaccurate. please check that 'ip route list' is allowed");
        return true;
    }
    $gateway="";
    foreach($out as $one) if (preg_match('#^default via ([^ ]+) #',$one,$mat)) { $gateway=$mat[1]; break; }
    if (!$gateway) {
        logme(LOG_WARNING,"Found no default gateway, I'll consider this Internet absent,");
        return false;
    }
    
    $out=[];
    exec("ip neigh list ".$gateway,$out,$res);
    if ($res!=0) {
        logme(LOG_ERR,"Can't list the macs. is-my-gateway-fast will be inaccurate. please check that 'ip route list' is allowed");
        return true;
    }
    $mac="";
    foreach($out as $one) if (preg_match('#^[^ ]+ dev [^ ]+ lladdr ([^ ]+) #',$one,$mat)) { $mac=$mat[1]; break; }
    if (!$mac) {
        logme(LOG_WARNING,"Found no mac for the default gateway, I'll consider this Internet absent,");
        return false;
    }
    return in_array($mac,$conf["good-macs"]);
}


/* -------------------------------------------------------------------------------- */
/** Logs a message to $conf["log-file"] which can be a path or "syslog"
 * if it is unset, logs to stderr
 * level must be a LOG_* constants (see below)
 */ 
function logme($level,$message) {
    global $conf;
    static $first=false;
    $types=[
        LOG_WARNING=> "Warning",
        LOG_ERR=> "Error",
        LOG_INFO=> "Info",
        LOG_DEBUG=> "Debug",
    ];

    if (!$conf["debug"] && $level==LOG_DEBUG) return; 

    if (!isset($conf["log-file"])) {
        $conf["log-file"]="php://stderr";
    }
    if ($conf["log-file"]=="syslog") {
        if ($first) {
            openlog("yt-pt-sync", LOG_PID, LOG_LOCAL0);
            $first=false;
        }
        syslog($level,$message);
    } else {
        $f=@fopen($conf["log-file"],"ab");
        if (!$f) {
            echo "CRITICAL: can't log into ".$conf["log-file"]." please check permissions\n";
            echo date("Y-m-d H:i:s")." [".$types[$level]."] ".$message."\n";
        } else {
            fputs($f,date("Y-m-d H:i:s")." [".$types[$level]."] ".$message."\n");
            fclose($f);
        }
    }
}


/* -------------------------------------------------------------------------------- */
/** Authenticate on a Peertube server using oAuth
 */
function apioAuth2($instance,$username,$password) {

    $res1=file_get_contents("https://".$instance."/api/v1/oauth-clients/local");
    $res1=json_decode($res1);
    if (!isset($res1->client_id) || !isset($res1->client_secret)) {
        logme(LOG_ERR,"Can't oauth-clients on $instance");
        return false;
    }
    $ch = curl_init("https://".$instance."/api/v1/users/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'yt-pt-sync');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, true);
    $postfields="client_id=".$res1->client_id."&".
               "client_secret=".$res1->client_secret."&".
               "grant_type=password&".
               "response_type=code&".
               "username=".$username."&".
               "password=".$password
               ;
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

    $headers = array(
        'Content-type: application/x-www-form-urlencoded',
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $return = curl_exec($ch);
    curl_close($ch);
    $res=json_decode($return);
    if (isset($res->access_token)) {
        return $res->access_token;
    } else {
        logme(LOG_ERR,"Can't auth to $instance for user $username");
        return false;
    }
}


/* ------------------------------------------------------------------------------------------ */
/** Get a Channel object (mainly it's ID) from its name
 */
function apiGetChannel($instance,$bearer, $channelname) {
    $ch = curl_init("https://".$instance."/api/v1/video-channels/".$channelname);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'yt-pt-sync');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $headers = array(
        'Authorization: Bearer '.$bearer,
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $return = curl_exec($ch);
    curl_close($ch);
    return json_decode($return,true);
}


/* ------------------------------------------------------------------------------------------ */
/** Upload a video to a peertube instance,
 */
function apiUpload($instance, $bearer, $channelid, $title, $description, $videofile, $publishdate=null) {
    $ch = curl_init("https://".$instance."/api/v1/videos/upload");
    $cvFile = curl_file_create($videofile);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'yt-pt-sync');
    curl_setopt($ch, CURLOPT_TIMEOUT, 1800); // big timeout : upload may be LARGE 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    $postfields=array(
        "channelId" => $channelid,
        "name" => $title,
        "commentsPolicy" => 2,
        "downloadEnabled" => "true",
        "language" => "fr", /* @TODO: configure me */
        //        "generateTranscription" => "true", /* @TODO: configure me */
        "waitTranscoding" => "true", 
        "privacy" => 1,
        "licence" => 6,
        "videofile" => $cvFile,
    );
    if ($description) {
        $postfields["description"] = $description;
    }
    if (!is_null($publishdate)) {
        $postfields["originallyPublishedAt"]=$publishdate;
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    
    $headers = array(
        'Authorization: Bearer '.$bearer,
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $return = curl_exec($ch);
    curl_close($ch);
    return json_decode($return,true);
}


/* ------------------------------------------------------------------------------------------ */
/** Modify a video on a peertube instance,
 */
function apiEditVideo($instance, $bearer, $uuid, $title=null, $description=null, $thumbnail=null) {
    $ch = curl_init("https://".$instance."/api/v1/videos/".$uuid);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'yt-pt-sync');
    curl_setopt($ch, CURLOPT_TIMEOUT, 1800); // big timeout : upload may be LARGE 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    $postfields=[];
    if (!is_null($thumbnail)) {
        $ctFile = curl_file_create($thumbnail);
        $atype=["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp" ];
        $ctFile->setMimeType($atype[pathinfo($thumbnail, PATHINFO_EXTENSION)]);
        $postfields["thumbnailfile"]=$ctFile;
    }
    if (!is_null($title)) {
        $postfields["name"]=$title;
    }
    if (!is_null($description)) {
        $postfields["description"]=$description;
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    
    $headers = array(
        'Content-type: multipart/form-data',
        'Authorization: Bearer '.$bearer,
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $return = curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    // return code should be 204  & no content
    return ($code==204);
}


/* ------------------------------------------------------------------------------------------ */
/** Get the thumbnail of a video. Uses User-Agent.
 */
function getThumbnail($url,$file) {
    global $conf;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $conf["user-agent"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // big timeout : upload may be LARGE 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $return = curl_exec($ch);
    curl_close($ch);
    file_put_contents($file,$return);
}


/* -------------------------------------------------------------------------------- */
/** download the video having YouTube id $id to the channel $channel
 * id = something like dQw4w9WgXcQ
 * channel = the hash in config.php for this instance.
 */
function download($id,$channel) {
    global $conf;
    
    // I didn't add --write-auto-subs so that we don't take YT (somehow crappy) auto subs and prefer our whisper ones :) 
    // ./yt-dlp --cookies-from-browser chromium --write-subs --sub-langs fr --write-info-json --output "3peDwxfPVE4" "https://www.youtube.com/watch?v=3peDwxfPVE4"
    // in json I have "ext" that tells the extension of the saved file.
    logme(LOG_INFO,'Launching yt-dlp on '.$id.' for channel '.$channel[0].' '.$channel[3]);
    passthru('./yt-dlp --cookies-from-browser '.$conf['browser'].' --write-subs --sub-langs '.$conf['sub-langs'].' --write-info-json --output cache/'.$id.' "https://www.youtube.com/watch?v='.$id.'"',$res);
    logme(LOG_INFO,'Finished yt-dlp on '.$id);

    // read info.json, get the extension. should be webm or mp4
    if (!is_file('cache/'.$id.'.info.json')) {
        logme(LOG_ERR,'no '.$id.'.info.json, skipping this one');
        return false;
    }
    $data=json_decode(file_get_contents('cache/'.$id.'.info.json'),true);
    if (!$data) {
        logme(LOG_ERR,"can't decode info.json, skipping this video");
        return false;
    }
    if ($data["ext"]!="webm" && $data["ext"]!="mp4" && $data["ext"]!="mkv") { 
        logme(LOG_ERR,'extension is neither webm nor mp4 or mkv but '.$data['ext'].' skipping');
        return false;
    }
    if (filesize('cache/'.$id.'.'.$data['ext'])<1048576) {
        logme(LOG_ERR,'file too small, something is wrong '.filesize('cache/'.$id.'.'.$data['ext']).' skipping');
        return false;        
    }

    return upload($id,$channel,$data);    
}


/* -------------------------------------------------------------------------------- */
/** upload to peertube
 */
function upload($id,$channel,$data) {
    global $conf,$cache;
    
    // log into the peertube account.
    $bearer=apioAuth2($channel[0],$channel[1],$channel[2]);
    if (!$bearer) {
        logme(LOG_ERR,"Can't log into peertube, see message above");
        return false;
    }

    // if we don't have the channel_id for this instance+channel, we get it using the API
    if (!isset($cache["channelid"][$channel[0]."-".$channel[3]])) {
        $cid=apiGetChannel($channel[0],$bearer,$channel[3]);
        if (!$cid) {
            logme(LOG_ERR,"Can't find channel ".$channel[3]);
            return false;
        }
        $cache["channelid"][$channel[0]."-".$channel[3]]=$cid["id"];
    }
    
    // now we upload to peertube using the info and the file we got:
    // fulltitle description
    logme(LOG_INFO,"Uploading ".$id." to ".$channel[0]." channel ".$channel[3]);
    $uploaded = apiUpload($channel[0],$bearer,
                          $cache['channelid'][$channel[0]."-".$channel[3]],
                          $data['fulltitle'],$data['description'],'cache/'.$id.'.'.$data['ext'],
                          substr(date("c",$data["timestamp"]),0,19)."Z"
    );
    // $uploaded = {"video":{"id":39750,"shortUUID":"tZuXzLCvNtvarZnvt6RYYC","uuid":"e2adfc22-bbfd-4100-b508-093c2a7be84c"}}

    if (!$uploaded) {
        logme(LOG_ERR,"Can't upload ". $id." to ".$channel[0]." ".$channel[3] );
        return false;
    }
    if (!isset($uploaded["video"])) {
        logme(LOG_ERR,"Upload failed, result was ".json_encode($uploaded));
        return false;
    }
    $uuid=$uploaded["video"]["shortUUID"];

    
    // get the thumbnail :
    $thumbnail=null;
    if (isset($data["thumbnail"])) {
        $ext=pathinfo(parse_url($data["thumbnail"],PHP_URL_PATH), PATHINFO_EXTENSION);
        if ($ext=="webp" || $ext=="jpg") {
            getThumbnail($data["thumbnail"],"cache/".$id."-thumb.".$ext);
            $thumbnail="cache/".$id."-thumb.".$ext;
            logme(LOG_INFO,"Got thumnbnail of size ".filesize($thumbnail)." uploading");
            // save the thumbnail:
            $result=apiEditVideo($channel[0],$bearer, $uuid, null, null, $thumbnail);
            logme(LOG_INFO,"After thumbnail upload: ".$result);
        } else {
            logme(LOG_ERR,"Thumbnail ext is $ext, weird");
        }
    }

    // save it in cache for information
    logme(LOG_INFO,"Uploaded on ".$channel[0]." as ".$uuid);
    file_put_contents('cache/data',date('Y-m-d H:i:s').' '.$id.' '.$channel[0].' '.$uuid."\n",FILE_APPEND);

    uploadSubtitles($id,$channel,$uuid);

    return true;
}


/* -------------------------------------------------------------------------------- */
/** Upload subtitles of videos already uploaded to peertube
 */ 
function uploadSubtitles($id,$channel,$uuid) {
    global $conf;
    $langs=explode(",",$conf["sub-langs"]);
    foreach($langs as $lang) {
        if (is_file('cache/'.$id.'.'.$lang.'.vtt')) {
            // upload me
            logme(LOG_INFO,"HERE I SHOULD UPLOAD THE ".$lang." SUBTITLE...");
        }
    }
}

function searchDataByYtId($id) {
    $f=fopen('cache/data','rb');
    while($s=fgets($f,1024)) {
        $fields=explode(' ',trim($s));
        if ($fields[2]==$id) {
            fclose($f);
            return $fields;
        }
    }
    fclose($f);
    return false;
}
