/**
 * @provides javelin-behavior-conpherence-menu
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-workflow
 *           javelin-util
 *           javelin-stratcom
 *           javelin-uri
 */

JX.behavior('conpherence-menu', function(config) {

  function onresponse(context, response) {
    var header = JX.$H(response.header);
    var messages = JX.$H(response.messages);
    var form = JX.$H(response.form);
    var widgets = JX.$H(response.widgets);
    var headerRoot = JX.$(config.header);
    var messagesRoot = JX.$(config.messages);
    var formRoot = JX.$(config.form_pane);
    var widgetsRoot = JX.$(config.widgets_pane);
    JX.DOM.setContent(headerRoot, header);
    JX.DOM.setContent(messagesRoot, messages);
    messagesRoot.scrollTop = messagesRoot.scrollHeight;
    JX.DOM.setContent(formRoot, form);
    JX.DOM.setContent(widgetsRoot, widgets);

    for (var i = 0; i < context.parentNode.childNodes.length; i++) {
      var current = context.parentNode.childNodes[i];
      if (current.id == context.id) {
        JX.DOM.alterClass(current, 'conpherence-selected', true);
        JX.DOM.alterClass(current, 'hide-unread-count', true);
      } else {
        JX.DOM.alterClass(current, 'conpherence-selected', false);
      }
    }

    // TODO - update the browser URI T2086

    JX.Stratcom.invoke(
      'conpherence-selected-loaded',
      null,
      {}
    );
  }

  JX.Stratcom.listen(
    'click',
    'conpherence-menu-click',
    function(e) {
      e.kill();
      var selected = e.getNode(['conpherence-menu-click']);
      if (config.fancy_ajax) {
        JX.Stratcom.invoke(
          'conpherence-selected',
          null,
          { selected : selected }
        );
      } else {
        var data = JX.Stratcom.getData(selected);
        var uri = new JX.URI(config.base_uri + data.id + '/');
        uri.go();
      }
    }
  );

  JX.Stratcom.listen(
    'conpherence-initial-selected',
    null,
    function(e) {
      var selected = e.getData().selected;
      e.kill();
      JX.Stratcom.invoke(
        'conpherence-selected',
        null,
        { selected : selected }
      );
    }
  );

  JX.Stratcom.listen(
    'conpherence-selected',
    null,
    function(e) {

      var selected = e.getData().selected;
      var data = JX.Stratcom.getData(selected);

      var uri = config.base_uri + 'view/' + data.id + '/';
      new JX.Workflow(uri, {})
        .setHandler(JX.bind(null, onresponse, selected))
        .start();
    }
  );

});
