/**
 * @requires javelin-install
 * @provides javelin-sound
 * @javelin
 */


JX.install('Sound', {
  statics: {
    _sounds: {},

    load: function(uri) {
      var self = JX.Sound;

      if (!(uri in self._sounds)) {
        self._sounds[uri] = JX.$N(
          'audio',
          {
            src: uri,
            preload: 'auto'
          });
      }
    },

    play: function(uri) {
      var self = JX.Sound;
      self.load(uri);

      var sound = self._sounds[uri];

      try {
        sound.play();
      } catch (ex) {
        JX.log(ex);
      }
    }
  }
});
