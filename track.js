function remetric(id, metric, options, control) {
  
  options = options || {};
  options.metric = metric;
  options.container = id;
  
  if ( control ) {
    control.classList.add('loading');
  }
  
  fetch('', {
    method: 'post',
    body: JSON.stringify(options)
  }).then(function(r) {
    return r.text();
  }).then(function(html) {
    document.querySelector('#metric-' + id).innerHTML = html;
  });
}
