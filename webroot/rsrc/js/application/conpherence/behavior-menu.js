/**
 * @provides javelin-behavior-conpherence-menu
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-request
 *           javelin-stratcom
 *           javelin-uri
 *           javelin-util
 *           javelin-workflow
 */

JX.behavior('conpherence-menu', function(config) {

  function onwidgetresponse(context, response) {
    var widgets = JX.$H(response.widgets);
    var widgetsRoot = JX.$(config.widgets_pane);
    JX.DOM.setContent(widgetsRoot, widgets);
  }

  function onresponse(context, response) {
    var header = JX.$H(response.header);
    var messages = JX.$H(response.messages);
    var form = JX.$H(response.form);
    var headerRoot = JX.$(config.header);
    var messagesRoot = JX.$(config.messages);
    var formRoot = JX.$(config.form_pane);
    var widgetsRoot = JX.$(config.widgets_pane);
    var menuRoot = JX.$(config.menu_pane);
    JX.DOM.setContent(headerRoot, header);
    JX.DOM.setContent(messagesRoot, messages);
    messagesRoot.scrollTop = messagesRoot.scrollHeight;
    JX.DOM.setContent(formRoot, form);

    var conpherences = JX.DOM.scry(
      menuRoot,
      'a',
      'conpherence-menu-click'
    );

    for (var i = 0; i < conpherences.length; i++) {
      var current = conpherences[i];
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
      var widget_uri = config.base_uri + 'widget/' + data.id + '/';
      new JX.Workflow(uri, {})
        .setHandler(JX.bind(null, onresponse, selected))
        .start();
      new JX.Workflow(widget_uri, {})
        .setHandler(JX.bind(null, onwidgetresponse, selected))
        .start();
    }
  );

  JX.Stratcom.listen('click', 'conpherence-edit-metadata', function (e) {
    e.kill();
    var root = JX.$(config.form_pane);
    var form = JX.DOM.find(root, 'form');
    var data = e.getNodeData('conpherence-edit-metadata');
    new JX.Workflow.newFromForm(form, data)
      .setHandler(function (r) {
        // update the header
        JX.DOM.setContent(
          JX.$(config.header),
          JX.$H(r.header)
        );

        // update the menu entry as well
        JX.DOM.replace(
          JX.$(r.conpherence_phid + '-nav-item'),
          JX.$H(r.nav_item)
        );
        JX.DOM.replace(
          JX.$(r.conpherence_phid + '-menu-item'),
          JX.$H(r.menu_item)
        );
      })
      .start();
  });

  JX.Stratcom.listen('click', 'show-older-messages', function(e) {
    e.kill();
    console.log(document.URL);
    new JX.Request('/conpherence/view/1/', function(r) {
      console.log('100');
    })
    .setData({offset: 100}) // get the next page
    .send();
  });

  // select the current message
  var selectedConpherence = false;
  if (config.selected_conpherence_id) {
    var selected = JX.$(config.selected_conpherence_id + '-nav-item');
    JX.Stratcom.invoke(
      'conpherence-initial-selected',
      null,
      { selected : selected }
    );
    selectedConpherence = true;
  }

});
