/**
 * @provides javelin-behavior-differential-show-all-comments
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('differential-show-all-comments', function(config) {

  var shown = false;
  function reveal(node) {
    if (shown) {
      return false;
    }
    shown = true;
    node = node || JX.DOM.find(
      document.body,
      'div',
      'differential-all-comments-container');
    if (node) {
      JX.DOM.setContent(node, JX.$H(config.markup));
    }
    return true;
  }

  // Reveal the hidden comments if the user clicks "Show All Comments", or if
  // there's an anchor in the URL, since we don't want to link to "#comment-3"
  // and have it collapsed.

  function at_comment_hash() {
    return window.location.hash && window.location.hash.match(/comment/);
  }

  if (at_comment_hash()) {
    reveal();
  } else {
    JX.Stratcom.listen(
      'hashchange',
      null,
      function(e) {
        if (at_comment_hash() && reveal()) {
          try {
            var target = JX.$(window.location.hash.replace(/^#/, ''));
            window.scrollTo(0, target.offsetTop);
          } catch (e) {
          }
        }
      });
  }

  JX.Stratcom.listen(
    'click',
    'differential-show-all-comments',
    function(e) {
      reveal(e.getNode('differential-all-comments-container'));
      e.kill();
    });

});
