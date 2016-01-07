/**
 * @provides javelin-behavior-refresh-csrf
 * @requires javelin-request
 *           javelin-behavior
 *           javelin-dom
 *           javelin-router
 *           javelin-util
 *           phabricator-busy
 */

/**
 * We cycle CSRF tokens every hour but accept the last 6, which means that if
 * you leave a page open for more than 6 hours before submitting it you may hit
 * CSRF protection. This is a super confusing workflow which potentially
 * discards data, and we can't recover from it by re-issuing a CSRF token
 * since that would leave us vulnerable to CSRF attacks.
 *
 * Our options basically boil down to:
 *
 *   - Increase the CSRF window to something like 24 hours. This makes the CSRF
 *     implementation vaguely less secure and only mitigates the problem.
 *   - Code all forms to understand GET, POST and POST-with-invalid-CSRF. This
 *     is a huge undertaking and difficult to test properly since it is hard
 *     to remember to test the third phantom state.
 *   - Use JS to refresh the CSRF token.
 *
 * Since (1) mitigates rather than solving and (2) is a huge mess, this
 * behavior implements (3) and refreshes all the CSRF tokens on the page every
 * 55 minutes. This allows forms to remain valid indefinitely.
 */
JX.behavior('refresh-csrf', function(config) {

  var current_token = config.current;

  function refresh_csrf() {
    new JX.Request('/login/refresh/', function(r) {
      current_token = r.token;
      var inputs = JX.DOM.scry(document.body, 'input');
      for (var ii = 0; ii < inputs.length; ii++) {
        if (inputs[ii].name == config.tokenName) {
          inputs[ii].value = r.token;
        }
      }
    })
    .send();
  }

  // Refresh the CSRF tokens every 55 minutes.
  setInterval(refresh_csrf, 1000 * 60 * 55);

  // Additionally, add the CSRF token as an HTTP header to every AJAX request.
  JX.Request.listen('open', function(r) {
    var via = JX.$U(window.location).getRelativeURI();

    var xport = r.getTransport();

    xport.setRequestHeader(config.header, current_token);
    xport.setRequestHeader(config.viaHeader, via);
  });

  // Does this type of routable show the "Busy" spinner?
  var is_busy_type = function(type) {
    switch (type) {
      case 'workflow':
        return true;
    }

    return false;
  };

  // Does this type of routable show the "Loading" bar?
  var is_bar_type = function(type) {
    switch (type) {
      case 'content':
        return true;
    }

    return false;
  };


  var queue = {};
  var count = 0;
  var node;

  // Redraw the loading bar.
  var redraw_bar = function() {
    // If all requests have completed, hide the bar after a moment.
    if (!count) {
      if (node) {
        node.firstChild.style.width = '100%';
        setTimeout(JX.bind(null, JX.DOM.remove, node), 500);
      }
      node = null;
      queue = {};
      return;
    }

    // If we don't have the bar yet, draw it.
    if (!node) {
      node = JX.$N('div', {className: 'routing-bar'});
      document.body.appendChild(node);
      node.appendChild(JX.$N('div', {className: 'routing-progress'}));
    }

    // Update the bar progress.
    var done = 0;
    var total = 0;
    for (var k in queue) {
      total++;
      if (queue[k]) {
        done++;
      }
    }

    node.firstChild.style.width = (100 * (done / total)) + '%';
  };


  // Listen for queued requests.
  JX.Router.listen('queue', function(r) {
    var type = r.getType();

    if (is_bar_type(type)) {
      queue[r.getID()] = false;
      count++;
      redraw_bar();
    }

    if (is_busy_type(r.getType())) {
      JX.Busy.start();
    }
  });


  // Listen for completed requests.
  JX.Router.listen('done', function(r) {
    var type = r.getType();

    if (is_bar_type(type)) {
      queue[r.getID()] = true;
      count--;
      redraw_bar();
    }

    if (is_busy_type(r.getType())) {
      JX.Busy.done();
    }
  });


});
