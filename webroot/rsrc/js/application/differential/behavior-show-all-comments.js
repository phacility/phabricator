/**
 * @provides javelin-behavior-phabricator-show-all-transactions
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

/**
 * Automatically show older transactions if the user follows an anchor to a
 * transaction which is hidden by the "N older changes are hidden." shield.
 */
JX.behavior('phabricator-show-all-transactions', function(config) {

  var revealed = false;

  function get_hash() {
    return window.location.hash.replace(/^#/, '');
  }

  function hash_is_hidden() {
    var hash = get_hash();
    for (var ii = 0; ii < config.anchors.length; ii++) {
      if (config.anchors[ii] == hash) {
        return true;
      }
    }
    return false;
  }

  function reveal() {
    if (revealed) {
      return false;
    }

    JX.DOM.hide(JX.$(config.hideID));
    JX.DOM.show(JX.$(config.showID));
    revealed = true;

    return true;
  }

  function check_hash() {
    if (hash_is_hidden()) {
      if (reveal()) {
        try {
          var target = JX.$(get_hash());
          JX.DOM.scrollTo(target);
        } catch (ignored) {
          // We did our best.
        }
      }
    }
  }

  JX.DOM.listen(
    JX.$(config.linkID),
    'click',
    null,
    function (e) {
      e.kill();
      reveal();
    });

  JX.Stratcom.listen('hashchange', null, check_hash);
  check_hash();
});
