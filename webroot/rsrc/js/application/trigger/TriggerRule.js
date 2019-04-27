/**
 * @provides trigger-rule
 * @javelin
 */

JX.install('TriggerRule', {

  construct: function() {
  },

  properties: {
    rowID: null,
    type: null,
    value: null,
    editor: null,
    isValidRule: true,
    invalidView: null
  },

  statics: {
    newFromDictionary: function(map) {
      return new JX.TriggerRule()
        .setType(map.type)
        .setValue(map.value)
        .setIsValidRule(map.isValidRule)
        .setInvalidView(map.invalidView);
    },
  },

  members: {
    _typeCell: null,
    _valueCell: null,
    _readValueCallback: null,

    newRowContent: function() {
      if (!this.getIsValidRule()) {
        var invalid_cell = JX.$N(
          'td',
          {
            colSpan: 2,
            className: 'invalid-cell'
          },
          JX.$H(this.getInvalidView()));

        return [invalid_cell];
      }

      var type_cell = this._getTypeCell();
      var value_cell = this._getValueCell();


      this._rebuildValueControl();

      return [type_cell, value_cell];
    },

    getValueForSubmit: function() {
      this._readValueFromControl();

      return {
        type: this.getType(),
        value: this.getValue()
      };
    },

    _getTypeCell: function() {
      if (!this._typeCell) {
        var editor = this.getEditor();
        var types = editor.getTypes();

        var options = [];
        for (var ii = 0; ii < types.length; ii++) {
          var type = types[ii];

          if (!type.getIsSelectable()) {
            continue;
          }

          options.push(
            JX.$N('option', {value: type.getType()}, type.getName()));
        }

        var control = JX.$N('select', {}, options);

        control.value = this.getType();

        var on_change = JX.bind(this, this._onTypeChange, control);
        JX.DOM.listen(control, 'change', null, on_change);

        var attributes = {
          className: 'type-cell'
        };

        this._typeCell = JX.$N('td', attributes, control);
      }

      return this._typeCell;
    },

    _onTypeChange: function(control) {
      var new_type = control.value;

      this.setType(new_type);

      // Before we build a new control, change the rule value to be appropriate
      // for the new rule type.

      // TODO: Currently, we set the rule value to the default value for the
      // type. This works most of the time, but if the user selects type "A",
      // makes a change to the value, selects type "B", then changes back to
      // type "A", it would be better to retain their edit. This is currently
      // difficult because of tokenizers: if you save their value, you get a
      // list of PHIDs which do not restore cleanly into tokens later.

      var editor = this.getEditor();
      var type = editor.getType(new_type);
      this.setValue(type.getDefaultValue());

      this._rebuildValueControl();
    },

    _getValueCell: function() {
      if (!this._valueCell) {
        var attributes = {
          className: 'value-cell'
        };

        this._valueCell = JX.$N('td', attributes);
      }

      return this._valueCell;
    },

    _rebuildValueControl: function() {
      var value_cell = this._getValueCell();

      var editor = this.getEditor();
      var type = editor.getType(this.getType());
      var control = type.getControl();

      var input = control.newInput(this);
      this._readValueCallback = input.get;

      JX.DOM.setContent(value_cell, input.node);
    },

    _readValueFromControl: function() {
      if (this._readValueCallback) {
        this.setValue(this._readValueCallback());
      }
    }

  }

});
