/**
 * @provides javelin-behavior-differential-accept-with-errors
 * @requires javelin-behavior
 *           javelin-dom
 */

JX.behavior('differential-accept-with-errors', function(config) {
  if (config.warnings) {
    toggleWarning();
    JX.DOM.listen(
      JX.$(config.select),
      'change',
      null,
      toggleWarning);
  }

  function toggleWarning() {
    if (JX.$(config.select).value == 'accept') {
      JX.DOM.show(JX.$(config.warnings));
    } else {
      JX.DOM.hide(JX.$(config.warnings));
    }
  }

});
