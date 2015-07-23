/**
 * @requires multirow-row-manager
 *           javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-json
 *           phabricator-prefab
 * @provides herald-rule-editor
 * @javelin
 */

JX.install('HeraldRuleEditor', {
  construct : function(config) {
    var root = JX.$(config.root);
    this._root = root;

    JX.DOM.listen(
      root,
      'click',
      'create-condition',
      JX.bind(this, this._onnewcondition));

    JX.DOM.listen(
      root,
      'click',
      'create-action',
      JX.bind(this, this._onnewaction));

    JX.DOM.listen(root, 'change', null, JX.bind(this, this._onchange));
    JX.DOM.listen(root, 'submit', null, JX.bind(this, this._onsubmit));

    var conditionsTable = JX.DOM.find(root, 'table', 'rule-conditions');
    var actionsTable = JX.DOM.find(root, 'table', 'rule-actions');

    this._conditionsRowManager = new JX.MultirowRowManager(conditionsTable);
    this._conditionsRowManager.listen(
      'row-removed',
      JX.bind(this, function(row_id) {
          delete this._config.conditions[row_id];
        }));

    this._actionsRowManager = new JX.MultirowRowManager(actionsTable);
    this._actionsRowManager.listen(
      'row-removed',
      JX.bind(this, function(row_id) {
          delete this._config.actions[row_id];
        }));

    this._conditionGetters = {};
    this._conditionTypes = {};
    this._actionGetters = {};
    this._actionTypes = {};

    this._config = config;

    var conditions = this._config.conditions;
    this._config.conditions = [];

    var actions = this._config.actions;
    this._config.actions = [];

    this._renderConditions(conditions);
    this._renderActions(actions);
  },

  members : {
    _config : null,
    _root : null,
    _conditionGetters : null,
    _conditionTypes : null,
    _actionGetters : null,
    _actionTypes : null,
    _conditionsRowManager : null,
    _actionsRowManager : null,

    _onnewcondition : function(e) {
      this._newCondition();
      e.kill();
    },
    _onnewaction : function(e) {
      this._newAction();
      e.kill();
    },
    _onchange : function(e) {
      var target = e.getTarget();

      var row = e.getNode(JX.MultirowRowManager.getRowSigil());
      if (!row) {
        // Changing the "when all of / any of these..." dropdown.
        return;
      }

      if (JX.Stratcom.hasSigil(target, 'field-select')) {
        this._onfieldchange(row);
      } else if (JX.Stratcom.hasSigil(target, 'condition-select')) {
        this._onconditionchange(row);
      } else if (JX.Stratcom.hasSigil(target, 'action-select')) {
        this._onactionchange(row);
      }
    },
    _onsubmit : function() {
      var rule = JX.DOM.find(this._root, 'input', 'rule');

      var k;

      for (k in this._config.conditions) {
        this._config.conditions[k][2] = this._getConditionValue(k);
      }

      for (k in this._config.actions) {
        this._config.actions[k][1] = this._getActionTarget(k);
      }
      rule.value = JX.JSON.stringify({
        conditions: this._config.conditions,
        actions: this._config.actions
      });
    },

    _getConditionValue : function(id) {
      if (this._conditionGetters[id]) {
        return this._conditionGetters[id]();
      }
      return this._config.conditions[id][2];
    },

    _getActionTarget : function(id) {
      if (this._actionGetters[id]) {
        return this._actionGetters[id]();
      }
      return this._config.actions[id][1];
    },

    _onactionchange : function(r) {
      var target = JX.DOM.find(r, 'select', 'action-select');
      var row_id = this._actionsRowManager.getRowID(r);

      this._config.actions[row_id][0] = target.value;

      var target_cell = JX.DOM.find(r, 'td', 'target-cell');
      var target_input = this._renderTargetInputForRow(row_id);

      JX.DOM.setContent(target_cell, target_input);
    },
    _onfieldchange : function(r) {
      var target = JX.DOM.find(r, 'select', 'field-select');
      var row_id = this._actionsRowManager.getRowID(r);

      this._config.conditions[row_id][0] = target.value;

      var condition_cell = JX.DOM.find(r, 'td', 'condition-cell');
      var condition_select = this._renderSelect(
        this._selectKeys(
          this._config.info.conditions,
          this._config.info.conditionMap[target.value]),
        this._config.conditions[row_id][1],
        'condition-select');

      JX.DOM.setContent(condition_cell, condition_select);

      this._onconditionchange(r);

      var condition_name = this._config.conditions[row_id][1];
      if (condition_name == 'unconditionally') {
        JX.DOM.hide(condition_select);
      }
    },
    _onconditionchange : function(r) {
      var target = JX.DOM.find(r, 'select', 'condition-select');
      var row_id = this._conditionsRowManager.getRowID(r);

      this._config.conditions[row_id][1] = target.value;

      var value_cell = JX.DOM.find(r, 'td', 'value-cell');
      var value_input = this._renderValueInputForRow(row_id);
      JX.DOM.setContent(value_cell, value_input);
    },

    _renderTargetInputForRow : function(row_id) {
      var action = this._config.actions[row_id];
      var type = this._config.info.targets[action[0]];

      var input = this._buildInput(type);
      var node = input[0];
      var get_fn = input[1];
      var set_fn = input[2];

      if (node) {
        JX.Stratcom.addSigil(node, 'action-target');
      }

      var old_type = this._actionTypes[row_id];
      if (old_type == type || !old_type) {
        set_fn(this._getActionTarget(row_id));
      }

      this._actionTypes[row_id] = type;
      this._actionGetters[row_id] = get_fn;

      return node;
    },

    _buildInput : function(type) {
      var input;
      var get_fn;
      var set_fn;
      switch (type) {
        case 'rule':
          input = this._renderSelect(this._config.template.rules);
          get_fn = function() { return input.value; };
          set_fn = function(v) { input.value = v; };
          break;
        case 'email':
        case 'user':
        case 'repository':
        case 'tag':
        case 'package':
        case 'project':
        case 'userorproject':
        case 'buildplan':
        case 'taskpriority':
        case 'taskstatus':
        case 'legaldocuments':
        case 'applicationemail':
        case 'space':
          var tokenizer = this._newTokenizer(type);
          input = tokenizer[0];
          get_fn = tokenizer[1];
          set_fn = tokenizer[2];
          break;
        case 'none':
          input = '';
          get_fn = JX.bag;
          set_fn = JX.bag;
          break;
        case 'contentsource':
        case 'flagcolor':
        case 'value-ref-type':
        case 'value-ref-change':
          input = this._renderSelect(this._config.select[type].options);
          get_fn = function() { return input.value; };
          set_fn = function(v) { input.value = v; };
          set_fn(this._config.select[type]['default']);
          break;
        default:
          input = JX.$N('input', {type: 'text'});
          get_fn = function() { return input.value; };
          set_fn = function(v) { input.value = v; };
          break;
      }

      return [input, get_fn, set_fn];
    },

    _renderValueInputForRow : function(row_id) {
      var cond = this._config.conditions[row_id];
      var type = this._config.info.values[cond[0]][cond[1]];

      var input = this._buildInput(type);
      var node = input[0];
      var get_fn = input[1];
      var set_fn = input[2];

      if (node) {
        JX.Stratcom.addSigil(node, 'condition-value');
      }

      var old_type = this._conditionTypes[row_id];
      if (old_type == type || !old_type) {
        set_fn(this._getConditionValue(row_id));
      }

      this._conditionTypes[row_id] = type;
      this._conditionGetters[row_id] = get_fn;

      return node;
    },

    _newTokenizer : function(type) {
      var tokenizerConfig = {
        src : this._config.template.source[type].uri,
        placeholder: this._config.template.source[type].placeholder,
        browseURI: this._config.template.source[type].browseURI,
        icons : this._config.template.icons,
        username : this._config.username
      };

      var build = JX.Prefab.newTokenizerFromTemplate(
        this._config.template.markup,
        tokenizerConfig);
      build.tokenizer.start();

      return [
        build.node,
        function() {
          return build.tokenizer.getTokens();
        },
        function(map) {
          for (var k in map) {
            build.tokenizer.addToken(k, map[k]);
          }
        }];
    },
    _selectKeys : function(map, keys) {
      var r = {};
      for (var ii = 0; ii < keys.length; ii++) {
        r[keys[ii]] = map[keys[ii]];
      }
      return r;
    },
    _renderConditions : function(conditions) {
      for (var k in conditions) {
        this._newCondition(conditions[k]);
      }
    },
    _newCondition : function(data) {
      var row = this._conditionsRowManager.addRow([]);
      var row_id = this._conditionsRowManager.getRowID(row);
      this._config.conditions[row_id] = data || [null, null, ''];
      var r = this._conditionsRowManager.updateRow(
        row_id,
        this._renderCondition(row_id));

      this._onfieldchange(r);
    },
    _renderCondition : function(row_id) {
      var field_select = this._renderSelect(
        this._config.info.fields,
        this._config.conditions[row_id][0],
        'field-select');
      var field_cell = JX.$N('td', {sigil: 'field-cell'}, field_select);

      var condition_cell = JX.$N('td', {sigil: 'condition-cell'});
      var value_cell = JX.$N('td', {className : 'value', sigil: 'value-cell'});

      return [field_cell, condition_cell, value_cell];
    },
    _renderActions : function(actions) {
      for (var k in actions) {
        this._newAction(actions[k]);
        delete actions[k];
      }
    },
    _newAction : function(data) {
      data = data || [];
      var temprow = this._actionsRowManager.addRow([]);
      var row_id = this._actionsRowManager.getRowID(temprow);
      this._config.actions[row_id] = data;
      var r = this._actionsRowManager.updateRow(row_id,
                                                this._renderAction(data));
      this._onactionchange(r);
    },
    _renderAction : function(action) {
      var action_select = this._renderSelect(
        this._config.info.actions,
        action[0],
        'action-select');
      var action_cell = JX.$N('td', {sigil: 'action-cell'}, action_select);

      var target_cell = JX.$N(
        'td',
        {className : 'target', sigil : 'target-cell'});

      return [action_cell, target_cell];
    },
    _renderSelect : function(map, selected, sigil) {
      var attrs = {
        sigil : sigil
      };
      return JX.Prefab.renderSelect(map, selected, attrs);
    }
  }
});
