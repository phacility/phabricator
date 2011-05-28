/**
 * @provides javelin-behavior-phabricator-keyboard-shortcuts
 * @requires javelin-behavior
 *           javelin-workflow
 *           javelin-json
 *           phabricator-keyboard-shortcut
 */

/**
 * Define global keyboard shortcuts.
 */
JX.behavior('phabricator-keyboard-shortcuts', function(config) {
  var desc = 'Show keyboard shortcut help for the current page.';
  new JX.KeyboardShortcut('?', desc)
    .setHandler(function(manager) {
      var desc = manager.getShortcutDescriptions();
      new JX.Workflow(config.helpURI, {keys : JX.JSON.serialize(desc)})
        .start();
    })
    .register();
});
