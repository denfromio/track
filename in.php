<?php

while ( 1 ) {
  if ( is_file('/var/log/track/in.pending.log') ) {
    exec('cat /var/log/track/in.pending.log | clickhouse-client -q "INSERT INTO event FORMAT TSKV"');
    unlink('/var/log/track/in.pending.log');
  }

  sleep(5);

  exec('mv /var/log/track/in.log /var/log/track/in.pending.log');
  exec('kill -USR1 `cat /var/run/nginx.pid`');
}
