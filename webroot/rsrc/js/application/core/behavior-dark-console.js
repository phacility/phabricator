/**
 * @provides javelin-behavior-dark-console
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-util
 *           javelin-dom
 *           javelin-request
 *           phabricator-keyboard-shortcut
 *           javelin-behavior-dark-console-ajax
 */

JX.behavior('dark-console', function(config) {
  var selected_tab = null;

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

      selected_tab = target.id.replace('dark-console-tab-', '');

      new JX.Request(config.uri, JX.bag)
        .setData({ tab : selected_tab })
        .send();
    });

  var desc = 'Toggle visibility of DarkConsole.';
  new JX.KeyboardShortcut('`', desc)
    .setHandler(function(manager) {
      var console = JX.DOM.find(document.body, 'table', 'dark-console');
      var requestLog = JX.DOM.find(
        document.body,
        'table',
        'dark-console-request-log');

      config.visible = !config.visible;
      if (config.visible) {
        JX.DOM.show(console);
        JX.DOM.show(requestLog);
      } else {
        JX.DOM.hide(console);
        JX.DOM.hide(requestLog);
      }

      new JX.Request(config.uri, JX.bag)
        .setData({visible: config.visible ? 1 : 0})
        .send();
    })
    .register();

 var initRequestLog = function() {
   var console      = JX.DOM.find(document.body,
                                  'table',
                                  'dark-console');
   var requestLog   = JX.DOM.find(document.body,
                                  'table',
                                  'dark-console-request-log');
   var requestTable = JX.DOM.find(requestLog,    'table');
   var rows         = JX.DOM.scry(requestTable,  'tr');
   var tableHeader  = rows[0];
   var newRowNumber = JX.$N(
     'a',
     {
       'sigil' : 'request-log-number',
       'meta'  : { 'console' : console }
     },
     "0"
   );
   var newRowURI = JX.$N(
     'a',
     {
       'sigil' : 'request-log-uri',
       'meta'  : { 'console' : console }
     },
     config.request_uri
   );

   var newRow = JX.$N(
     'tr',
     {
       'className' : 'highlight'
     },
     [
       JX.$N('td', {}, newRowNumber),
       JX.$N('td', {}, 'main'),
       JX.$N('td', {}, newRowURI)
      ]
   );

   JX.DOM.setContent(requestTable, [tableHeader, newRow]);
 }

 initRequestLog();

 var updateActiveRequest = function(e) {
    var log        = e.getNode('dark-console-request-log');
    var table      = JX.DOM.find(log, 'table');
    var rows       = JX.DOM.scry(table, 'tr');
    var targetRow  = e.getTarget().parentNode.parentNode;
    var data       = JX.Stratcom.getData(e.getTarget());
    var newConsole = data.console;
    for (var ii = 0; ii < rows.length; ii++) {
       JX.DOM.alterClass(
         rows[ii],
         'highlight',
         rows[ii] == targetRow);
    }
    var console = JX.DOM.find(document.body, 'table', 'dark-console');
    JX.DOM.replace(console, newConsole);
    if (selected_tab) {
      console     = JX.DOM.find(document.body, 'table', 'dark-console');
      var s_id    = 'dark-console-tab-' + selected_tab;
      var tabs    = JX.DOM.scry(console, 'a', 'dark-console-tab');
      var panels  = JX.DOM.scry(console, 'div', 'dark-console-panel');
      for (var ii = 0; ii < tabs.length; ii++) {
        JX.DOM.alterClass(
          tabs[ii],
          'dark-console-tab-selected',
          tabs[ii].id == s_id);
        (tabs[ii].id != s_id ? JX.DOM.hide : JX.DOM.show)(panels[ii]);
      }
    }
 }

 JX.Stratcom.listen(
   'click',
   ['dark-console-request-log', 'request-log-number'],
   updateActiveRequest
   );
 JX.Stratcom.listen(
   'click',
   ['dark-console-request-log', 'request-log-uri'],
   updateActiveRequest
   );

});
