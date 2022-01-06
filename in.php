<?php

while ( 1 ) {
  clearstatcache();
  
  if ( is_file('/var/log/track/in.pending.log') ) {
    $o = null;
    exec('cat /var/log/track/in.pending.log | clickhouse-client -q "INSERT INTO event FORMAT TSKV" 2>&1', $o);
    $o = implode("\n", $o);
    
    $err = 'Unknown field found while parsing TSKV format: ';
    if ( strpos($o, $err) ) {
      $col = substr($o, strpos($o, $err) + strlen($err));
      $sol = substr($col, strpos($col, ':'));
      
      echo 'we should add col: ' . $col . "\n";
    }
    
    unlink('/var/log/track/in.pending.log');
  }

  sleep(5);

  if ( filesize('/var/log/track/in.log') ) {
    exec('mv /var/log/track/in.log /var/log/track/in.pending.log');
    exec('kill -USR1 `cat /var/run/nginx.pid`');
  }
}
