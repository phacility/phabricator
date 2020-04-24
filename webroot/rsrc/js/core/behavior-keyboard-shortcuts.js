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
  var pht = JX.phtize(config.pht);
  var workflow = null;

  new JX.KeyboardShortcut('?', pht('?'))
    .setGroup('global')
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

  if (config.searchID) {
    new JX.KeyboardShortcut('/', pht('/'))
      .setGroup('global')
      .setHandler(function() {
        var search = JX.$(config.searchID);
        search.focus();
        search.select();
      })
      .register();
  }

});
