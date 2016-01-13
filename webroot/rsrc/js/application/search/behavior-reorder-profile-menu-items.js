/**
 * @provides javelin-behavior-reorder-profile-menu-items
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phabricator-draggable-list
 */

JX.behavior('reorder-profile-menu-items', function(config) {

  var root = JX.$(config.listID);

  var list = new JX.DraggableList('profile-menu-item', root)
    .setFindItemsHandler(function() {
      return JX.DOM.scry(root, 'li', 'profile-menu-item');
    });

  list.listen('didDrop', function(node) {
    var nodes = list.findItems();
    var order = [];
    var key;
    for (var ii = 0; ii < nodes.length; ii++) {
      key = JX.Stratcom.getData(nodes[ii]).key;
      if (key) {
        order.push(key);
      }
    }

    list.lock();
    JX.DOM.alterClass(node, 'drag-sending', true);

    new JX.Workflow(config.orderURI, {order: order.join()})
      .setHandler(function() {
        JX.DOM.alterClass(node, 'drag-sending', false);
        list.unlock();
      })
      .start();
  });

});
