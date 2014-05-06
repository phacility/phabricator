/**
 * @provides javelin-router
 * @requires javelin-install
 *           javelin-util
 * @javelin
 */

/**
 * Route requests. Primarily, this class provides a quality-of-service
 * priority queue so large numbers of background loading tasks don't block
 * interactive requests.
 */
JX.install('Router', {

  construct: function() {
    this._queue = [];
  },

  events: ['queue', 'start', 'done'],

  members: {
    _queue: null,
    _active: 0,
    _limit: 5,

    queue: function(routable) {
      this._queue.push(routable);

      this.invoke('queue', routable);
      this._update();
    },

    getRoutableByKey: function(key) {
      for (var ii = 0; ii < this._queue.length; ii++) {
        if (this._queue[ii].getKey() == key) {
          return this._queue[ii];
        }
      }
      return null;
    },

    /**
     * Start new requests if we have slots free for them.
     */
    _update: function() {
      var active = this._active;
      var limit = this._limit;

      if (active >= limit) {
        // If we're already at the request limit, we can't add any more
        // requests.
        return;
      }

      // If we only have one free slot, we reserve it for a request with
      // at least priority 1000.
      var minimum;
      if ((active + 1) == limit) {
        minimum = 1000;
      } else {
        minimum = 0;
      }

      var idx = this._getNextRoutable(minimum);
      if (idx === null) {
        return;
      }

      var routable = this._queue[idx];
      this._queue.splice(idx, 1);


      routable.listen('done', JX.bind(this, this._done, routable));

      this._active++;
      routable.start();
      this.invoke('start', routable);

      this._update();
    },

    _done: function(routable) {
      this._active--;
      this.invoke('done', routable);

      this._update();
    },

    _getNextRoutable: function(minimum) {
      var best = (minimum - 1);

      var routable = null;
      for (var ii = 0; ii < this._queue.length; ii++) {
        var priority = this._queue[ii].getPriority();
        if (priority > best) {
          best = priority;
          routable = ii;
        }
      }

      return routable;
    }

  },

  statics: {
    _instance: null,
    getInstance: function() {
      if (!JX.Router._instance) {
        JX.Router._instance = new JX.Router();
      }
      return JX.Router._instance;
    }
  }
});
