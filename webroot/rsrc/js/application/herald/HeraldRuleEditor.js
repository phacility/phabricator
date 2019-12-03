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
      var spec = this._config.info.valueMap[type];

      var input;
      var get_fn;
      var set_fn;
      switch (spec.control) {
        case 'herald.control.none':
          input = null;
          get_fn = JX.bag;
          set_fn = JX.bag;
          break;
        case 'herald.control.text':
          input = JX.$N('input', {type: 'text'});
          get_fn = function() { return input.value; };
          set_fn = function(v) { input.value = v; };
          break;
        case 'herald.control.remarkup':
          input = JX.$N('textarea');
          get_fn = function() { return input.value; };
          set_fn = function(v) { input.value = v; };
          break;
        case 'herald.control.select':
          var options;

          // NOTE: This is a hacky special case for "Another Herald Rule",
          // which we don't currently generate normal options for.

          if (spec.key == 'select.rule') {
            options = this._config.template.rules;
          } else {
            options = spec.template.options;
          }

          input = this._renderSelect(options);
          get_fn = function() { return input.value; };
          set_fn = function(v) { input.value = v; };
          if (spec.template.default) {
            set_fn(spec.template.default);
          }
          break;
        case 'herald.control.tokenizer':
          var tokenizer = this._newTokenizer(spec.template.tokenizer);
          input = tokenizer[0];
          get_fn = tokenizer[1];
          set_fn = tokenizer[2];
          break;
        default:
          JX.$E('No rules to build control "' + spec.control + '".');
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

    _newTokenizer : function(spec) {
      var tokenizerConfig = {
        src: spec.datasourceURI,
        placeholder: spec.placeholder,
        browseURI: spec.browseURI,
        limit: spec.limit
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
            var v = map[k];

            // The control value may be set from wire values from the server,
            // or a transformed value from another control, or a bare string
            // value from another control.
            if (typeof v == 'string') {
              v = v;
            } else if (!v.hasOwnProperty('id')) {
              v = JX.Prefab.transformDatasourceResults(v);
            }

            build.tokenizer.addToken(k, v);
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

      var default_condition = [
        this._config.default.field,
        this._config.default.condition,
        null
      ];
      this._config.conditions[row_id] = data || default_condition;

      var r = this._conditionsRowManager.updateRow(
        row_id,
        this._renderCondition(row_id));

      this._onfieldchange(r);
    },
    _renderCondition : function(row_id) {
      var groups = this._config.info.fields;

      var attrs = {
        sigil: 'field-select'
      };

      var field_select = this._renderGroupSelect(
        groups,
        attrs,
        this._config.conditions[row_id][0]);

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

    _renderGroupSelect: function(groups, attrs, value) {
      var optgroups = [];
      for (var ii = 0; ii < groups.length; ii++) {
        var group = groups[ii];
        var options = [];
        for (var k in group.options) {
          var option = group.options[k];

          var name = option.name;
          var available = option.available;

          // See T7961. If the option is not marked as "available", we only
          // include it in the dropdown if the dropdown already has it as a
          // value. We want to hide options provided by applications which are
          // not installed, but do not want to break existing rules.

          if (available || (k === value)) {
            options.push(JX.$N('option', {value: k}, name));
          }
        }
        if (options.length) {
          optgroups.push(JX.$N('optgroup', {label: group.label}, options));
        }
      }

      var select = JX.$N('select', attrs, optgroups);

      if (value !== undefined) {
        select.value = value;
      }

      return select;
    },

    _newAction : function(data) {
      var default_action = [
        this._config.default.action,
        null
      ];

      data = data || default_action;
      var temprow = this._actionsRowManager.addRow([]);
      var row_id = this._actionsRowManager.getRowID(temprow);
      this._config.actions[row_id] = data;
      var r = this._actionsRowManager.updateRow(row_id,
                                                this._renderAction(data));
      this._onactionchange(r);
    },

    _renderAction : function(action) {
      var groups = this._config.info.actions;
      var attrs = {
        sigil: 'action-select'
      };

      var action_select = this._renderGroupSelect(
        groups,
        attrs,
        action[0]);

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
