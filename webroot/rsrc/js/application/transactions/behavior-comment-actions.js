/**
 * @provides javelin-behavior-comment-actions
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           phuix-form-control-view
 *           phuix-icon-view
 */

JX.behavior('comment-actions', function(config) {
  var action_map = config.actions;

  var action_node = JX.$(config.actionID);
  var form_node = JX.$(config.formID);
  var input_node = JX.$(config.inputID);

  var rows = {};

  JX.DOM.listen(action_node, 'change', null, function() {
    var options = action_node.options;
    var option;

    var selected = action_node.value;
    action_node.value = '+';

    for (var ii = 0; ii < options.length; ii++) {
      option = options[ii];
      if (option.value == selected) {
        add_row(option);
        break;
      }
    }
  });

  JX.DOM.listen(form_node, 'submit', null, function() {
    var data = [];

    for (var k in rows) {
      data.push({
        type: k,
        value: rows[k].getValue()
      });
    }

    input_node.value = JX.JSON.stringify(data);
  });

  function add_row(option) {
    var action = action_map[option.value];
    if (!action) {
      return;
    }

    option.disabled = true;

    var icon = new JX.PHUIXIconView()
      .setIcon('fa-times-circle');
    var remove = JX.$N('a', {href: '#'}, icon.getNode());

    var control = new JX.PHUIXFormControl()
      .setLabel(action.label)
      .setError(remove)
      .setControl('tokenizer', action.spec);
    var node = control.getNode();

    rows[action.key] = control;

    JX.DOM.listen(remove, 'click', null, function(e) {
      e.kill();
      JX.DOM.remove(node);
      delete rows[action.key];
      option.disabled = false;
    });

    // TODO: Grotesque.
    action_node
      .parentNode
      .parentNode
      .parentNode
      .insertBefore(node, action_node.parentNode.parentNode.nextSibling);
  }

});
