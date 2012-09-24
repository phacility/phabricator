/**
 * @provides javelin-behavior-differential-user-select
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('differential-user-select', function() {

  var unselectable;

  function isOnRight(node) {
    return node.previousSibling &&
      node.parentNode.firstChild != node.previousSibling;
  }

  JX.Stratcom.listen(
    'mousedown',
    null,
    function(e) {
      var key = 'differential-unselectable';
      if (unselectable) {
        JX.DOM.alterClass(unselectable, key, false);
      }
      var diff = e.getNode('differential-diff');
      var td = e.getNode('tag:td');
      if (diff && td && isOnRight(td)) {
        unselectable = diff;
        JX.DOM.alterClass(diff, key, true);
      }
    });

});
