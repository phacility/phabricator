/**
 * @provides javelin-behavior-reorder-applications
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phabricator-draggable-list
 */

JX.behavior('reorder-applications', function(config) {

  var root = JX.$(config.listID);

  var list = new JX.DraggableList('pinned-application', root)
    .setFindItemsHandler(function() {
      return JX.DOM.scry(root, 'li', 'pinned-application');
    });

  list.listen('didDrop', function(node) {
    var nodes = list.findItems();
    var order = [];
    var key;
    for (var ii = 0; ii < nodes.length; ii++) {
      key = JX.Stratcom.getData(nodes[ii]).applicationClass;
      if (key) {
        order.push(key);
      }
    }

    list.lock();
    JX.DOM.alterClass(node, 'drag-sending', true);

    new JX.Workflow(config.panelURI, {order: order.join()})
      .start();
  });

});
