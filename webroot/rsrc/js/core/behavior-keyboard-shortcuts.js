/**
 * @provides javelin-behavior-phabricator-keyboard-shortcuts
 * @requires javelin-behavior
 *           javelin-workflow
 *           javelin-json
 *           javelin-dom
 *           phabricator-keyboard-shortcut
 */

/**
 * Define global keyboard shortcuts.
 */
JX.behavior('phabricator-keyboard-shortcuts', function(config) {
  var workflow = null;

  var desc = 'Show keyboard shortcut help for the current page.';
  new JX.KeyboardShortcut('?', desc)
    .setHandler(function(manager) {
      if (workflow) {
        // Already showing the dialog.
        return;
      }
      var desc = manager.getShortcutDescriptions();
      var data = {keys : JX.JSON.stringify(desc)};
      workflow = new JX.Workflow(config.helpURI, data)
        .setCloseHandler(function() {
          workflow = null;
        });
      workflow.start();
    })
    .register();

});
