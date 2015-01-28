/**
 * @provides javelin-behavior-durable-column
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-scrollbar
 *           javelin-quicksand
 *           phabricator-keyboard-shortcut
 */

JX.behavior('durable-column', function() {

  var frame = JX.$('phabricator-standard-page');
  var quick = JX.$('phabricator-standard-page-body');
  var show = false;

  new JX.KeyboardShortcut('\\', 'Toggle Column (Prototype)')
    .setHandler(function() {
      show = !show;
      JX.DOM.alterClass(frame, 'with-durable-column', show);
      JX.$('durable-column').style.display = (show ? 'block' : 'none');
      JX.Stratcom.invoke('resize');
      JX.Quicksand.setFrame(show ? quick : null);
    })
    .register();

  new JX.Scrollbar(JX.$('phui-durable-column-content'));

  JX.Quicksand.start();

});
