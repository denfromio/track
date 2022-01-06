<?php

while ( 1 ) {
  clearstatcache();
  
  if ( is_file('/var/log/track/in.pending.log') ) {
    $o = null;
    exec('cat /var/log/track/in.pending.log | clickhouse-client -q "INSERT INTO event FORMAT TSKV" 2>&1', $o, $error);
    
    if ( $error ) {
      $o = implode("\n", $o);

      $err = 'Unknown field found while parsing TSKV format: ';
      if ( strpos($o, $err) ) {
        $col = substr($o, strpos($o, $err) + strlen($err), strpos($o, ': (at row') - strpos($o, $err) - strlen($err));
        echo 'we should add col: ' . $col . "\n";
        exec('clickhouse-client -q "alter table event add column ' . $col . ' String"');
        exec('cat /var/log/track/in.pending.log | clickhouse-client -q "INSERT INTO event FORMAT TSKV"');
      }
      else {
        exec('cat /var/log/track/in.pending.log >> /var/log/track/in.error.log');
    }

    unlink('/var/log/track/in.pending.log');
  }

  sleep(5);

  if ( filesize('/var/log/track/in.log') ) {
    exec('mv /var/log/track/in.log /var/log/track/in.pending.log');
    exec('kill -USR1 `cat /var/run/nginx.pid`');
  }
}
