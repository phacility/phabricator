/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-util
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
      self._show(this);

      if (this.getDuration()) {
        setTimeout(JX.bind(self, self._hide, this), this.getDuration());
      }
    },
    _render : function() {
      return JX.$N(
        'div',
        {
          className: 'jx-notification ' + this.getClassName(),
          sigil: 'jx-notification'
        },
        this.getContent());
    }
  },

  properties : {

    /**
     * Optional class name(s) to add to the rendered notification.
     *
     * @param string Class name(s).
     */
    className : null,

    /**
     * Notification content.
     *
     * @param mixed Content.
     */
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
    _active : [],
    _show : function(notification) {
      var self = JX.Notification;

      self._installListener();
      self._active.push({
        object: notification,
        render: notification._render()
      });

      // Don't show more than a few notifications at once because it's silly.
      while (self._active.length > 5) {
        self._hide(self._active[0].object);
      }

      self._redraw();
    },
    _hide : function(notification) {
      var self = JX.Notification;

      for (var ii = 0; ii < self._active.length; ii++) {
        if (self._active[ii].object === notification) {
          notification.invoke('close');
          self._active.splice(ii, 1);
          break;
        }
      }

      self._redraw();
    },
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

          var target = e.getNode('jx-notification');
          for (var ii = 0; ii < self._active.length; ii++) {
            var n = self._active[ii];
            if (n.render === target) {
              var activation = n.object.invoke('activate');
              if (!activation.getPrevented()) {
                self._hide(n.object);
              }
              return;
            }
          }

        });
    },
    _redraw : function() {
      var self = JX.Notification;

      if (!self._active.length) {
        if (self._container) {
          JX.DOM.remove(self._container);
          self._container = null;
        }
        return;
      }

      if (!self._container) {
        self._container = JX.$N(
          'div',
          {
            className: 'jx-notification-container'
          });
        document.body.appendChild(self._container);
      }

      var notifications = [];
      for (var ii = 0; ii < self._active.length; ii++) {
        notifications.push(self._active[ii].render);
      }

      JX.DOM.setContent(self._container, notifications);
    }
  }

});

