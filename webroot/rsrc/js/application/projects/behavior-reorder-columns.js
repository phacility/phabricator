/**
 * @provides javelin-behavior-reorder-columns
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phabricator-draggable-list
 */

JX.behavior('reorder-columns', function(config) {

  var root = JX.$(config.listID);

  var list = new JX.DraggableList('board-column', root)
    .setFindItemsHandler(function() {
      return JX.DOM.scry(root, 'li', 'board-column');
    });

  list.listen('didDrop', function(node) {
    var nodes = list.findItems();

    var node_data = JX.Stratcom.getData(node);

    // Find the column sequence of the previous node.
    var sequence = null;
    var data;
    for (var ii = 0; ii < nodes.length; ii++) {
      data = JX.Stratcom.getData(nodes[ii]);
      if (data.columnPHID === node_data.columnPHID) {
        break;
      }
      sequence = data.columnSequence;
    }

    list.lock();
    JX.DOM.alterClass(node, 'drag-sending', true);

    var parameters = {
      columnPHID: node_data.columnPHID,
      sequence: (sequence === null) ? 0 : (parseInt(sequence, 10) + 1)
    };

    new JX.Workflow(config.reorderURI, parameters)
      .setHandler(function(r) {

        // Adjust metadata for the new sequence numbers.
        for (var ii = 0; ii < nodes.length; ii++) {
          var data = JX.Stratcom.getData(nodes[ii]);
          data.columnSequence = r.sequenceMap[data.columnPHID];
        }

        list.unlock();
        JX.DOM.alterClass(node, 'drag-sending', false);
      })
      .start();
  });

});
