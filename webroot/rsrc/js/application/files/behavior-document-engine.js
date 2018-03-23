/**
 * @provides javelin-behavior-document-engine
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('document-engine', function(config, statics) {



  function onmenu(e) {
    var node = e.getNode('document-engine-view-dropdown');
    var data = JX.Stratcom.getData(node);

    if (data.menu) {
      return;
    }

    e.prevent();

    var menu = new JX.PHUIXDropdownMenu(node);
    var list = new JX.PHUIXActionListView();

    var view;
    var engines = [];
    for (var ii = 0; ii < data.views.length; ii++) {
      var spec = data.views[ii];

      view = new JX.PHUIXActionView()
        .setName(spec.name)
        .setIcon(spec.icon)
        .setIconColor(spec.color)
        .setHref(spec.engineURI);

      view.setHandler(JX.bind(null, function(spec, e) {
        if (!e.isNormalClick()) {
          return;
        }

        e.prevent();
        menu.close();

        onview(data, spec, false);
      }, spec));

      list.addItem(view);

      engines.push({
        spec: spec,
        view: view
      });
    }

    menu.setContent(list.getNode());

    menu.listen('open', function() {
      for (var ii = 0; ii < engines.length; ii++) {
        var engine = engines[ii];

        // Highlight the current rendering engine.
        var is_selected = (engine.spec.viewKey == data.viewKey);
        engine.view.setSelected(is_selected);
      }
    });

    data.menu = menu;
    menu.open();
  }

  function onview(data, spec, immediate) {
    data.sequence = (data.sequence || 0) + 1;
    var handler = JX.bind(null, onrender, data, data.sequence);

    data.viewKey = spec.viewKey;
    JX.History.replace(spec.viewURI);

    new JX.Request(spec.engineURI, handler)
      .send();

    if (data.loadingView) {
      // If we're already showing "Loading...", immediately change it to
      // show the new document type.
      onloading(data, spec);
    } else if (!immediate) {
      // Otherwise, grey out the document and show "Loading..." after a
      // short delay. This prevents the content from flickering when rendering
      // is fast.
      var viewport = JX.$(data.viewportID);
      JX.DOM.alterClass(viewport, 'document-engine-in-flight', true);

      var load = JX.bind(null, onloading, data, spec);
      data.loadTimer = setTimeout(load, 333);
    }
  }

  function onloading(data, spec) {
    data.loadingView = true;

    var viewport = JX.$(data.viewportID);
    JX.DOM.alterClass(viewport, 'document-engine-in-flight', false);
    JX.DOM.setContent(viewport, JX.$H(spec.loadingMarkup));
  }

  function onrender(data, sequence, r) {
    // If this isn't the most recent request we sent, throw it away. This can
    // happen if the user makes multiple selections from the menu while we are
    // still rendering the first view.
    if (sequence != data.sequence) {
      return;
    }

    if (data.loadTimer) {
      clearTimeout(data.loadTimer);
      data.loadTimer = null;
    }

    var viewport = JX.$(data.viewportID);

    JX.DOM.alterClass(viewport, 'document-engine-in-flight', false);
    data.loadingView = false;

    JX.DOM.setContent(viewport, JX.$H(r.markup));
  }

  if (!statics.initialized) {
    JX.Stratcom.listen('click', 'document-engine-view-dropdown', onmenu);
    statics.initialized = true;
  }

  if (config.renderControlID) {
    var control = JX.$(config.renderControlID);
    var data = JX.Stratcom.getData(control);

    for (var ii = 0; ii < data.views.length; ii++) {
      if (data.views[ii].viewKey == data.viewKey) {
        onview(data, data.views[ii], true);
        break;
      }
    }
  }

});
