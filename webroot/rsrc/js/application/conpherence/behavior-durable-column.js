/**
 * @provides javelin-behavior-durable-column
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-scrollbar
 *           phabricator-keyboard-shortcut
 */

JX.behavior('durable-column', function() {

  var frame = JX.$('phabricator-standard-page');
  var show = false;

  new JX.KeyboardShortcut('\\', 'Toggle Column (Prototype)')
    .setHandler(function() {
      show = !show;
      JX.DOM.alterClass(frame, 'with-durable-column', show);
      JX.$('durable-column').style.display = (show ? 'block' : 'none');
      JX.Stratcom.invoke('resize');
    })
    .register();

  new JX.Scrollbar(JX.$('phui-durable-column-content'));


});
