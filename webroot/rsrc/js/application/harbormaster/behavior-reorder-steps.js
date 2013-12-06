/**
 * @provides javelin-behavior-harbormaster-reorder-steps
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phabricator-draggable-list
 */

JX.behavior('harbormaster-reorder-steps', function(config) {

  var root = JX.$(config.listID);

  var list = new JX.DraggableList('build-step', root)
    .setFindItemsHandler(function() {
      return JX.DOM.scry(root, 'li', 'build-step');
    });

  list.listen('didDrop', function(node, after) {
    var nodes = list.findItems();
    var order = [];
    var key;
    for (var ii = 0; ii < nodes.length; ii++) {
      key = JX.Stratcom.getData(nodes[ii]).stepID;
      if (key) {
        order.push(key);
      }
    }

    list.lock();
    JX.DOM.alterClass(node, 'drag-sending', true);

    new JX.Workflow(config.orderURI, {order: order.join()})
      .setHandler(function(e) {
        JX.DOM.alterClass(node, 'drag-sending', false);
        list.unlock();
      })
      .start();
  });

});

