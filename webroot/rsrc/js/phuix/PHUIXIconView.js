/**
 * @provides phuix-icon-view
 * @requires javelin-install
 *           javelin-dom
 */

JX.install('PHUIXIconView', {

  members: {
    _node: null,
    _icon: null,
    _color: null,

    setIcon: function(icon) {
      var node = this.getNode();
      if (this._icon) {
        JX.DOM.alterClass(node, this._icon, false);
      }
      this._icon = icon;
      JX.DOM.alterClass(node, this._icon, true);
      return this;
    },

    setColor: function(color) {
      var node = this.getNode();
      if (this._color) {
        JX.DOM.alterClass(node, this._color, false);
      }
      this._color = color;
      JX.DOM.alterClass(node, this._color, true);
      return this;
    },

    getNode: function() {
      if (!this._node) {
        var attrs = {
          className: 'phui-icon-view phui-font-fa'
        };

        this._node = JX.$N('span', attrs);
      }

      return this._node;
    }
  }

});
