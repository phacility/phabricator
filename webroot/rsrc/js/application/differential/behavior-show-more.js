/**
 * @provides javelin-behavior-differential-show-more
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-workflow
 *           javelin-util
 *           javelin-stratcom
 *           changeset-view-manager
 */

JX.behavior('differential-show-more', function() {

  JX.Stratcom.listen(
    'click',
    'show-more',
    function(e) {
      e.kill();

      var changeset = e.getNode('differential-changeset');
      var view = JX.ChangesetViewManager.getForNode(changeset);
      var data = e.getNodeData('show-more');
      var target = e.getNode('context-target');

      view.loadContext(data.range, target);
    });

});
