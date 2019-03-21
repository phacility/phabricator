/**
 * @requires javelin-install
 * @provides javelin-sound
 * @javelin
 */


JX.install('Sound', {
  statics: {
    _sounds: {},
    _queue: [],
    _playingQueue: false,

    load: function(uri) {
      var self = JX.Sound;

      if (!(uri in self._sounds)) {
        var audio = JX.$N(
          'audio',
          {
            src: uri,
            preload: 'auto'
          });

        // In Safari, it isn't good enough to just load a sound in response
        // to a click: we must also play it. Once we've played it once, we
        // can continue to play it freely.

        // Play the sound, then immediately pause it. This rejects the "play()"
        // promise but marks the audio as playable, so our "play()" method will
        // work correctly later.
        if (window.webkitAudioContext) {
          audio.play().then(JX.bag, JX.bag);
          audio.pause();
        }

        self._sounds[uri] = audio;
      }
    },

    play: function(uri, callback) {
      var self = JX.Sound;
      self.load(uri);

      var sound = self._sounds[uri];

      try {
        sound.onended = callback || JX.bag;
        sound.play().then(JX.bag, callback || JX.bag);
      } catch (ex) {
        JX.log(ex);
      }
    },

    queue: function(uri) {
      var self = JX.Sound;
      self._queue.push(uri);
      self._playQueue();
    },

    _playQueue: function() {
      var self = JX.Sound;
      if (self._playingQueue) {
        return;
      }
      self._playingQueue = true;
      self._nextQueue();
    },

    _nextQueue: function() {
      var self = JX.Sound;
      if (self._queue.length) {
        var next = self._queue[0];
        self._queue.splice(0, 1);
        self.play(next, self._nextQueue);
      } else {
        self._playingQueue = false;
      }
    }

  }
});
