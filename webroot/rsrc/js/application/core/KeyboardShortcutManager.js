/**
 * @provides phabricator-keyboard-shortcut-manager
 * @requires javelin-install
 *           javelin-util
 *           javelin-stratcom
 *           javelin-dom
 * @javelin
 */

JX.install('KeyboardShortcutManager', {

  construct : function() {
    this._shortcuts = [];

    JX.Stratcom.listen('keypress', null, JX.bind(this, this._onkeypress));
    JX.Stratcom.listen('keydown', null, JX.bind(this, this._onkeydown));
    JX.Stratcom.listen('keyup', null, JX.bind(this, this._onkeyup));
  },

  statics : {
    _instance : null,
    getInstance : function() {
      if (!JX.KeyboardShortcutManager._instance) {
        JX.KeyboardShortcutManager._instance = new JX.KeyboardShortcutManager();
      }
      return JX.KeyboardShortcutManager._instance;
    }
  },

  members : {
    _shortcuts : null,

    /**
     * Instead of calling this directly, you should call
     * KeyboardShortcut.register().
     */
    addKeyboardShortcut : function(s) {
      this._shortcuts.push(s);
    },
    getShortcutDescriptions : function() {
      var desc = [];
      for (var ii = 0; ii < this._shortcuts.length; ii++) {
        desc.push({
          keys : this._shortcuts[ii].getKeys(),
          description : this._shortcuts[ii].getDescription()
        });
      }
      return desc;
    },
    _onkeypress : function(e) {
      var raw = e.getRawEvent();

      if (raw.altKey || raw.ctrlKey || raw.metaKey) {
        // Never activate keyboard shortcuts if modifier keys are also
        // depressed.
        return;
      }

      var target = e.getTarget();
      var ignore = ['input', 'select', 'textarea', 'object', 'embed'];
      if (JX.DOM.isType(target, ignore)) {
        // Never activate keyboard shortcuts if the user has some other control
        // focused.
        return;
      }
      // TODO: This likely needs to be refined to deal with arrow keys, etc.
      var key = String.fromCharCode(raw.charCode);

      var shortcuts = this._shortcuts;
      for (var ii = 0; ii < shortcuts.length; ii++) {
        var keys = shortcuts[ii].getKeys();
        for (var jj = 0; jj < keys.length; jj++) {
          if (keys[jj] == key) {
            shortcuts[ii].getHandler()(this);
            return;
          }
        }
      }
    },
    _onkeydown : function(e) {
      this._handleTooltipKeyEvent(e, true);
    },
    _onkeyup : function(e) {
      this._handleTooltipKeyEvent(e, false);
    },
    _handleTooltipKeyEvent : function(e, is_keydown) {
      if (e.getRawEvent().keyCode != 18) {
        // If this isn't the alt/option key, don't do anything.
        return;
      }
      // Fire all the shortcut handlers.
      var shortcuts = this._shortcuts;
      for (var ii = 0; ii < shortcuts.length; ii++) {
        var handler = shortcuts[ii].getTooltipHandler();
        handler && handler(this, is_keydown);
      }
    }

  }
});
