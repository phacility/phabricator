/**
 * @provides javelin-behavior-phabricator-watch-anchor
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-util
 *           javelin-dom
 */

JX.behavior('phabricator-watch-anchor', function() {

  var highlighted;

  function highlight() {
    highlighted && JX.DOM.alterClass(highlighted, 'anchor-target', false);
    try {
      highlighted = JX.$('anchor-' + window.location.hash.replace('#', ''));
      JX.DOM.alterClass(highlighted, 'anchor-target', true);
    } catch (ex) {
      if (ex === JX.$.NotFound) {
        highlighted = null;
      } else {
        throw ex;
      }
    }
  }

  JX.Stratcom.listen(
    'hashchange',
    null,
    // Defer invocation so other listeners can update the document.
    function() { JX.defer(highlight); });

  JX.defer(highlight);
});
