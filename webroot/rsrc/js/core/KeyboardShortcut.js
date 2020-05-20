/**
 * @provides phabricator-keyboard-shortcut
 * @requires javelin-install
 *           javelin-util
 *           phabricator-keyboard-shortcut-manager
 * @javelin
 */

/**
 * Register a keyboard shortcut, which does something when the user presses a
 * key with no other inputs focused.
 */
JX.install('KeyboardShortcut', {

  construct : function(keys, description) {
    keys = JX.$AX(keys);
    this.setKeys(keys);
    this.setDescription(description);
  },

  properties : {
    keys : null,
    group: null,
    description : null,
    handler : null,
    tooltipHandler : null
  },

  members : {
    register : function() {
      JX.KeyboardShortcutManager.getInstance().addKeyboardShortcut(this);
      return this;
    }
  }

});
