/**
 * @requires multirow-row-manager
 *           trigger-rule
 * @provides trigger-rule-editor
 * @javelin
 */

JX.install('TriggerRuleEditor', {

  construct: function(form_node) {
    this._formNode = form_node;
    this._rules = [];
    this._types = [];
  },

  members: {
    _formNode: null,
    _tableNode: null,
    _createButtonNode: null,
    _inputNode: null,
    _rowManager: null,
    _rules: null,
    _types: null,

    setTableNode: function(table) {
      this._tableNode = table;
      return this;
    },

    setCreateButtonNode: function(button) {
      this._createButtonNode = button;
      return this;
    },

    setInputNode: function(input) {
      this._inputNode = input;
      return this;
    },

    start: function() {
      var on_submit = JX.bind(this, this._submitForm);
      JX.DOM.listen(this._formNode, 'submit', null, on_submit);

      var manager = new JX.MultirowRowManager(this._tableNode);
      this._rowManager = manager;

      var on_remove = JX.bind(this, this._rowRemoved);
      manager.listen('row-removed', on_remove);

      var create_button = this._createButtonNode;
      var on_create = JX.bind(this, this._createRow);
      JX.DOM.listen(create_button, 'click', null, on_create);
    },

    _submitForm: function() {
      var values = [];
      for (var ii = 0; ii < this._rules.length; ii++) {
        var rule = this._rules[ii];
        values.push(rule.getValueForSubmit());
      }

      this._inputNode.value = JX.JSON.stringify(values);
    },

    _createRow: function(e) {
      var rule = this.newRule();
      this.addRule(rule);
      e.kill();
    },

    newRule: function() {
      // Create new rules with the first valid rule type.
      var types = this.getTypes();
      var type;
      for (var ii = 0; ii < types.length; ii++) {
        type = types[ii];
        if (!type.getIsSelectable()) {
          continue;
        }

        // If we make it here: this type is valid, so use it.
        break;
      }

      var default_value = type.getDefaultValue();

      return new JX.TriggerRule()
        .setType(type.getType())
        .setValue(default_value);
    },

    addRule: function(rule) {
      rule.setEditor(this);
      this._rules.push(rule);

      var manager = this._rowManager;

      var row = manager.addRow([]);
      var row_id = manager.getRowID(row);
      rule.setRowID(row_id);

      manager.updateRow(row_id, rule.newRowContent());
    },

    addType: function(type) {
      this._types.push(type);
      return this;
    },

    getTypes: function() {
      return this._types;
    },

    getType: function(type) {
      for (var ii = 0; ii < this._types.length; ii++) {
        if (this._types[ii].getType() === type) {
          return this._types[ii];
        }
      }

      return null;
    },

    _rowRemoved: function(row_id) {
      for (var ii = 0; ii < this._rules.length; ii++) {
        var rule = this._rules[ii];

        if (rule.getRowID() === row_id) {
          this._rules.splice(ii, 1);
          break;
        }
      }
    }

  }

});
