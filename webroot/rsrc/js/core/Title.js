/**
 * @provides phabricator-title
 * @requires javelin-install
 */

/**
 * Update the document title to show a notification/message count.
 */
JX.install('Title', {
  statics: {
    _counts: {},
    _title: null,

    setCount: function(k, v) {
      var self = JX.Title;
      self._counts[k] = v;
      self._update();
    },

    setTitle: function(title) {
      var self = JX.Title;
      self._title = title;
      self._update();
    },

    _update: function() {
      var self = JX.Title;

      if (self._title === null) {
        self._title = document.title;
      }

      var sum = 0;
      for (var k in self._counts) {
        sum += parseInt(self._counts[k], 10) || 0;
      }

      var title;
      if (sum) {
        title = '(' + sum + ') ' + self._title;
      } else {
        title = self._title;
      }

      document.title = title;

    }
  }
});
