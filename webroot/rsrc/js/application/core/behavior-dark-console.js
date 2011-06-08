/**
 * @provides javelin-behavior-dark-console
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-util
 *           javelin-dom
 *           javelin-request
 *           phabricator-keyboard-shortcut
 */

JX.behavior('dark-console', function(config) {
  JX.Stratcom.listen(
    'click',
    ['dark-console', 'dark-console-tab'],
    function(e) {
      var console = e.getNode('dark-console');
      var tabs    = JX.DOM.scry(console, 'a', 'dark-console-tab');
      var panels  = JX.DOM.scry(console, 'div', 'dark-console-panel');
      var target  = e.getTarget();
      for (var ii = 0; ii < tabs.length; ii++) {
        JX.DOM.alterClass(
          tabs[ii],
          'dark-console-tab-selected',
          tabs[ii] == target);
        (tabs[ii] != target ? JX.DOM.hide : JX.DOM.show)(panels[ii]);
      }

      new JX.Request(config.uri, JX.bag)
        .setData({tab: target.id.replace('dark-console-tab-', '')})
        .send();
    });

  var desc = 'Toggle visibility of DarkConsole.';
  new JX.KeyboardShortcut('`', desc)
    .setHandler(function(manager) {
      var console = JX.DOM.find(document.body, 'table', 'dark-console');

      config.visible = !config.visible;
      if (config.visible) {
        JX.DOM.show(console);
      } else {
        JX.DOM.hide(console);
      }

      new JX.Request(config.uri, JX.bag)
        .setData({visible: config.visible ? 1 : 0})
        .send();
    })
    .register();
});
