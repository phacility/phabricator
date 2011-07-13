/**
 * @provides javelin-behavior-refresh-csrf
 * @requires javelin-request
 *           javelin-behavior
 *           javelin-dom
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

  function refresh_csrf() {
    new JX.Request('/login/refresh/', function(r) {
        var inputs = JX.DOM.scry(document.body, 'input');
        for (var ii = 0; ii < inputs.length; ii++) {
          if (inputs[ii].name == '__csrf__') {
            inputs[ii].value = r.token;
          }
        }
      })
      .send();
  }

  // Refresh the CSRF tokens every 55 minutes.
  setInterval(refresh_csrf, 1000 * 60 * 55);
});
