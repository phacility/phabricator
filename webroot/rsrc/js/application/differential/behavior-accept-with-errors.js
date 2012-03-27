/**
 * @provides javelin-behavior-differential-accept-with-errors
 * @requires javelin-behavior
 *           javelin-dom
 */

JX.behavior('differential-accept-with-errors', function(config) {

  function toggleWarning(control) {
    if (control) {
      JX.DOM.hide(JX.$(control));
      JX.DOM.listen(
        JX.$(config.select),
        'change',
        null,
        function(e) {
          if (JX.$(config.select).value == 'accept') {
            JX.DOM.show(JX.$(control));
          } else {
            JX.DOM.hide(JX.$(control));
          }
        });
    }
  }

  toggleWarning(config.warnings);
});
