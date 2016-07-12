/**
 * @provides javelin-behavior-editengine-reorder-configs
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phabricator-draggable-list
 */

JX.behavior('editengine-reorder-configs', function(config) {

  var root = JX.$(config.listID);

  var list = new JX.DraggableList('editengine-form-config', root)
    .setFindItemsHandler(function() {
      return JX.DOM.scry(root, 'li', 'editengine-form-config');
    });

  list.listen('didDrop', function() {
    var nodes = list.findItems();

    var data;
    var keys = [];
    for (var ii = 0; ii < nodes.length; ii++) {
      data = JX.Stratcom.getData(nodes[ii]);
      keys.push(data.formIdentifier);
    }

    JX.$(config.inputID).value = keys.join(',');
  });

});
