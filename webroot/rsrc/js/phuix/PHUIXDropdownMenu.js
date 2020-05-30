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

    if (node) {
      JX.DOM.listen(
        this._node,
        'click',
        null,
        JX.bind(this, this._onclick));
    }

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
    offsetY: 0,
    disableAutofocus: false
  },

  members: {
    _node: null,
    _menu: null,
    _open: false,
    _content: null,
    _position: null,
    _visible: false,

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

    setPosition: function(pos) {
      this._position = pos;
      this._setMenuNodePosition(pos);
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
      if (!this._visible) {
        this._visible = true;
        document.body.appendChild(this._menu);
      }

      if (this.getWidth()) {
        new JX.Vector(this.getWidth(), null).setDim(this._menu);
      }

      this._adjustposition();

      if (this._node) {
        JX.DOM.alterClass(this._node, 'phuix-dropdown-open', true);
        this._node.setAttribute('aria-expanded', 'true');
      }

      // Try to highlight the first link in the menu for assistive technologies.
      if (!this.getDisableAutofocus()) {
        var links = JX.DOM.scry(this._menu, 'a');
        if (links[0]) {
          JX.DOM.focus(links[0]);
        }
      }
    },

    _hide : function() {
      this._visible = false;
      JX.DOM.remove(this._menu);

      if (this._node) {
        JX.DOM.alterClass(this._node, 'phuix-dropdown-open', false);
        this._node.setAttribute('aria-expanded', 'false');
      }
    },

    _adjustposition : function() {
      if (!this._open) {
        return;
      }

      if (this._position) {
        this._setMenuNodePosition(this._position);
        return;
      }

      if (!this._node) {
        return;
      }

      var m = JX.Vector.getDim(this._menu);

      var v = JX.$V(this._node);
      var d = JX.Vector.getDim(this._node);

      var alignments = ['right', 'left'];
      var disallow = {};
      var margin = 8;

      // If "right" alignment would leave us with the dropdown near or off the
      // left side of the screen, disallow it.
      var x_min = ((v.x + d.x) - m.x);
      if (x_min < margin) {
        disallow.right = true;
      }

      var align = this.getAlign();

      // If the position disallows the configured alignment, try the next
      // best alignment instead.

      // If no alignment is allowed, we'll stick with the original alignment
      // and accept that it isn't going to render very nicely. This can happen
      // if the browser window is very, very small.
      if (align in disallow) {
        for (var ii = 0; ii < alignments.length; ii++) {
          if (!(alignments[ii] in disallow)) {
            align = alignments[ii];
            break;
          }
        }
      }

      switch (align) {
        case 'right':
          v = v.add(d)
               .add(JX.$V(-m.x, 0));
          break;
        default:
          v = v.add(0, d.y);
          break;
      }

      this._setMenuNodePosition(v);
    },

    _setMenuNodePosition: function(v) {
      v = v.add(this.getOffsetX(), this.getOffsetY());
      v.setPos(this._menu);
    },

    getMenuNodeDimensions: function() {
      if (!this._visible) {
        document.body.appendChild(this._menu);
      }

      var dim = JX.Vector.getDim(this._menu);

      if (!this._visible) {
        JX.DOM.remove(this._menu);
      }

      return dim;
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

      if (this._node) {
        JX.DOM.focus(this._node);
      }

      e.prevent();
    }

  }
});
