/**
 * @provides javelin-behavior-document-engine
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('document-engine', function() {

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

        onview(data, spec);
      }, spec));

      list.addItem(view);
    }

    menu.setContent(list.getNode());

    data.menu = menu;
    menu.open();
  }

  function onview(data, spec) {
    var handler = JX.bind(null, onrender, data);

    new JX.Request(spec.engineURI, handler)
      .send();
  }

  function onrender(data, r) {
    var viewport = JX.$(data.viewportID);

    JX.DOM.setContent(viewport, JX.$H(r.markup));
  }

  JX.Stratcom.listen('click', 'document-engine-view-dropdown', onmenu);

});
