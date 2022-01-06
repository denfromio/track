<?php

while ( 1 ) {
  clearstatcache()
  
  if ( is_file('/var/log/track/in.pending.log') ) {
    exec('cat /var/log/track/in.pending.log | clickhouse-client -q "INSERT INTO event FORMAT TSKV"');
    unlink('/var/log/track/in.pending.log');
  }

  sleep(5);
  
  echo filesize('/var/log/track/in.log') . '.';

  if ( filesize('/var/log/track/in.log') ) {
    exec('mv /var/log/track/in.log /var/log/track/in.pending.log');
    exec('kill -USR1 `cat /var/run/nginx.pid`');
  }
}
