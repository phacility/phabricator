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

    list.addItem(
      new JX.PHUIXActionView()
        .setDivider(true));

    var encode_item = new JX.PHUIXActionView()
      .setName(data.encode.name)
      .setIcon(data.encode.icon);

    var onencode = JX.bind(null, function(data, e) {
      e.prevent();

      if (encode_item.getDisabled()) {
        return;
      }

      new JX.Workflow(data.encode.uri, {encoding: data.encode.value})
        .setHandler(function(r) {
          data.encode.value = r.encoding;
          onview(data);
        })
        .start();

      menu.close();

    }, data);

    encode_item.setHandler(onencode);

    list.addItem(encode_item);

    var highlight_item = new JX.PHUIXActionView()
      .setName(data.highlight.name)
      .setIcon(data.highlight.icon);

    var onhighlight = JX.bind(null, function(data, e) {
      e.prevent();

      if (highlight_item.getDisabled()) {
        return;
      }

      new JX.Workflow(data.highlight.uri, {highlight: data.highlight.value})
        .setHandler(function(r) {
          data.highlight.value = r.highlight;
          onview(data);
        })
        .start();

      menu.close();
    }, data);

    highlight_item.setHandler(onhighlight);

    list.addItem(highlight_item);

    menu.setContent(list.getNode());

    menu.listen('open', function() {
      for (var ii = 0; ii < engines.length; ii++) {
        var engine = engines[ii];

        // Highlight the current rendering engine.
        var is_selected = (engine.spec.viewKey == data.viewKey);
        engine.view.setSelected(is_selected);

        if (is_selected) {
          encode_item.setDisabled(!engine.spec.canEncode);
          highlight_item.setDisabled(!engine.spec.canHighlight);
        }
      }
    });

    data.menu = menu;
    menu.open();
  }

  function add_params(uri, data) {
    uri = JX.$U(uri);

    if (data.highlight.value) {
      uri.setQueryParam('highlight', data.highlight.value);
    }

    if (data.encode.value) {
      uri.setQueryParam('encode', data.encode.value);
    }

    return uri.toString();
  }

  function onview(data, spec, immediate) {
    if (!spec) {
      for (var ii = 0; ii < data.views.length; ii++) {
        if (data.views[ii].viewKey == data.viewKey) {
          spec = data.views[ii];
          break;
        }
      }
    }

    data.sequence = (data.sequence || 0) + 1;
    var handler = JX.bind(null, onrender, data, data.sequence);

    data.viewKey = spec.viewKey;

    var uri = add_params(spec.engineURI, data);

    new JX.Request(uri, handler)
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

      // Replace the URI with the URI for the specific rendering the user
      // has selected.

      var view_uri = add_params(spec.viewURI, data);
      JX.History.replace(view_uri);
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

  if (config && config.renderControlID) {
    var control = JX.$(config.renderControlID);
    var data = JX.Stratcom.getData(control);
    onview(data, null, true);
  }

});
