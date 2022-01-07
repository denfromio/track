<?php

function clickhouse($query) {
  $c = curl_init('http://127.0.0.1:8123?query=' . urlencode($query));
  curl_setopt_array($c, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-ClickHouse-Format: JSON']
  ]);
  
  $response = curl_exec($c);
   return json_decode($response, 1)['data'];
}

function metric($name, $options = []) {
  static $loaded;
  
  $metric_id = $options['container'] ?: uniqid();
  
  $html = '';
  
  if ( !$loaded ) {
    ob_start();
    include __DIR__ . '/track.js';
    $js = ob_get_clean();
    $html .= '<script>' . $js . '</script>';
    
    ob_start();
    include __DIR__ . '/track.css';
    $css = ob_get_clean();
    $html .= '<style>' . $css . '</style>';
    
    $loaded = true;
  }
  
  $data = metric_data($name, $options);
  $max = max($data);
  foreach ( $data as $t => $v ) {
    $bars[] = '<i><i style="height:' . round(200*$v/$max) . 'px;"><em>' . $v . '</em></i><em>' . $t . '</em></i>';
  }
  
  $chart = '<div class="bars">' . implode($bars) . '</div>';
  
  $periods = [];
  foreach ( ['hour', 'day', 'month', 'year'] as $period ) {
    $opts = json_encode(array_merge($options, ['period' => $period]));
    $p = $period[0];
    $periods[] = "<a class='" . ($period == ($options['period']?:'day') ? 'on' : '') . "' href='javascript:;' onclick='remetric(\"{$metric_id}\", \"{$name}\", {$opts}, this)'>{$p}</a>";
  }
  
  $controls = '<div class="periods">' . implode('', $periods) . '</div>';
  
  
  
  $val = array_sum($data);
  if ( $options['latest'] ) {
    $vals = $data;
    do {
      $val = array_pop($vals);
    } while ( !$val && $vals );
  }
  
  $legend = '
    <span class="val">' . $val . '</span>
  ';
  
  
  
  $title = $options['title'] ?: $name;
  
  
  
  $html .= "
    <h3>{$title}</h3><div>{$chart}</div>
    <div class='controls'>{$controls}</div>
    <div class='legend'>{$legend}</div>
  ";
  return $options['html-only'] ? $html : "<div class='track-metric' id='metric-{$metric_id}'>{$html}</div>";
}

function metric_data($name, $options) {
  $period = $options['period']?:'day';
  
  if ( $period == 'hour' ) {
    $time_group = 'toStartOfMinute(time)';
    $tpl = array_map(function ($i) { return date('Y-m-d H:i:00', strtotime('-' . $i . ' minute')); }, range(59, 0));
  }
  else if ( $period == 'day' ) {
    $time_group = 'toStartOfHour(time)';
    $tpl = array_map(function ($i) { return date('Y-m-d H:00:00', strtotime('-' . $i . ' hour')); }, range(23, 0));
  }
  else if ( $period == 'year' ) {
    $time_group = 'toStartOfMonth(time)';
    $tpl = array_map(function ($i) { return date('Y-m-01', strtotime('-' . $i . ' month')); }, range(11, 0));
  }
  else {
    $time_group = 'toDate(time)';
    $tpl = array_map(function ($i) { return date('Y-m-d', strtotime('-' . $i . ' day')); }, range(30, 0));
  }
  
  
  $sel_val = 'count(*)';
  if ( $options['latest'] ) {
    $sel_val = 'anyLast(' . $options['latest'] . ')';
  }
  
  
  if ( $options['where'] ) {
    $where = [];
    foreach ( $options['where'] as $k => $v ) {
      if ( strpos($k, '_id') ) {
        $where[] = "{$k} = {$v}";
      }
      else {
        $where[] = "{$k} = '{$v}'";
      }
    }
    
    $where = ' WHERE ' . implode(' AND ', $where);
  }
  else {
    $where = '';
  }
  
  
  $data = clickhouse('SELECT ' . $time_group . ' t,' . $sel_val . ' v FROM ' . $name . ' ' . $where . ' GROUP BY t ORDER BY t DESC LIMIT 100');
  foreach ( $data as $row ) {
    $kv[$row['t']] = $row['v'];
  }
  
  
  $previous = $kv[array_key_first($kv)];
  foreach ( $tpl as $t ) {
    if ( $options['latest'] ) {
      $default = $previous;
    }
    else {
      $default = 0;
    }
    
    $v = $kv[$t] ?: $default;
    $time_series[in_array($period, ['day', 'hour']) ? date('H:i', strtotime($t)) : $t] = $v;
    
    $previous = $v;
  }
  
  return $time_series;
}




if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
  $_POST = json_decode(file_get_contents('php://input'), 1);
  ob_clean();
  
  $_POST['html-only'] = true;
  echo metric($_POST['metric'], $_POST);
  exit;
}
