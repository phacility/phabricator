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

    var render = JX.$N(
      'span',
      attr,
      [
        JX.$N('span', {className: object.sprite}),
        JX.$N('span', {className: 'result-name'}, object.displayName),
        JX.$N('span', {className: 'result-type'}, object.type)
      ]);

    object.display = render;

    return object;
  }

  datasource.setTransformer(transform);

  // Sort handler that orders results by type (e.g., applications, users)
  // and then selects for good matches on the "priority" substrings if they
  // exist (for instance, username matches are preferred over real name
  // matches, and application name matches are preferred over application
  // flavor text matches).

  var sort_handler = function(value, list, cmp) {
    var priority_hits = {};
    var type_priority = {
      'jump' : 1,
      'apps' : 2,
      'proj' : 3,
      'user' : 4,
      'symb' : 5
    };

    var tokens = this.tokenize(value);

    var ii;
    for (ii = 0; ii < list.length; ii++) {
      var item = list[ii];

      for (var jj = 0; jj < tokens.length; jj++) {
        if (item.name.indexOf(tokens[jj]) === 0) {
          priority_hits[item.id] = true;
        }
      }

      if (!item.priority) {
        continue;
      }

      for (var hh = 0; hh < tokens.length; hh++) {
        if (item.priority.substr(0, tokens[hh].length) == tokens[hh]) {
          priority_hits[item.id] = true;
        }
      }
    }

    list.sort(function(u, v) {
      var u_type = type_priority[u.priorityType] || 999;
      var v_type = type_priority[v.priorityType] || 999;

      if (u_type != v_type) {
        return u_type - v_type;
      }

      if (priority_hits[u.id] != priority_hits[v.id]) {
        return priority_hits[v.id] ? 1 : -1;
      }

      return cmp(u, v);
    });

    // If we have more results than fit, limit each type of result to 3, so
    // we show 3 applications, then 3 users, etc. For jump items, we show only
    // one result.
    var type_count = 0;
    var current_type = null;
    for (ii = 0; ii < list.length; ii++) {
      if (list[ii].type != current_type) {
        current_type = list[ii].type;
        type_count = 1;
      } else {
        type_count++;

        // Skip this item if:
        //   - it's a jump nav item, and we already have at least one jump
        //     nav item; or
        //   - we have more items than will fit in the typeahead, and this
        //     is the 4..Nth result of its type.

        var skip = ((current_type == 'jump') && (type_count > 1)) ||
                   ((list.length > config.limit) && (type_count > 3));
        if (skip) {
          list.splice(ii, 1);
          ii--;
        }
      }
    }

  };

  datasource.setSortHandler(JX.bind(datasource, sort_handler));
  datasource.setFilterHandler(JX.Prefab.filterClosedResults);
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
