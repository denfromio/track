<?php

function detach_logs() {
  foreach ( glob('/var/log/track/in_*.log') as $log ) {
    if ( filesize($log) ) {
      $detach = true;
      exec('mv ' . $log . ' ' . str_replace('.log', '.pending', $log));
    }
  }
  
  if ( $detach ) {
    exec('kill -USR1 `cat /var/run/nginx.pid`');
  }
}

function store_pending() {
  foreach ( glob('/var/log/track/in_*.pending') as $log ) {
    $m = [];
    preg_match('/in_(.+)\.pending/', $log, $m);    
    $event = $m[1];

    store_event($event);
    unlink($log);
  }
}

function store_event( $name ) {
  exec('cat /var/log/track/in_' . $name . '.pending | clickhouse-client -q "INSERT INTO ' . $name . ' FORMAT TSKV" 2>&1', $o, $error);
  $response = implode("\n", $o);
  
  if ( $error ) {
    $err_col = 'Unknown field found while parsing TSKV format: ';
    $err_tbl = 'DB::Exception: Table default.';
    
    if ( strpos($response, $err_col) ) {
      $col = substr($response, strpos($response, $err_col) + strlen($err_col), strpos($response, ': (at row') - strpos($response, $err_col) - strlen($err_col));
      echo 'Adding column ' . $col . "\n";
      exec('clickhouse-client -q "alter table ' . $name . ' add column ' . $col . ' String"');
      store_event($name);
    }
    else if ( strpos($response, $err_tbl) ) {
      $tbl = substr($response, strpos($response, $err_tbl) + strlen($err_tbl), strpos($response, ' doesn\'t exist.') - strpos($response, $err_tbl) - strlen($err_tbl));
      echo 'Adding table ' . $tbl . "\n";
      exec('clickhouse-client -q "create table ' . $tbl . ' (time Date) engine = MergeTree ORDER BY time Partition by time"');
      store_event($name);
    }
    else {
      exec('cat /var/log/track/in_' . $name . '.pending >> /var/log/track/in_' . $name . '.error');
    }
  }
  
  return $issue;
}

while ( 1 ) {
  clearstatcache();
  store_pending();
  sleep(5);
  detach_logs();
}
