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
        logme(LOG_ERROR,"Can't list the routes. is-my-gateway-fast will be inaccurate. please check that 'ip route list' is allowed");
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



function download($id,$channel) {
    // I didn't add --write-auto-subs so that we don't take YT (somehow crappy) auto subs and prefer our whisper ones :) 
    // ./yt-dlp --cookies-from-browser chromium --write-subs --sub-langs fr --write-info-json --output "3peDwxfPVE4" "https://www.youtube.com/watch?v=3peDwxfPVE4"
    // in json I have "ext" that tells the extension of the saved file.
    return true;
}
