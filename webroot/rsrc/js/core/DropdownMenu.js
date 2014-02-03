/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-vector
 *           javelin-stratcom
 *           phabricator-menu-item
 * @provides phabricator-dropdown-menu
 * @javelin
 */

JX.install('PhabricatorDropdownMenu', {

  construct : function(node) {
    this._node = node;
    this._items = [];
    this._menu = JX.$N('div', { className : 'dropdown-menu-frame' });

    JX.DOM.listen(
      this._node,
      'click',
      null,
      JX.bind(this, this._onclick));

    JX.DOM.listen(
      this._menu,
      'click',
      null,
      JX.bind(this, this._onclickitem));

    JX.Stratcom.listen(
      'mousedown',
      null,
      JX.bind(this, this._onclickglobal));

    JX.Stratcom.listen(
      'resize',
      null,
      JX.bind(this, this._onresize));

    JX.PhabricatorDropdownMenu.listen(
      'open',
      JX.bind(this, this.close));
  },

  events : ['open'],

  properties : {
    width : null
  },

  members : {
    _node : null,
    _menu : null,
    _open : false,
    _items : null,
    _alignRight : true,

    // By default, the dropdown will have its right edge aligned with the
    // right edge of _node. Making this false does left edge alignment
    toggleAlignDropdownRight : function (bool) {
      this._alignRight = bool;
    },

    open : function() {
      if (this._open) {
        return;
      }

      this.invoke('open');

      var menu_items = [];
      for (var ii = 0; ii < this._items.length; ii++) {
        menu_items.push(this._items[ii].render());
      }
      JX.DOM.setContent(this._menu, menu_items);

      this._open = true;
      this._show();

      return this;
    },

    close : function() {
      if (!this._open) {
        return;
      }
      this._open = false;
      this._hide();

      return this;
    },

    clear : function() {
      this._items = [];
      return this;
    },

    addItem : function(item) {
      if (__DEV__) {
        if (!(item instanceof JX.PhabricatorMenuItem)) {
          JX.$E(
            'JX.DropdownMenu.addItem(<junk>): ' +
            'item must be a JX.PhabricatorMenuItem.');
        }
      }
      this._items.push(item);
      return this;
    },

    _onclick : function(e) {
      if (this._open) {
        this.close();
      } else {
        this.open();
      }
      e.prevent();
    },

    _onclickitem : function(e) {
      var item = JX.Stratcom.getData(e.getTarget()).item;
      if (!item) {
        return;
      }

      if (item.getDisabled()) {
        e.prevent();
        return;
      }

      item.select();
      e.prevent();
      this.close();
    },

    _onclickglobal : function(e) {
      if (!this._open) {
        return;
      }

      if (JX.Stratcom.pass(e)) {
        return;
      }

      var t = e.getTarget();
      while (t) {
        if (t == this._menu || t == this._node) {
          return;
        }
        t = t.parentNode;
      }

      this.close();
    },

    _show : function() {
      document.body.appendChild(this._menu);

      if (this.getWidth()) {
        new JX.Vector(this.getWidth(), null).setDim(this._menu);
      }

      this._onresize();

      JX.DOM.alterClass(this._node, 'dropdown-open', true);
    },

    _onresize : function() {
      if (!this._open) {
        return;
      }

      var m = JX.Vector.getDim(this._menu);

      var v = JX.$V(this._node);
      var d = JX.Vector.getDim(this._node);
      if (this._alignRight) {
        v = v.add(d)
             .add(JX.$V(-m.x, 0));
      } else {
        v = v.add(0, d.y);
      }
      v.setPos(this._menu);
    },

    _hide : function() {
      JX.DOM.remove(this._menu);
      JX.DOM.alterClass(this._node, 'dropdown-open', false);
    }

  }
});
