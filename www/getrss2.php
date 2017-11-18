<?php

function f_parse($html_, $host_, $page_) {
 
  $connection_info = parse_url(getenv('DATABASE_URL'));
  $pdo = new PDO(
    "pgsql:host=${connection_info['host']};dbname=" . substr($connection_info['path'], 1),
    $connection_info['user'],
    $connection_info['pass']);
    
  $sql = <<< __HEREDOC__
SELECT M1.type
      ,M1.word
  FROM m_words M1
 WHERE M1.type > 200
__HEREDOC__;
  
  $words = array();
  foreach ($pdo->query($sql) as $row) {
    $words[(string)$row['type']] = $row['word'];
  }
  
  $buf = $html_;
  $buf = explode($words['201'], $buf, 2)[1];
  $buf = explode($words['202'], $buf, 2)[0];

  $sql = <<< __HEREDOC__
INSERT INTO t_contents2
( uri, title, thumbnail, time, page ) VALUES ( :b_uri, :b_title, :b_thumbnail, :b_time, :b_page )
__HEREDOC__;
  $statement = $pdo->prepare($sql);

  foreach(explode($words['203'], $buf) as $one_record) {

    if (strpos($one_record, $words['204']) === false) {
      continue;
    }   
    
    if (preg_match($words['205'], $one_record, $matches) == 0) {
      continue;
    }
    $time = $matches[1];
    
    if (preg_match('/^([1-4]\d|\d)$/', $time, $matches) == 1) {
      continue;
    }
    
    if (preg_match('/<a href="(.+?)"/', $one_record, $matches) == 0) {
      continue;
    }
    $href = $matches[1];
    
    if (preg_match('/<img src="(.+?)"/', $one_record, $matches) == 0) {
      continue;
    }
    $thumbnail = 'https:' . $matches[1];
   
    if (preg_match('/<a .+? title="(.+?)"/', $one_record, $matches) == 0) {
      continue;
    }
    $title = $matches[1];
        
    //error_log("${time} ${title} ${href} ${thumbnail}");
    error_log("${href} ${title}");
    
    $rc = $statement->execute(
      array(':b_uri' => $href,
            ':b_title' => $title,
            ':b_thumbnail' => $thumbnail,
            ':b_time' => $time,
            ':b_page' => $page_
           ));
    error_log($rc);
  }
  
  $pdo = null;
  
  return;
}

$start_time = time();

$max_count = getenv('MAX_COUNT_2');
$per_count = getenv('PER_COUNT_2');

$pid = getmypid();
$count = $_GET['c'];
$url = $_GET['u'];

error_log("${pid} START ${count}");

$count++;

$target_url = getenv('SEARCH_URL_2');

if ($count > $max_count) {
  error_log("${pid} FINISH ${count}");
  return;
}

if ($count === 1) {
  $connection_info = parse_url(getenv('DATABASE_URL'));
  $pdo = new PDO(
    "pgsql:host=${connection_info['host']};dbname=" . substr($connection_info['path'], 1),
    $connection_info['user'],
    $connection_info['pass']);

  $pdo->exec('TRUNCATE TABLE t_contents2');

  $pdo = null;
}

$urls = array();
for($i = 0; $i < $per_count; $i++) {
  $urls[] = $target_url . (($count - 1) * $per_count + $i + 1);
}
$urls[] = $url . '?c=' . $count . '&u=' . $url;

$mh = curl_multi_init();

foreach($urls as $url) {
  $ch = curl_init();
  curl_setopt_array($ch, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => parse_url($url, PHP_URL_QUERY)
    ));
  curl_multi_add_handle($mh, $ch);
}

do {
  $stat = curl_multi_exec($mh, $running);
} while ($stat === CURLM_CALL_MULTI_PERFORM);

do switch (curl_multi_select($mh, 10)) {
  case -1:
    usleep(10);
    do {
      $stat = curl_multi_exec($mh, $running);
    } while ($stat === CURLM_CALL_MULTI_PERFORM);
    continue 2;
  case 0:
    continue 2;
  default:
    do {
      $stat = curl_multi_exec($mh, $running);
    } while ($stat === CURLM_CALL_MULTI_PERFORM);
    
    do if ($raised = curl_multi_info_read($mh, $remains)) {
      $info = curl_getinfo($raised['handle']);
      $page = explode('=', parse_url($info[url], PHP_URL_QUERY), 2)[1];
      $host = parse_url($info[url], PHP_URL_HOST);
      $response = curl_multi_getcontent($raised['handle']);
      //error_log("${pid} ${query_string} " . strlen($response));
      //error_log($response);
      f_parse($response, $host, $page);
      curl_multi_remove_handle($mh, $raised['handle']);
      curl_close($raised['handle']);
    } while ($remains);
} while ($running);

curl_multi_close($mh);

$sql = <<< __HEREDOC__
SELECT T1.uri
      ,T1.title
      ,T1.thumbnail
      ,T1.time
  FROM t_contents2 T1
 ORDER BY CAST(T1.page AS integer)
         ,T1.create_time
__HEREDOC__;

$xml_root_text = <<< __HEREDOC__
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>db3</title>
    <link>http://www.yahoo.co.jp</link>
    <description>none</description>
    <language>ja</language>
    __ITEMS__
  </channel>
</rss>
__HEREDOC__;

if ($count === 1) {  
  $connection_info = parse_url(getenv('DATABASE_URL'));
  $pdo = new PDO(
    "pgsql:host=${connection_info['host']};dbname=" . substr($connection_info['path'], 1),
    $connection_info['user'],
    $connection_info['pass']);

  $items = array();
  foreach ($pdo->query($sql) as $row) {
    $uri = $row['uri'];
    $title = $row['title'];
    $thumbnail = $row['thumbnail'];
    $time = $row['time'];
    $tag = explode('/', $uri)[5];
    $items[] = "<item><title>${time} ${title}</title><link>${uri}</link><description>&lt;img src='${thumbnail}'&gt;${tag}</description><pubDate></pubDate></item>";
  }

  header('Content-Type: application/xml; charset=UTF-8');
  echo str_replace('__ITEMS__', implode("\r\n", $items), $xml_root_text);
  $pdo = null;
  
  error_log('items : ' . count($items));
  $url = 'https://logs-01.loggly.com/inputs/' . getenv('LOGGLY_TOKEN') . '/tag/' . $_SERVER['SERVER_NAME'] . '/';
  $context = array(
    'http' => array(
      'method' => 'POST',
      'header' => array(
        'Content-Type: text/plain'
        ),
      'content' => 'items : ' . count($items)
      ));
  $res = file_get_contents($url, false, stream_context_create($context));
  error_log($res);
} else {
  echo '<HTML><HEAD><TITLE>' . ($start_time - time()) . '</TITLE></HEAD><BODY>' . time() . '</BODY></HTML>';
}

error_log("${pid} FINISH ${count}");
?>
