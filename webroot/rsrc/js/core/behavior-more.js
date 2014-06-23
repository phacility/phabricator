/**
 * @provides javelin-behavior-aphront-more
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 * @javelin
 */

JX.behavior('aphront-more', function() {
  JX.Stratcom.listen(
    'click',
    'aphront-more-view-show-more',
    function(e) {
      e.kill();

      var node = e.getNode('aphront-more-view');
      var more = JX.$H(e.getNodeData('aphront-more-view-show-more').more);

      // When the user clicks "More...", show more.
      JX.DOM.setContent(node, more);
    });
});
