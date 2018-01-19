/**
 * @provides javelin-behavior-bulk-editor
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           multirow-row-manager
 *           javelin-json
 *           phuix-form-control-view
 */

JX.behavior('bulk-editor', function(config) {

  var root = JX.$(config.rootNodeID);
  var editor_table = JX.DOM.find(root, 'table', 'bulk-actions');

  var manager = new JX.MultirowRowManager(editor_table);
  var action_rows = [];

  var option_map = {};
  var option_order = [];
  var spec_map = {};

  for (var ii = 0; ii < config.edits.length; ii++) {
    var edit = config.edits[ii];

    option_map[edit.xaction] = edit.label;
    option_order.push(edit.xaction);

    spec_map[edit.xaction] = edit;
  }

  function renderRow() {
    var action_select = new JX.PHUIXFormControl()
      .setControl('optgroups', config.optgroups)
      .getRawInputNode();

    var cell = JX.$N('td', {className: 'bulk-edit-input'});
    var vfunc = null;

    function update() {
      var spec = spec_map[action_select.value];
      var control = spec.control;

      var phuix = new JX.PHUIXFormControl()
        .setControl(control.type, control.spec);

      JX.DOM.setContent(cell, phuix.getRawInputNode());

      vfunc = JX.bind(phuix, phuix.getValue);
    }

    JX.DOM.listen(action_select, 'change', null, update);
    update();

    return {
      nodes : [JX.$N('td', {}, action_select), cell],
      dataCallback : function() {
        return {
          type: action_select.value,
          value: vfunc()
        };
      }
    };
  }

  function onaddaction(e) {
    e.kill();
    addRow({});
  }

  function addRow(info) {
    var data = renderRow(info);
    var row = manager.addRow(data.nodes);
    var id = manager.getRowID(row);

    action_rows[id] = data.dataCallback;
  }

  function onsubmit() {
    var input = JX.$(config.inputNodeID);

    var actions = [];
    for (var k in action_rows) {
      actions.push(action_rows[k]());
    }

    input.value = JX.JSON.stringify(actions);
  }

  addRow({});

  JX.DOM.listen(
    root,
    'click',
    'add-action',
    onaddaction);

  JX.DOM.listen(
    root,
    'submit',
    null,
    onsubmit);

  manager.listen(
    'row-removed',
    function(row_id) {
      delete action_rows[row_id];
    });

});
