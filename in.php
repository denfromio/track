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
    echo $name . "\n";
    echo 'Error: ' . $resposne . "\n\n";
    
    $err_col = 'Unknown field found while parsing TSKV format: ';
    $err_tbl = 'DB::Exception: Table default.';
    
    if ( strpos($resposne, $err_col) ) {
        $col = substr($resposne, strpos($resposne, $err_col) + strlen($err_col), strpos($resposne, ': (at row') - strpos($resposne, $err_col) - strlen($err_col));
        echo 'Adding column ' . $col . "\n";
        exec('clickhouse-client -q "alter table event add column ' . $col . ' String"');
    }
    else if ( strpos($resposne, $err_tbl) ) {
        $tbl = substr($resposne, strpos($resposne, $err_tbl) + strlen($err_tbl), strpos($resposne, ' doesn\'t exist.') - strpos($resposne, $err_tbl) - strlen($err_tbl));
        echo 'Adding table ' . $tbl . "\n";
        exec('clickhouse-client -q "create table ' . $tbl . ' (time Date) engine = MergeTree ORDER BY time Partition by time"');
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
