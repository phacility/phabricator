/**
 * @provides javelin-behavior-phabricator-search-typeahead
 * @requires javelin-behavior
 *           javelin-typeahead-ondemand-source
 *           javelin-typeahead
 *           javelin-dom
 *           javelin-uri
 *           javelin-util
 *           javelin-stratcom
 *           phabricator-prefab
 *           phuix-icon-view
 */

JX.behavior('phabricator-search-typeahead', function(config) {

  var datasource = new JX.TypeaheadOnDemandSource(config.src);

  function transform(object) {
    object = JX.Prefab.transformDatasourceResults(object);

    var attr = {
      className: 'phabricator-main-search-typeahead-result'
    };

    if (object.imageURI) {
      attr.style = {backgroundImage: 'url('+object.imageURI+')'};
    }

    var icon = null;
    if (object.icon) {
      icon = new JX.PHUIXIconView()
        .setIcon(object.icon)
        .setColor('lightgreytext')
        .getNode();
      icon = [icon, ' '];
    }

    var render = JX.$N(
      'span',
      attr,
      [
        JX.$N('span', {className: object.sprite}),
        JX.$N('span', {className: 'result-name'}, object.displayName),
        icon,
        JX.$N('span', {className: 'result-type'}, object.type)
      ]);

    if (object.closed) {
      JX.DOM.alterClass(render, 'result-closed', true);
    }

    object.display = render;

    return object;
  }

  datasource.setTransformer(transform);

  var sort_handler = function(value, list, cmp) {
    // First, sort all the results normally.
    JX.bind(this, JX.Prefab.sortHandler, {}, value, list, cmp)();

    // Now we're going to apply some special rules to order results by type,
    // so applications always appear near the top, then users, etc.
    var ii;

    var type_order = [
      'jump',
      'apps',
      'proj',
      'user',
      'repo',
      'symb',
      'misc'
    ];

    var type_map = {};
    for (ii = 0; ii < type_order.length; ii++) {
      type_map[type_order[ii]] = true;
    }

    var buckets = {};
    for (ii = 0; ii < list.length; ii++) {
      var item = list[ii];

      var type = item.priorityType;
      if (!type_map.hasOwnProperty(type)) {
        type = 'misc';
      }

      if (!buckets.hasOwnProperty(type)) {
        buckets[type] = [];
      }

      buckets[type].push(item);
    }

    // If we have more results than fit, limit each type of result to 3, so
    // we show 3 applications, then 3 users, etc. For jump items, we show only
    // one result.
    var jj;
    var results = [];
    for (ii = 0; ii < type_order.length; ii++) {
      var current_type = type_order[ii];
      var type_list = buckets[current_type] || [];
      for (jj = 0; jj < type_list.length; jj++) {

        // Skip this item if:
        //   - it's a jump nav item, and we already have at least one jump
        //     nav item; or
        //   - we have more items than will fit in the typeahead, and this
        //     is the 4..Nth result of its type.

        var skip = ((current_type == 'jump') && (jj >= 1)) ||
                   ((list.length > config.limit) && (jj >= 3));
        if (skip) {
          continue;
        }

        results.push(type_list[jj]);
      }
    }

    // Replace the list in place with the results.
    list.splice.apply(list, [0, list.length].concat(results));
  };

  datasource.setSortHandler(JX.bind(datasource, sort_handler));
  datasource.setMaximumResultCount(config.limit);

  var typeahead = new JX.Typeahead(JX.$(config.id), JX.$(config.input));
  typeahead.setDatasource(datasource);
  typeahead.setPlaceholder(config.placeholder);

  typeahead.listen('choose', function(r) {
    JX.$U(r.href).go();
    JX.Stratcom.context().kill();
  });

  typeahead.start();

  JX.DOM.listen(JX.$(config.button), 'click', null, function () {
    typeahead.setPlaceholder('');
    typeahead.updatePlaceHolder();
  });


  // When the user navigates between applications, we need to update the
  // input in the document, the icon on the button, and the icon in the
  // menu.
  JX.Stratcom.listen(
    'quicksand-redraw',
    null,
    function(e) {
      var r = e.getData().newResponse;
      updateCurrentApplication(r.applicationClass, r.applicationSearchIcon);
    });

  var current_app_icon;
  function updateCurrentApplication(app_class, app_icon) {
    current_app_icon = app_icon || config.defaultApplicationIcon;

    // Update the icon on the button.
    var button = JX.$(config.selectorID);
    var data = JX.Stratcom.getData(button);
    if (data.value == config.appScope) {
      updateIcon(button, data, current_app_icon);
    }

    // Set the hidden input to the new value.
    JX.$(config.applicationID).value = app_class;
  }

  function updateIcon(button, data, new_icon) {
    var icon = JX.DOM.find(button, 'span', 'global-search-dropdown-icon');
    JX.DOM.alterClass(icon, data.icon, false);
    data.icon = new_icon;
    JX.DOM.alterClass(icon, data.icon, true);
  }

  // Implement the scope selector menu for the global search.
  JX.Stratcom.listen('click', 'global-search-dropdown', function(e) {
    var data = e.getNodeData('global-search-dropdown');
    var button = e.getNode('global-search-dropdown');
    if (data.menu) {
      return;
    }

    e.kill();

    function updateValue(spec) {
      if (data.value == spec.value) {
        return;
      }

      // Swap out the icon.
      updateIcon(button, data, spec.icon);

      // Update the value.
      data.value = spec.value;

      // Update the form input.
      var frame = button.parentNode;
      var input = JX.DOM.find(frame, 'input', 'global-search-dropdown-input');
      input.value = data.value;

      new JX.Request(config.scopeUpdateURI)
        .setData({value: data.value})
        .send();
    }

    var menu = new JX.PHUIXDropdownMenu(button)
      .setAlign('left');
    data.menu = menu;

    menu.listen('open', function() {
      var list = new JX.PHUIXActionListView();

      for (var ii = 0; ii < data.items.length; ii++) {
        var spec = data.items[ii];

        // If this is the "Search Current Application" item and we've
        // navigated to a page which sent us new information about the
        // icon, update the icon so the menu reflects the icon for the
        // current application.
        if (spec.value == config.appScope) {
          if (current_app_icon !== undefined) {
            spec.icon = current_app_icon;
          }
        }

        var item = new JX.PHUIXActionView()
          .setName(spec.name)
          .setIcon(spec.icon);

        if (spec.value) {
          if (spec.value == data.value) {
            item.setSelected(true);
          }

          var handler = function(spec, e) {
            e.prevent();
            menu.close();
            updateValue(spec);
          };

          item.setHandler(JX.bind(null, handler, spec));
        } else if (spec.href) {
          item.setHref(spec.href);
          item.setHandler(function() { menu.close(); });
        } else {
          item.setLabel(true);
        }

        list.addItem(item);
      }

      menu.setContent(list.getNode());
    });

    menu.open();
  });


});
