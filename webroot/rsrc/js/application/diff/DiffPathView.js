/**
 * @provides phabricator-diff-path-view
 * @requires javelin-dom
 * @javelin
 */

JX.install('DiffPathView', {

  construct: function() {
  },

  properties: {
    changeset: null
  },

  members: {
    _node: null,
    _path: null,
    _depth: 0,
    _selected: false,

    getNode: function() {
      if (!this._node) {
        this._node = JX.$N('li');

        var onclick = JX.bind(this, this._onclick);
        JX.DOM.listen(this._node, 'click', null, onclick);
      }
      return this._node;
    },

    setPath: function(path) {
      this._path = path;
      this._redraw();
      return this;
    },

    getPath: function() {
      return this._path;
    },

    setDepth: function(depth) {
      this._depth = depth;
      this._redraw();
      return this;
    },

    setIsSelected: function(selected) {
      this._selected = selected;
      this._redraw();
      return this;
    },

    _onclick: function(e) {
      if (!e.isNormalClick()) {
        return;
      }

      var changeset = this.getChangeset();
      if (changeset) {
        changeset.select(true);
      }

      e.kill();
    },

    _redraw: function() {
      var node = this.getNode();

      node.style.paddingLeft = (8 * this._depth) + 'px';

      var display = this._path[this._path.length - 1];

      if (this._selected) {
        display = ['*', display];
      }

      JX.DOM.setContent(node, display);
    }

  }

});
