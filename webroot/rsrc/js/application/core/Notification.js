/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-stratcom
 * @provides phabricator-notification
 * @javelin
 */

/**
 * Show a notification. Usage:
 *
 *   var n = new JX.Notification()
 *     .setContent('click me!');
 *   n.listen('activate', function(e) { alert('you clicked!'); });
 *   n.show();
 *
 */
JX.install('Notification', {

  events : ['activate', 'close'],

  members : {
    show : function() {
      var self = JX.Notification;

      self.close();
      self._installListener();
      self._active = this;

      var container = JX.$N(
        'div',
        {
          className: 'jx-notification-container',
          sigil: 'jx-notification'
        },
        this.getContent());
      document.body.appendChild(container);

      self._container = container;

      if (this.getDuration()) {
        self._timeout = setTimeout(self.close, this.getDuration());
      }
    }
  },

  properties : {
    content : null,

    /**
     * Duration before the notification fades away, in milliseconds. If set to
     * 0, the notification persists until dismissed.
     *
     * @param int Notification duration, in milliseconds.
     */
    duration : 12000
  },

  statics : {
    _container : null,
    _listening : false,
    _active : null,
    _installListener : function() {
      var self = JX.Notification;

      if (self._listening) {
        return;
      } else {
        self._listening = true;
      }

      JX.Stratcom.listen(
        'click',
        'jx-notification',
        function(e) {
          // NOTE: Don't kill the event since the user might have clicked a
          // link, and we want to follow the link if they did. Istead, invoke
          // the activate event for the active notification and dismiss it if it
          // isn't handled.

          var activation = self._active.invoke('activate');
          if (activation.getPrevented()) {
            return;
          }

          self.close();
        });
    },
    close : function() {
      var self = JX.Notification;

      if (self._container) {
        JX.DOM.remove(self._container);
        self._container = null;

        self._active.invoke('close');
        self._active = null;
      }

      self._timeout && clearTimeout(self._timeout);
      self._timeout = null;
    }
  }

});

