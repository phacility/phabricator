/**
 * @provides javelin-behavior-maniphest-batch-editor
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-prefab
 *           multirow-row-manager
 *           javelin-json
 */

JX.behavior('maniphest-batch-editor', function(config) {
  var root = JX.$(config.root);
  var editor_table = JX.DOM.find(root, 'table', 'maniphest-batch-actions');
  var manager = new JX.MultirowRowManager(editor_table);
  var action_rows = [];

  function renderRow() {
    var action_select = JX.Prefab.renderSelect(
      {
        'add_project': 'Add Projects',
        'remove_project' : 'Remove Projects',
        'priority': 'Change Priority',
        'status': 'Change Status',
        'add_comment': 'Comment',
        'assign': 'Assign',
        'add_ccs' : 'Add CCs',
        'remove_ccs' : 'Remove CCs',
        'space': 'Shift to Space'
      });

    var proj_tokenizer = build_tokenizer(config.sources.project);
    var owner_tokenizer = build_tokenizer(config.sources.owner);
    var cc_tokenizer = build_tokenizer(config.sources.cc);
    var space_tokenizer = build_tokenizer(config.sources.spaces);

    var priority_select = JX.Prefab.renderSelect(config.priorityMap);
    var status_select = JX.Prefab.renderSelect(config.statusMap);
    var comment_input = JX.$N('input', {style: {width: '100%'}});

    var cell = JX.$N('td', {className: 'batch-editor-input'});
    var vfunc = null;

    function update() {
      switch (action_select.value) {
        case 'add_project':
        case 'remove_project':
          JX.DOM.setContent(cell, proj_tokenizer.template);
          vfunc = function() {
            return JX.keys(proj_tokenizer.object.getTokens());
          };
          break;
        case 'add_ccs':
        case 'remove_ccs':
          JX.DOM.setContent(cell, cc_tokenizer.template);
          vfunc = function() {
            return JX.keys(cc_tokenizer.object.getTokens());
          };
          break;
        case 'assign':
          JX.DOM.setContent(cell, owner_tokenizer.template);
          vfunc = function() {
            return JX.keys(owner_tokenizer.object.getTokens());
          };
          break;
        case 'space':
          JX.DOM.setContent(cell, space_tokenizer.template);
          vfunc = function() {
            return JX.keys(space_tokenizer.object.getTokens());
          };
          break;
        case 'add_comment':
          JX.DOM.setContent(cell, comment_input);
          vfunc = function() {
            return comment_input.value;
          };
          break;
        case 'priority':
          JX.DOM.setContent(cell, priority_select);
          vfunc = function() { return priority_select.value; };
          break;
        case 'status':
          JX.DOM.setContent(cell, status_select);
          vfunc = function() { return status_select.value; };
          break;
      }
    }

    JX.DOM.listen(action_select, 'change', null, update);
    update();

    return {
      nodes : [JX.$N('td', {}, action_select), cell],
      dataCallback : function() {
        return {
          action: action_select.value,
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
    var input = JX.$(config.input);

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

  function build_tokenizer(tconfig) {
    var built = JX.Prefab.newTokenizerFromTemplate(
      config.tokenizerTemplate,
      JX.copy({}, tconfig));
    built.tokenizer.start();

    return {
      object: built.tokenizer,
      template: built.node
    };
  }

});
