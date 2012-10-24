/**
 * @provides javelin-behavior-refresh-csrf
 * @requires javelin-request
 *           javelin-behavior
 *           javelin-dom
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
    r.getTransport().setRequestHeader(config.header, current_token);
    JX.Busy.start();
  });

  JX.Request.listen('finally', function(r) {
    JX.Busy.done();
  })
});
