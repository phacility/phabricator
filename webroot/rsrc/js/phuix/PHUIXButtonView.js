/**
 * @provides phuix-button-view
 * @requires javelin-install
 *           javelin-dom
 */
JX.install('PHUIXButtonView', {

  statics: {
    BUTTONTYPE_DEFAULT: 'buttontype.default',
    BUTTONTYPE_SIMPLE: 'buttontype.simple'
  },

  members: {
    _node: null,
    _textNode: null,

    _iconView: null,
    _color: null,
    _selected: null,
    _buttonType: null,

    setIcon: function(icon) {
      this.getIconView().setIcon(icon);
      return this;
    },

    getIconView: function() {
      if (!this._iconView) {
        this._iconView = new JX.PHUIXIconView();
        this._redraw();
      }
      return this._iconView;
    },

    setColor: function(color) {
      var node = this.getNode();

      if (this._color) {
        JX.DOM.alterClass(node, 'button-' + this._color, false);
      }
      this._color = color;
      JX.DOM.alterClass(node, 'button-' + this._color, true);

      return this;
    },

    setSelected: function(selected) {
      var node = this.getNode();
      this._selected = selected;
      JX.DOM.alterClass(node, 'selected', this._selected);
      return this;
    },

    setButtonType: function(button_type) {
      var self = JX.PHUIXButtonView;

      this._buttonType = button_type;
      var node = this.getNode();

      var is_simple = (this._buttonType == self.BUTTONTYPE_SIMPLE);
      JX.DOM.alterClass(node, 'phui-button-simple', is_simple);

      return this;
    },

    setText: function(text) {
      JX.DOM.setContent(this._getTextNode(), text);
      this._redraw();
      return this;
    },

    getNode: function() {
      if (!this._node) {
        var attrs = {
          className: 'button'
        };

        this._node = JX.$N('button', attrs);

        this._redraw();
      }

      return this._node;
    },

    _getTextNode: function() {
      if (!this._textNode) {
        var attrs = {
          className: 'phui-button-text'
        };

        this._textNode = JX.$N('div', attrs);
      }

      return this._textNode;
    },

    _redraw: function() {
      var node = this.getNode();

      var icon = this._iconView;
      var text = this._textNode;

      var content = [];
      if (icon) {
        content.push(icon.getNode());
      }

      if (text) {
        content.push(text);
      }

      JX.DOM.alterClass(node, 'has-icon', !!icon);
      JX.DOM.alterClass(node, 'has-text', !!text);
      JX.DOM.setContent(node, content);
    }
  }

});
