<?php

$pid = getmypid();

$url = $_GET['u'];
error_log("${pid} ${url}");

header('Content-Type: text/plain');

$connection_info = parse_url(getenv('DATABASE_URL'));
$pdo = new PDO(
  "pgsql:host=${connection_info['host']};dbname=" . substr($connection_info['path'], 1),
  $connection_info['user'],
  $connection_info['pass']);

$sql = <<< __HEREDOC__
SELECT url2 FROM t_urls WHERE url1 = :b_url1
__HEREDOC__;
$statement = $pdo->prepare($sql);

$statement->execute([':b_url1' => $url ]);
$result = $statement->fetch();
if ($result !== FALSE) {
  echo $result['url2']);
  $pdo = null;
  exit;
}

$res = get_contents($url);
$rc = preg_match('/<div class="gotoBlog"><a href="(.+?)"/', $res, $matches);
if ($rc != 1) {
  echo 'NONE';
  $pdo = null;
  exit;
}

$url2 = $matches[1];
error_log("${pid} ${url2}");

$res = get_contents($url2);

$url3 = 'none';
for ($i = 0; $i < 2; $i++) {
  if ($i == 0) {
     $pattern = explode(',', getenv('LINK_PATTERN1'));
  } else {
     $pattern = explode(',', getenv('LINK_PATTERN2'));
  }
  $rc = preg_match('/' . $pattern[0] . '/', $res, $matches);
  if ($rc != 1) {
    continue;
  }
  error_log("${pid} " . $matches[1]);
  $url3 = str_replace($pattern[1], $matches[1], $pattern[2]);
  error_log("${pid} ${url3}");
}

if ($url3 == 'none') {
  echo 'NONE';
  $pdo = null;
  exit;
}

$sql = <<< __HEREDOC__
INSERT INTO t_urls (url1, url2) VALUES (:b_url1, :b_url2)
__HEREDOC__;
$statement = $pdo->prepare($sql);
$statement->execute([':b_url1' => $url, ':b_url2' => $url3]);

echo $url3;

$pdo = null;
exit;

function get_contents($url_) {
  $ch = curl_init();
  curl_setopt_array($ch,
                    [CURLOPT_URL => $url_,
                     CURLOPT_RETURNTRANSFER => TRUE,
                     CURLOPT_ENCODING => '',
                     CURLOPT_CONNECTTIMEOUT => 20,
                     CURLOPT_FOLLOWLOCATION => TRUE,
                     CURLOPT_MAXREDIRS => 3,
                     CURLOPT_FILETIME => TRUE,
                     // CURLOPT_TCP_FASTOPEN => TRUE,
                     CURLOPT_SSL_FALSESTART => TRUE,
                     CURLOPT_PATH_AS_IS => TRUE,
                     CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; rv:56.0) Gecko/20100101 Firefox/61.0',
                    ]);
  $contents = curl_exec($ch);
  curl_close($ch);  
  return $contents;
}
?>
