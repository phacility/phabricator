/**
 * @provides javelin-behavior-policy-rule-editor
 * @requires javelin-behavior
 *           multirow-row-manager
 *           javelin-dom
 *           javelin-util
 *           phabricator-prefab
 *           javelin-json
 */
JX.behavior('policy-rule-editor', function(config) {
  var root = JX.$(config.rootID);
  var rows = [];
  var data = {};

  JX.DOM.listen(
    root,
    'click',
    'create-rule',
    function(e) {
      e.kill();
      new_rule(config.defaultRule);
    });

  JX.DOM.listen(
    root,
    'change',
    'rule-select',
    function(e) {
      e.kill();

      var row = e.getNode(JX.MultirowRowManager.getRowSigil());
      var row_id = rules_manager.getRowID(row);

      data[row_id].rule = data[row_id].ruleNode.value;
      data[row_id].value = null;

      redraw(row_id);
    });

  JX.DOM.listen(
    JX.DOM.findAbove(root, 'form'),
    ['submit', 'didWorkflowSubmit'],
    null,
    function(e) {
      var rules = JX.DOM.find(e.getNode('tag:form'), 'input', 'rules');

      var value = [];
      for (var ii = 0; ii < rows.length; ii++) {
        var row_data = data[rows[ii]];

        var row_dict = {
          action: row_data.actionNode.value,
          rule: row_data.rule,
          value: row_data.getValue()
        };

        value.push(row_dict);
      }

      rules.value = JX.JSON.stringify(value);
    });


  var rules_table = JX.DOM.find(root, 'table', 'rules');
  var rules_manager = new JX.MultirowRowManager(rules_table);
  rules_manager.listen(
    'row-removed',
    function(row_id) {
      delete data[row_id];
      for (var ii = 0; ii < rows.length; ii++) {
        if (rows[ii] == row_id) {
          rows.splice(ii, 1);
          break;
        }
      }
    });


  function new_rule(spec) {
    var row = rules_manager.addRow([]);
    var row_id = rules_manager.getRowID(row);

    rows.push(row_id);
    data[row_id] = JX.copy({}, spec);

    redraw(row_id);
  }

  function redraw(row_id) {
    var action_content = JX.Prefab.renderSelect(
      config.actions,
      data[row_id].action);
    data[row_id].actionNode = action_content;
    var action_cell = JX.$N('td', {className: 'action-cell'}, action_content);

    var rule_content = JX.Prefab.renderSelect(
      config.rules,
      data[row_id].rule,
      {sigil: 'rule-select'});
    data[row_id].ruleNode = rule_content;
    var rule_cell = JX.$N('td', {className: 'rule-cell'}, rule_content);

    var input = render_input(data[row_id].rule, null);

    var value_content = input.node;
    data[row_id].getValue = input.get;
    input.set(data[row_id].value);

    var value_cell = JX.$N('td', {className: 'value-cell'}, value_content);

    rules_manager.updateRow(row_id, [action_cell, rule_cell, value_cell]);
  }

  function render_input(rule, value) {
    var node, get_fn, set_fn;
    var type = config.types[rule];
    var template = config.templates[rule];

    switch (type) {
      case 'tokenizer':
        var options = {
          src: template.uri,
          placeholder: template.placeholder,
          browseURI: template.browseURI,
          limit: template.limit
        };

        var build = JX.Prefab.newTokenizerFromTemplate(
          template.markup,
          options);

        node = build.node;

        var tokenizer = build.tokenizer;
        tokenizer.start();

        get_fn = function() { return JX.keys(tokenizer.getTokens()); };
        set_fn = function(map) {
          if (!map) {
            return;
          }
          for (var k in map) {
            tokenizer.addToken(k, map[k]);
          }
        };
        break;
      case 'none':
        node = null;
        get_fn = JX.bag;
        set_fn = JX.bag;
        break;
      case 'select':
        node = JX.Prefab.renderSelect(
          config.templates[rule].options,
          value);
        get_fn = function() { return node.value; };
        set_fn = function(v) { node.value = v; };
        break;
      default:
      case 'text':
        node = JX.$N('input', {type: 'text'});
        get_fn = function() { return node.value; };
        set_fn = function(v) { node.value = v; };
        break;
    }

    return {
      node: node,
      get: get_fn,
      set: set_fn
    };
  }

  for (var ii = 0; ii < config.data.length; ii++) {
    new_rule(config.data[ii]);
  }

});
