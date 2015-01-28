/**
 * @provides javelin-behavior-phabricator-watch-anchor
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
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
  function defer_highlight() {
    setTimeout(highlight, 0);
  }

  // In some cases, we link to an anchor but the anchor target ajaxes in
  // later. If it pops in within the first few seconds, jump to it.
  function try_anchor() {
    var anchor = window.location.hash.replace('#', '');
    try {
      // If the anchor exists, assume the browser handled the jump.
      if (anchor) {
        JX.$(anchor);
      }
      defer_highlight();
    } catch (e) {
      var n = 50;
      var try_anchor_again = function () {
        try {
          var node = JX.$(anchor);
          var pos = JX.Vector.getPosWithScroll(node);
          JX.DOM.scrollToPosition(0, pos.y - 60);
          defer_highlight();
        } catch (e) {
          if (n--) {
            setTimeout(try_anchor_again, 100);
          }
        }
      };
      try_anchor_again();
    }
  }

  JX.Stratcom.listen('hashchange', null, try_anchor);
  try_anchor();

});
