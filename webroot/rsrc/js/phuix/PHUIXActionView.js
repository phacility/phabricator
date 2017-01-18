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
    _disabled: false,
    _label: false,
    _handler: null,
    _selected: false,

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

    setLabel: function(label) {
      this._label = label;
      JX.DOM.alterClass(
        this.getNode(),
        'phabricator-action-view-label',
        label);
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
      return this;
    },

    setIcon: function(icon) {
      this._icon = icon;
      this._buildIconNode(true);
      return this;
    },

    setHref: function(href) {
      this._href = href;
      this._buildNameNode(true);
      return this;
    },

    getNode: function() {
      if (!this._node) {
        var classes = ['phabricator-action-view'];

        if (this._href || this._handler) {
          classes.push('phabricator-action-view-href');
        }

        if (this._icon) {
          classes.push('action-has-icon');
        }

        var content = [
          this._buildIconNode(),
          this._buildNameNode()
        ];

        var attr = {
          className: classes.join(' ')
        };
        this._node = JX.$N('li', attr, content);

        JX.Stratcom.addSigil(this._node, 'phuix-action-view');
      }

      return this._node;
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

        JX.DOM.alterClass(node, icon_class, true);

        if (this._iconNode && this._iconNode.parentNode) {
          JX.DOM.replace(this._iconNode, node);
        }
        this._iconNode = node;
      }

      return this._iconNode;
    },

    _buildNameNode: function(dirty) {
      if (!this._nameNode || dirty) {
        var attr = {
          className: 'phabricator-action-view-item'
        };

        var href = this._href;
        if (!href && this._handler) {
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
      if (this._handler) {
        this._handler(e);
      }
    }

  }

});
