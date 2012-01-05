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

    JX.PhabricatorDropdownMenu.listen(
      'open',
      JX.bind(this, this.close));
  },

  events : ['open'],

  members : {
    _node : null,
    _menu : null,
    _open : false,
    _items : null,

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
    },

    close : function() {
      if (!this._open) {
        return;
      }
      this._open = false;
      this._hide();
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
      item.select();
      e.prevent();
      this.close();
    },

    _onclickglobal : function(e) {
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

      var m = JX.Vector.getDim(this._menu);

      JX.$V(this._node)
        .add(JX.Vector.getDim(this._node))
        .add(JX.$V(-m.x, 0))
        .setPos(this._menu);

      JX.DOM.alterClass(this._node, 'dropdown-open', true);
    },

    _hide : function() {
      JX.DOM.remove(this._menu);
      JX.DOM.alterClass(this._node, 'dropdown-open', false);
    }

  }
});
