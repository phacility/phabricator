/**
 * @provides javelin-behavior-phabricator-keyboard-pager
 * @requires javelin-behavior
 *           javelin-uri
 *           phabricator-keyboard-shortcut
 */

JX.behavior('phabricator-keyboard-pager', function(config) {

  new JX.KeyboardShortcut('[', 'Prev Page')
    .setHandler(function() {
      if (config.prev) {
        JX.$U(config.prev).go();
      }
    })
    .register();

  new JX.KeyboardShortcut(']', 'Next Page')
    .setHandler(function() {
      if (config.next) {
        JX.$U(config.next).go();
      }
    })
    .register();

});
