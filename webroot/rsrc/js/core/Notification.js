/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-util
 *           phabricator-notification-css
 * @provides phabricator-notification
 * @javelin
 */

/**
 * Show a notification popup on screen. Usage:
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
    _container : null,
    _visible : false,
    _hideTimer : null,
    _duration : 12000,
    _asDesktop : false,
    _key : null,
    _title : null,
    _body : null,
    _href : null,
    _icon : null,

    show : function() {
      var self = JX.Notification;

      if (!this._visible) {
        this._visible = true;

        self._show(this);
        this._updateTimer();
      }

      if (self.supportsDesktopNotifications() &&
          self.desktopNotificationsEnabled() &&
          this._asDesktop) {
        // Note: specifying "tag" means that notifications with matching
        // keys will aggregate.
        var n = new window.Notification(this._title, {
          icon: this._icon,
          body: this._body,
          tag: this._key,
        });
        n.onclick = JX.bind(n, function (href) {
          this.close();
          window.focus();
          if (href) {
            JX.$U(href).go();
          }
        }, this._href);
        // Note: some OS / browsers do this automagically; make the behavior
        // happen everywhere.
        setTimeout(n.close.bind(n), this._duration);
      }
      return this;
    },

    hide : function() {
      if (this._visible) {
        this._visible = false;

        var self = JX.Notification;
        self._hide(this);
        this._updateTimer();
      }
      return this;
    },

    alterClassName : function(name, enable) {
      JX.DOM.alterClass(this._getContainer(), name, enable);
      return this;
    },

    setContent : function(content) {
      JX.DOM.setContent(this._getContainer(), content);
      return this;
    },

    setShowAsDesktopNotification : function(mode) {
      this._asDesktop = mode;
      return this;
    },

    setTitle : function(title) {
      this._title = title;
      return this;
    },

    setBody : function(body) {
      this._body = body;
      return this;
    },

    setHref : function(href) {
      this._href = href;
      return this;
    },

    setKey : function(key) {
      this._key = key;
      return this;
    },

    setIcon : function(icon) {
      this._icon = icon;
      return this;
    },

    /**
     * Set duration before the notification fades away, in milliseconds. If set
     * to 0, the notification persists until dismissed.
     *
     * @param int Notification duration, in milliseconds.
     * @return this
     */
    setDuration : function(milliseconds) {
      this._duration = milliseconds;
      this._updateTimer(false);
      return this;
    },

    _updateTimer : function() {
      if (this._hideTimer) {
        clearTimeout(this._hideTimer);
        this._hideTimer = null;
      }

      if (this._visible && this._duration) {
        this._hideTimer = setTimeout(JX.bind(this, this.hide), this._duration);
      }
    },

    _getContainer : function() {
      if (!this._container) {
        this._container = JX.$N(
          'div',
          {
            className: 'jx-notification',
            sigil: 'jx-notification'
          });
      }
      return this._container;
    }
  },

  statics : {
    supportsDesktopNotifications : function () {
      return 'Notification' in window;
    },
    desktopNotificationsEnabled : function () {
      return window.Notification.permission === 'granted';
    },
    _container : null,
    _listening : false,
    _active : [],
    _show : function(notification) {
      var self = JX.Notification;

      self._installListener();
      self._active.push(notification);
      self._redraw();
    },
    _hide : function(notification) {
      var self = JX.Notification;

      for (var ii = 0; ii < self._active.length; ii++) {
        if (self._active[ii] === notification) {
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
          // link, and we want to follow the link if they did. Instead, invoke
          // the activate event for the active notification and dismiss it if it
          // isn't handled.

          var target = e.getNode('jx-notification');
          for (var ii = 0; ii < self._active.length; ii++) {
            var n = self._active[ii];
            if (n._getContainer() === target) {
              var activation = n.invoke('activate');
              if (!activation.getPrevented()) {
                n.hide();
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

      // Show only a limited number of notifications at once.
      var limit = 5;

      var notifications = [];
      for (var ii = 0; ii < self._active.length; ii++) {
        notifications.push(self._active[ii]._getContainer());
        if (!(--limit)) {
          break;
        }
      }

      JX.DOM.setContent(self._container, notifications);
    }
  }

});
