/**
 * @provides phuix-form-control-view
 * @requires javelin-install
 *           javelin-dom
 */

JX.install('PHUIXFormControl', {

  members: {
    _node: null,
    _labelNode: null,
    _errorNode: null,
    _inputNode: null,
    _valueSetCallback: null,
    _valueGetCallback: null,

    setLabel: function(label) {
      JX.DOM.setContent(this._getLabelNode(), label);
      return this;
    },

    setError: function(error) {
      JX.DOM.setContent(this._getErrorNode(), error);
      return this;
    },

    setControl: function(type, spec) {
      var node = this._getInputNode();

      var input;
      switch (type) {
        case 'tokenizer':
          input = this._newTokenizer(spec);
          break;
        case 'select':
          input = this._newSelect(spec);
          break;
        case 'points':
          input = this._newPoints(spec);
          break;
        case 'optgroups':
          input = this._newOptgroups(spec);
          break;
        default:
          // TODO: Default or better error?
          JX.$E('Bad Input Type');
          return;
      }

      JX.DOM.setContent(node, input.node);
      this._valueGetCallback = input.get;
      this._valueSetCallback = input.set;

      return this;
    },

    setValue: function(value) {
      this._valueSetCallback(value);
      return this;
    },

    getValue: function() {
      return this._valueGetCallback();
    },

    getNode: function() {
      if (!this._node) {

        var attrs = {
          className: 'aphront-form-control grouped'
        };

        var content = [
          this._getLabelNode(),
          this._getErrorNode(),
          this._getInputNode()
        ];

        this._node = JX.$N('div', attrs, content);
      }

      return this._node;
    },

    _getLabelNode: function() {
      if (!this._labelNode) {
        var attrs = {
          className: 'aphront-form-label'
        };

        this._labelNode = JX.$N('label', attrs);
      }

      return this._labelNode;
    },

    _getErrorNode: function() {
      if (!this._errorNode) {
        var attrs = {
          className: 'aphront-form-error'
        };

        this._errorNode = JX.$N('span', attrs);
      }

      return this._errorNode;
    },

    _getInputNode: function() {
      if (!this._inputNode) {
        var attrs = {
          className: 'aphront-form-input'
        };

        this._inputNode = JX.$N('div', attrs);
      }

      return this._inputNode;
    },

    _newTokenizer: function(spec) {
      var build = JX.Prefab.newTokenizerFromTemplate(
        spec.markup,
        spec.config);
      build.tokenizer.start();

      function get_value() {
        return JX.keys(build.tokenizer.getTokens());
      }

      function set_value(map) {
        var tokens = get_value();
        for (var ii = 0; ii < tokens.length; ii++) {
          build.tokenizer.removeToken(tokens[ii]);
        }
        for (var k in map) {
          var v = JX.Prefab.transformDatasourceResults(map[k]);
          build.tokenizer.addToken(k, v);
        }
      }

      set_value(spec.value || {});

      return {
        node: build.node,
        get: get_value,
        set: set_value
      };
    },

    _newSelect: function(spec) {
      var node = JX.Prefab.renderSelect(
        spec.options,
        spec.value,
        {},
        spec.order);

      return {
        node: node,
        get: function() {
          return node.value;
        },
        set: function(value) {
          node.value = value;
        }
      };
    },

    _newPoints: function(spec) {
      var attrs = {
        type: 'text',
        value: spec.value
      };

      var node = JX.$N('input', attrs);

      return {
        node: node,
        get: function() {
          return node.value;
        },
        set: function(value) {
          node.value = value;
        }
      };
    },

    _newOptgroups: function(spec) {
      var value = spec.value || null;

      var optgroups = [];
      for (var ii = 0; ii < spec.groups.length; ii++) {
        var group = spec.groups[ii];
        var options = [];
        for (var jj = 0; jj < group.options.length; jj++) {
          var option = group.options[jj];
          options.push(JX.$N('option', {value: option.key}, option.label));

          if (option.selected && (value === null)) {
            value = option.key;
          }
        }
        optgroups.push(JX.$N('optgroup', {label: group.label}, options));
      }

      var node = JX.$N('select', {}, optgroups);
      node.value = value;

      return {
        node: node,
        get: function() {
          return node.value;
        },
        set: function(value) {
          node.value = value;
        }
      };
    }

  }

});
