/**
 * @provides javelin-behavior-dark-console
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

  JX.Stratcom.listen(
    'keypress',
    null,
    function(e) {
      var raw = e.getRawEvent();
      if ((String.fromCharCode(raw.charCode).charAt(0) == '`') &&
          !raw.shiftKey &&
          !raw.metaKey) {

        if (JX.Stratcom.pass()) {
          return;
        }

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
      }
    });
});
