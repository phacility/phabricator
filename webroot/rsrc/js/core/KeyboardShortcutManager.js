/**
 * @provides phabricator-keyboard-shortcut-manager
 * @requires javelin-install
 *           javelin-util
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
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

    /**
     * Some keys don't invoke keypress events in some browsers. We handle these
     * on keydown instead of keypress.
     */
    _downkeys : {
      left: 1,
      right: 1,
      up: 1,
      down: 1
    },

    /**
     * Some keys require Alt to be pressed in order to type them on certain
     * keyboard layouts.
     */
    _altkeys: {
      // "Alt+L" on German layouts.
      '@': 1,

      // "Alt+Shift+7" on German layouts.
      '\\': 1
    },

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

    /**
     * Scroll an element into view.
     */
    scrollTo : function(node) {
      var scroll_distance = JX.Vector.getAggregateScrollForNode(node);
      var node_position = JX.$V(node);
      JX.DOM.scrollToPosition(0, node_position.y + scroll_distance.y - 60);
    },

    _onkeypress : function(e) {
      if (!(this._getKey(e) in JX.KeyboardShortcutManager._downkeys)) {
        this._onkeyhit(e);
      }
    },
    _onkeyhit : function(e) {
      var self = JX.KeyboardShortcutManager;

      var raw = e.getRawEvent();

      if (raw.ctrlKey || raw.metaKey) {
        // Never activate keyboard shortcuts if modifier keys are also
        // depressed.
        return;
      }

      // For most keystrokes, don't activate keyboard shortcuts if the Alt
      // key is depressed. However, we continue if the character requires the
      // use of Alt to type it on some keyboard layouts.
      var key = this._getKey(e);
      if (raw.altKey && !(key in self._altkeys)) {
        return;
      }

      var target = e.getTarget();
      var ignore = ['input', 'select', 'textarea', 'object', 'embed'];
      if (JX.DOM.isType(target, ignore)) {
        // Never activate keyboard shortcuts if the user has some other control
        // focused.
        return;
      }

      var key = this._getKey(e);

      var shortcuts = this._shortcuts;
      for (var ii = 0; ii < shortcuts.length; ii++) {
        var keys = shortcuts[ii].getKeys();
        for (var jj = 0; jj < keys.length; jj++) {
          if (keys[jj] == key) {
            shortcuts[ii].getHandler()(this);
            e.kill(); // Consume the event
            return;
          }
        }
      }
    },
    _onkeydown : function(e) {
      this._handleTooltipKeyEvent(e, true);

      if (this._getKey(e) in JX.KeyboardShortcutManager._downkeys) {
        this._onkeyhit(e);
      }
    },
    _onkeyup : function(e) {
      this._handleTooltipKeyEvent(e, false);
    },
    _getKey : function(e) {
      return e.getSpecialKey() || String.fromCharCode(e.getRawEvent().charCode);
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
