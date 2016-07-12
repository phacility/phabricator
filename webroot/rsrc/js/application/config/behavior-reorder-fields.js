/**
 * @provides javelin-behavior-config-reorder-fields
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-json
 *           phabricator-draggable-list
 */

JX.behavior('config-reorder-fields', function(config) {

  var fields = config.fields;
  var root = JX.$(config.listID);

  var list = new JX.DraggableList('field-spec', root)
    .setFindItemsHandler(function() {
      return JX.DOM.scry(root, 'li', 'field-spec');
    });

  list.listen('didDrop', function() {
    write_state_to_form();
  });

  JX.DOM.listen(root, 'click', 'field-spec-toggle', function(e) {
    e.kill();

    var key = e.getNodeData('field-spec').fieldKey;
    fields[key].disabled = !fields[key].disabled;

    JX.DOM.replace(
      e.getNode('field-spec'),
      JX.$H(
        fields[key].disabled ?
          fields[key].disabledMarkup :
          fields[key].enabledMarkup));

    write_state_to_form();
  });

  var write_state_to_form = function() {
    var nodes = list.findItems();
    var order = [];
    var key;
    for (var ii = 0; ii < nodes.length; ii++) {
      key = JX.Stratcom.getData(nodes[ii]).fieldKey;
      if (key) {
        order.push({
          key: key,
          disabled: fields[key].disabled
        });
      }
    }

    JX.$(config.inputID).value = JX.JSON.stringify(order);
  };

  write_state_to_form();
});
