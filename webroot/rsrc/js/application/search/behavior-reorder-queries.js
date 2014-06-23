/**
 * @provides javelin-behavior-search-reorder-queries
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phabricator-draggable-list
 */

JX.behavior('search-reorder-queries', function(config) {

  var root = JX.$(config.listID);

  var list = new JX.DraggableList('named-query', root)
    .setFindItemsHandler(function() {
      return JX.DOM.scry(root, 'li', 'named-query');
    });

  list.listen('didDrop', function(node) {
    var nodes = list.findItems();
    var order = [];
    var key;
    for (var ii = 0; ii < nodes.length; ii++) {
      key = JX.Stratcom.getData(nodes[ii]).queryKey;
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
