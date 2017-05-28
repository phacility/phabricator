/**
 * @provides phabricator-darklog
 * @javelin
 */

JX.install('DarkLog', {

  construct: function() {
    this._messages = [];
  },

  members: {
    _node: null,
    _messages: null,

    addMessage: function(message) {
      var node = message.getNode();

      this._messages.push(message);
      if (this._node) {
        this._append([node]);
      }

      return this;
    },

    setNode: function(node) {
      var nodes = [];
      for (var ii = 0; ii < this._messages.length; ii++) {
        nodes.push(this._messages[ii].getNode());
      }

      this._node = node;
      this._append(nodes);

      return this;
    },

    _append: function(nodes) {
      for (var ii = 0; ii < nodes.length; ii++) {
        this._node.appendChild(nodes[ii]);
      }
    }

  }

});
