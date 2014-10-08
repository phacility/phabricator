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
      if (!item.priority) {
        continue;
      }

      for (var jj = 0; jj < tokens.length; jj++) {
        if (item.priority.substr(0, tokens[jj].length) == tokens[jj]) {
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
});
