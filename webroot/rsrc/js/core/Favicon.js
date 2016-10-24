/**
 * @provides phabricator-favicon
 * @requires javelin-install
 *           javelin-dom
 */
JX.install('Favicon', {
  statics: {
    _favicon: null,

    setFavicon: function(favicon) {
      var self = JX.Favicon;
      self._favicon = favicon;
      self._update();
    },

    _update: function() {
      var self = JX.Favicon;
      var cur_favicon = JX.$('favicon');

      if (self._favicon === null) {
        self._favicon = cur_favicon.href;
      }

      var new_favicon = JX.$N(
        'link',
        {href: self._favicon,
           id: 'favicon',
          rel: 'shortcut icon',
        });

      JX.DOM.replace(cur_favicon, new_favicon);

    }
  }
});
