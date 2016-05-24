/**
 * @requires javelin-install
 * @provides javelin-leader
 * @javelin
 */

/**
 * Synchronize multiple tabs over LocalStorage.
 *
 * This class elects one tab as the "Leader". It remains the leader until it
 * is closed.
 *
 * Tabs can conditionally call a function if they are the leader using
 * @{method:callIfLeader}. This will trigger leader election, and call the
 * function if the current tab is elected. This can be used to keep one
 * websocket open across a group of tabs, or play a sound only once in response
 * to a server state change.
 *
 * Tabs can broadcast messages to other tabs using @{method:broadcast}. Each
 * message has an optional ID. When a tab receives multiple copies of a message
 * with the same ID, copies after the first copy are discarded. This can be
 * used in conjunction with @{method:callIfLeader} to allow multiple event
 * responders to trigger a reaction to an event (like a sound) and ensure that
 * it is played only once (not once for each notification), and by only one
 * tab (not once for each tab).
 *
 * Finally, tabs can register a callback which will run if they become the
 * leading tab, by listening for `onBecomeLeader`.
 */

JX.install('Leader', {

  events: ['onBecomeLeader', 'onReceiveBroadcast'],

  statics: {
    _interval: null,
    _timeout: null,
    _broadcastKey: 'JX.Leader.broadcast',
    _leaderKey: 'JX.Leader.id',


    /**
     * Tracks leadership state. Since leadership election is asynchronous,
     * we can't expose this directly without inconsistent behavior.
     */
    _isLeader: false,


    /**
     * Keeps track of message IDs we've seen, so we send each message only
     * once.
     */
    _seen: {},


    /**
     * Helps keep the list of seen message IDs from growing without bound.
     */
    _seenList: [],


    /**
     * Elect a leader, triggering leadership callbacks if they are registered.
     */
    start: function() {
      var self = JX.Leader;
      self.call(JX.bag);
    },

    /**
     * Call a method if this tab is the leader.
     *
     * This is asynchronous because leadership election is asynchronous. If
     * the current tab is not the leader after any election takes place, the
     * callback will not be invoked.
     */
    callIfLeader: function(callback) {
      JX.Leader._callIf(callback, JX.bag);
    },


    /**
     * Call a method after leader election.
     *
     * This is asynchronous because leadership election is asynchronous. The
     * callback will be invoked after election takes place.
     *
     * This method is useful if you want to invoke a callback no matter what,
     * but the callback behavior depends on whether this is the leader or
     * not.
     */
    call: function(callback) {
      JX.Leader._callIf(callback, callback);
    },

    /**
     * Elect a leader, then invoke either a leader callback or a follower
     * callback.
     */
    _callIf: function(leader_callback, follower_callback) {
      var self = JX.Leader;

      if (!window.localStorage) {
        // If we don't have localStorage, pretend we're the only tab.
        self._becomeLeader();
        leader_callback();
        return;
      }

      // If we don't have an ID for this tab yet, generate one and register
      // event listeners.
      if (!self._id) {
        self._id = 1 + parseInt(Math.random() * 1000000000, 10);
        JX.Stratcom.listen('pagehide', null, self._pagehide);
        JX.Stratcom.listen('storage', null, self._storage);
      }

      // Read the current leadership lease.
      var lease = self._read();

      // Stagger these delays so that they are unlikely to race one another.
      var expire_delay = 50;
      var usurp_delay = 75;

      // If the lease is good, we're all set.
      var now = +new Date();
      if (lease.until > now) {
        if (lease.id === self._id) {

          // If we haven't installed an update timer yet, do so now. This will
          // renew our lease every 5 seconds, making sure we hold it until the
          // tab is closed.
          if (!self._interval && lease.until > now + 10000) {
            self._interval = window.setInterval(self._write, 5000);
          }

          self._becomeLeader();
          leader_callback();
        } else {

          // Set a callback to try to become the leader shortly after the
          // current lease expires. This lets us quickly recover from cases
          // where the leader goes missing.

          // In particular, this can happen in Safari if you close windows or
          // quit the browser instead of browsing away: the "pagehide" event
          // does not fire when the leader is simply destroyed, so it does not
          // evict itself from the throne of power.
          if (!self._timeout) {
            var usurp_at = (lease.until - now) + usurp_delay;
            self._timeout = window.setTimeout(self._usurp, usurp_at);
          }

          follower_callback();
        }

        return;
      }

      // If the lease isn't good, try to become the leader. We don't have
      // proper locking primitives for this, but can do a relatively good
      // job. The algorithm here is:
      //
      //   - Write our ID, trying to acquire the lease.
      //   - Delay for much longer than a write "could possibly" take.
      //   - Read the key back.
      //   - If nothing else overwrote the key, we become the leader.
      //
      // This avoids a race where our reads and writes could otherwise
      // interleave with another tab's reads and writes, electing both or
      // neither as the leader.
      //
      // This approximately follows an algorithm attributed to Fischer in
      // "A Fast Mutual Exclusion Algorithm" (Leslie Lamport, 1985). That
      // paper also describes a faster (but more complex) algorithm, but
      // it's not problematic to add a significant delay here because
      // leader election is not especially performance-sensitive.

      self._write();

      window.setTimeout(
        JX.bind(null, self._callIf, leader_callback, follower_callback),
        expire_delay);
    },


    /**
     * Send a message to all open tabs.
     *
     * Tabs can receive messages by listening to `onReceiveBroadcast`.
     *
     * @param string|null Message ID. If provided, subsequent messages with
     *   the same ID will be discarded.
     * @param wild The message to send.
     */
    broadcast: function(id, message) {
      var self = JX.Leader;
      if (id !== null) {
        if (id in self._seen) {
          return;
        }
        self._markSeen(id);
      }

      if (window.localStorage) {
        var json = JX.JSON.stringify(
          {
            id: id,
            message: message,

            // LocalStorage only emits events if the value changes. Include
            // a random component to make sure that broadcasts are never
            // eaten. Although this is probably not often useful in a
            // production system, it makes testing easier and more predictable.
            uniq: parseInt(Math.random() * 1000000, 10)
          });
        window.localStorage.setItem(self._broadcastKey, json);
      }

      self._receiveBroadcast(message);
    },


    /**
     * Write a lease which names us as the leader.
     */
    _write: function() {
      var self = JX.Leader;

      var str = [self._id, ((+new Date()) + 16000)].join(':');
      window.localStorage.setItem(self._leaderKey, str);
    },


    /**
     * Read the current lease.
     */
    _read: function() {
      var self = JX.Leader;

      var leader = window.localStorage.getItem(self._leaderKey) || '0:0';
      leader = leader.split(':');

      return {
        id: parseInt(leader[0], 10),
        until: parseInt(leader[1], 10)
      };
    },


    /**
     * When the tab is closed, if we're the leader, release leadership.
     *
     * This will trigger a new election if there are other tabs open.
     */
    _pagehide: function() {
      var self = JX.Leader;
      if (self._read().id === self._id) {
        window.localStorage.removeItem(self._leaderKey);
      }
    },


    /**
     * React to a storage update.
     */
    _storage: function(e) {
      var self = JX.Leader;

      var key = e.getRawEvent().key;
      var new_value = e.getRawEvent().newValue;

      switch (key) {
        case self._broadcastKey:
          new_value = JX.JSON.parse(new_value);
          if (new_value.id !== null) {
            if (new_value.id in self._seen) {
              return;
            }
            self._markSeen(new_value.id);
          }
          self._receiveBroadcast(new_value.message);
          break;
        case self._leaderKey:
          // If the leader tab closed, elect a new leader.
          if (new_value === null) {
            self.callIfLeader(JX.bag);
          }
          break;
      }
    },

    _receiveBroadcast: function(message) {
      var self = JX.Leader;
      new JX.Leader().invoke('onReceiveBroadcast', message, self._isLeader);
    },

    _becomeLeader: function() {
      var self = JX.Leader;
      if (self._isLeader) {
        return;
      }

      self._isLeader = true;
      new JX.Leader().invoke('onBecomeLeader');
    },


    /**
     * Try to usurp leadership position after a lease expiration.
     */
    _usurp: function() {
      var self = JX.Leader;
      self.call(JX.bag);
      self._timeout = null;
    },


    /**
     * Mark a message as seen.
     *
     * We keep a fixed-sized list of recent messages, and let old ones fall
     * off the end after a while.
     */
    _markSeen: function(id) {
      var self = JX.Leader;

      self._seen[id] = true;
      self._seenList.push(id);
      while (self._seenList.length > 128) {
        delete self._seen[self._seenList[0]];
        self._seenList.splice(0, 1);
      }
    }

  }
});
