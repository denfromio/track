function remetric(id, metric, options, control) {
  
  options = options || {};
  options.metric = metric;
  options.container = id;
  
  fd = new FormData();
  Object.keys(options).forEach(key => fd.append(key, options[key]));
  
  if ( control ) {
    control.classList.add('loading');
  }
  
  fetch('', {
    method: 'post',
    body: fd
  }).then(function(r) {
    return r.text();
  }).then(function(html) {
    document.querySelector('#metric-' + id).innerHTML = html;
  });
}
