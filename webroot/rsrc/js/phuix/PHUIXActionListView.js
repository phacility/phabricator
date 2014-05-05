/**
 * @provides phuix-action-list-view
 * @requires javelin-install
 *           javelin-dom
 */

JX.install('PHUIXActionListView', {

  construct: function() {
    this._items = [];
  },

  members: {
    _items: null,
    _node: null,

    addItem: function(item) {
      this._items.push(item);
      this.getNode().appendChild(item.getNode());
      return this;
    },

    getNode: function() {
      if (!this._node) {
        var attrs = {
          className: 'phabricator-action-list-view'
        };

        this._node = JX.$N('ul', attrs);
      }

      return this._node;
    }
  }

});
