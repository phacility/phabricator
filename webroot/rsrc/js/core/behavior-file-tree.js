/**
 * @provides javelin-behavior-phabricator-file-tree
 * @requires javelin-behavior
 *           phabricator-keyboard-shortcut
 *           javelin-stratcom
 */

JX.behavior('phabricator-file-tree', function() {

  new JX.KeyboardShortcut('f', 'Toggle file tree.')
    .setHandler(function() {
      JX.Stratcom.invoke('differential-filetree-toggle');
    })
    .register();

});
