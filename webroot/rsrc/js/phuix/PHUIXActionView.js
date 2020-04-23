/**
 * @provides phuix-action-view
 * @requires javelin-install
 *           javelin-dom
 *           javelin-util
 * @javelin
 */

JX.install('PHUIXActionView', {

  members: {
    _node: null,
    _name: null,
    _icon: 'none',
    _iconColor: null,
    _disabled: false,
    _label: false,
    _handler: null,
    _selected: false,
    _divider: false,
    _keyCommand: null,
    _unresponsive: null,

    _iconNode: null,
    _nameNode: null,

    setDisabled: function(disabled) {
      this._disabled = disabled;
      JX.DOM.alterClass(
        this.getNode(),
        'phabricator-action-view-disabled',
        disabled);

      this._buildIconNode(true);

      return this;
    },

    setUnresponsive: function(unresponsive) {
      this._unresponsive = unresponsive;
      this._buildNameNode(true);
      return this;
    },

    getDisabled: function() {
      return this._disabled;
    },

    setLabel: function(label) {
      this._label = label;
      JX.DOM.alterClass(
        this.getNode(),
        'phabricator-action-view-label',
        label);
      return this;
    },

    setDivider: function(divider) {
      this._divider = divider;
      JX.DOM.alterClass(
        this.getNode(),
        'phabricator-action-view-type-divider',
        divider);
      return this;
    },

    setSelected: function(selected) {
      this._selected = selected;
      JX.DOM.alterClass(
        this.getNode(),
        'phabricator-action-view-selected',
        selected);

      return this;
    },

    setName: function(name) {
      this._name = name;
      this._buildNameNode(true);
      return this;
    },

    setHandler: function(handler) {
      this._handler = handler;
      this._buildNameNode(true);
      this._rebuildClasses();
      return this;
    },

    setIcon: function(icon) {
      this._icon = icon;
      this._buildIconNode(true);
      return this;
    },

    setIconColor: function(color) {
      this._iconColor = color;
      this._buildIconNode(true);
      return this;
    },

    setHref: function(href) {
      this._href = href;
      this._buildNameNode(true);
      this._rebuildClasses();
      return this;
    },

    setKeyCommand: function(command) {
      this._keyCommand = command;

      var key_node = this._buildKeyCommandNode();
      JX.DOM.setContent(key_node, this._keyCommand);

      var node = this.getNode();
      JX.DOM.alterClass(node, 'has-key-command', !!this._keyCommand);

      return this;
    },

    getNode: function() {
      if (!this._node) {
        var classes = ['phabricator-action-view'];

        if (this._icon) {
          classes.push('action-has-icon');
        }

        var content = [
          this._buildIconNode(),
          this._buildNameNode(),
          this._buildKeyCommandNode(),
        ];

        var attr = {
          className: classes.join(' ')
        };
        this._node = JX.$N('li', attr, content);

        JX.Stratcom.addSigil(this._node, 'phuix-action-view');

        this._rebuildClasses();
      }

      return this._node;
    },

    _rebuildClasses: function() {
      var node = this.getNode();

      var is_href = !!(this._href || this._handler);
      JX.DOM.alterClass(node, 'phabricator-action-view-href', is_href);
    },

    _buildIconNode: function(dirty) {
      if (!this._iconNode || dirty) {
        var attr = {
          className: [
            'phui-icon-view',
            'phabricator-action-view-icon',
            'phui-font-fa'
          ].join(' ')
        };
        var node = JX.$N('span', attr);

        var icon_class = this._icon;
        if (this._disabled) {
          icon_class = icon_class + ' grey';
        }

        if (this._iconColor) {
          icon_class = icon_class + ' ' + this._iconColor;
        }

        JX.DOM.alterClass(node, icon_class, true);

        if (this._iconNode && this._iconNode.parentNode) {
          JX.DOM.replace(this._iconNode, node);
        }
        this._iconNode = node;
      }

      return this._iconNode;
    },

    _buildKeyCommandNode: function() {
      if (!this._keyCommandNode) {
        var attrs = {
          className: 'keyboard-shortcut-key'
        };

        this._keyCommandNode = JX.$N('div', attrs);
      }
      return this._keyCommandNode;
    },

    _buildNameNode: function(dirty) {
      if (!this._nameNode || dirty) {
        var attr = {
          className: 'phabricator-action-view-item'
        };

        var href = this._href;
        if (!href && this._handler && !this._unresponsive) {
          href = '#';
        }
        if (href) {
          attr.href = href;
        }

        var tag = href ? 'a' : 'span';

        var node = JX.$N(tag, attr, this._name);
        JX.DOM.listen(node, 'click', null, JX.bind(this, this._onclick));

        if (this._nameNode && this._nameNode.parentNode) {
          JX.DOM.replace(this._nameNode, node);
        }
        this._nameNode = node;
      }

      return this._nameNode;
    },

    _onclick: function(e) {
      if (this._unresponsive) {
        e.prevent();
        return;
      }

      if (this._handler) {
        this._handler(e);
      }
    }

  }

});
