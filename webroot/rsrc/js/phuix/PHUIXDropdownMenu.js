/**
 * @provides phuix-dropdown-menu
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-vector
 *           javelin-stratcom
 * @javelin
 */


/**
 * Basic interaction for a dropdown menu.
 *
 * The menu is unaware of the content inside it, so it can not close itself
 * when an item is selected. Callers must make a call to @{method:close} after
 * an item is chosen in order to close the menu.
 */
JX.install('PHUIXDropdownMenu', {

  construct : function(node) {
    this._node = node;

    JX.DOM.listen(
      this._node,
      'click',
      null,
      JX.bind(this, this._onclick));

    JX.Stratcom.listen(
      'mousedown',
      null,
      JX.bind(this, this._onanyclick));

    JX.Stratcom.listen(
      'resize',
      null,
      JX.bind(this, this._adjustposition));

    JX.Stratcom.listen('phuix.dropdown.open', null, JX.bind(this, this.close));

    JX.Stratcom.listen('keydown', null, JX.bind(this, this._onkey));

    JX.DOM.listen(
      this._getMenuNode(),
      'click',
      'tag:a',
      JX.bind(this, this._onlink));
  },

  events: ['open', 'close'],

  properties: {
    width: null,
    align: 'right',
    offsetX: 0,
    offsetY: 0
  },

  members: {
    _node: null,
    _menu: null,
    _open: false,
    _content: null,

    setContent: function(content) {
      JX.DOM.setContent(this._getMenuNode(), content);
      return this;
    },

    open: function() {
      if (this._open) {
        return;
      }

      this.invoke('open');
      JX.Stratcom.invoke('phuix.dropdown.open');

      this._open = true;
      this._show();

      return this;
    },

    close: function() {
      if (!this._open) {
        return;
      }
      this._open = false;
      this._hide();

      this.invoke('close');

      return this;
    },

    _getMenuNode: function() {
      if (!this._menu) {
        var attrs = {
          className: 'phuix-dropdown-menu',
          role: 'button'
        };

        var menu = JX.$N('div', attrs);

        this._menu = menu;
      }

      return this._menu;
    },

    _onclick : function(e) {
      if (this._open) {
        this.close();
      } else {
        this.open();
      }
      e.prevent();
    },

    _onlink: function(e) {
      if (!e.isNormalClick()) {
        return;
      }

      // If this action was built dynamically with PHUIXActionView, don't
      // do anything by default. The caller is responsible for installing a
      // handler if they want to react to clicks.
      if (e.getNode('phuix-action-view')) {
        return;
      }

      // If this item opens a submenu, we don't want to close the current
      // menu. One submenu is "Edit Related Objects..." on mobile.
      var link = e.getNode('tag:a');
      if (JX.Stratcom.hasSigil(link, 'keep-open')) {
        return;
      }

      this.close();
    },

    _onanyclick : function(e) {
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

      this._adjustposition();

      JX.DOM.alterClass(this._node, 'phuix-dropdown-open', true);

      this._node.setAttribute('aria-expanded', 'true');

      // Try to highlight the first link in the menu for assistive technologies.
      var links = JX.DOM.scry(this._menu, 'a');
      if (links[0]) {
        JX.DOM.focus(links[0]);
      }
    },

    _hide : function() {
      JX.DOM.remove(this._menu);

      JX.DOM.alterClass(this._node, 'phuix-dropdown-open', false);

      this._node.setAttribute('aria-expanded', 'false');
    },

    _adjustposition : function() {
      if (!this._open) {
        return;
      }

      var m = JX.Vector.getDim(this._menu);

      var v = JX.$V(this._node);
      var d = JX.Vector.getDim(this._node);

      switch (this.getAlign()) {
        case 'right':
          v = v.add(d)
               .add(JX.$V(-m.x, 0));
          break;
        default:
          v = v.add(0, d.y);
          break;
      }

      v = v.add(this.getOffsetX(), this.getOffsetY());

      v.setPos(this._menu);
    },

    _onkey: function(e) {
      // When the user presses escape with a menu open, close the menu and
      // refocus the button which activates the menu. In particular, this makes
      // popups more usable with assistive technologies.

      if (!this._open) {
        return;
      }

      if (e.getSpecialKey() != 'esc') {
        return;
      }

      this.close();
      JX.DOM.focus(this._node);

      e.prevent();
    }

  }
});
