<?php

$pid = getmypid();
$url = $_GET['u'];
error_log("${pid} ${url}");
header('Content-Type: text/plain');
$res = get_contents($url);
$rc = preg_match('/<div class="gotoBlog"><a href="(.+?)"/', $res, $matches);

?>
