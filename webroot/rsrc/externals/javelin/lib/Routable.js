/**
 * @provides javelin-routable
 * @requires javelin-install
 * @javelin
 */

JX.install('Routable', {

  construct : function() {
    this._id = (JX.Routable._nextID++);
  },

  properties: {
    key: null,
    priority: 1000,
    type: 'default'
  },

  events: ['start', 'done'],

  members: {
    _id: null,

    getID: function() {
      return this._id;
    },

    start: function() {
      this.invoke('start');
    },

    done: function() {
      this.invoke('done');
    }
  },

  statics: {
    _nextID: 0
  }

});
