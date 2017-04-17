/**
 * @provides phabricator-darkmessage
 * @javelin
 */

JX.install('DarkMessage', {

  construct: function() {

  },

  members: {
    _node: null,
    _message: null,

    setMessage: function(message) {
      this._message = message;

      JX.DOM.setContent(this.getNode(), message);

      return this;
    },

    getNode: function() {
      if (!this._node) {
        this._node = JX.$N(
          'div',
          {
            className: 'dark-console-log-message'
          });
      }

      return this._node;
    }
  }

});
