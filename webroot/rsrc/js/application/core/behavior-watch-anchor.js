/**
 * @provides javelin-behavior-phabricator-watch-anchor
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phabricator-watch-anchor', function() {

  var highlighted;

  function highlight() {
    highlighted && JX.DOM.alterClass(highlighted, 'anchor-target', false);
    try {
      highlighted = JX.$('anchor-' + window.location.hash.replace('#', ''));
    } catch (ex) {
      highlighted = null;
    }
    highlighted && JX.DOM.alterClass(highlighted, 'anchor-target', true);
  }

  // Defer invocation so other listeners can update the document.
  var fn = function() {
    setTimeout(highlight, 0);
  };
  JX.Stratcom.listen('hashchange', null, fn);
  fn();
});
