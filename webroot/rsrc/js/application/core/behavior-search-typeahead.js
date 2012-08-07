/**
 * @provides javelin-behavior-phabricator-search-typeahead
 * @requires javelin-behavior
 *           javelin-typeahead-ondemand-source
 *           javelin-typeahead
 *           javelin-dom
 *           javelin-uri
 *           javelin-util
 *           javelin-stratcom
 */

JX.behavior('phabricator-search-typeahead', function(config) {

  var datasource = new JX.TypeaheadOnDemandSource(config.src);

  function transform(object) {
    var attr = {
      className: 'phabricator-main-search-typeahead-result'
    }

    if (object[6]) {
      attr.style = {backgroundImage: 'url('+object[6]+')'};
    }

    var render = JX.$N(
      'span',
      attr,
      [
        JX.$N('span', {className: 'result-name'}, object[4] || object[0]),
        JX.$N('span', {className: 'result-type'}, object[5])
      ]);

    return {
      name: object[0],
      display: render,
      uri: object[1],
      id: object[2],
      priority: object[3],
      type: object[7]
    };
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
      // TODO: Put jump nav hits like "D123" first.
      'apps' : 2,
      'user' : 3,
      'symb' : 4
    };

    var tokens = this.tokenize(value);

    for (var ii = 0; ii < list.length; ii++) {
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
      var u_type = type_priority[u.type] || 999;
      var v_type = type_priority[v.type] || 999;

      if (u_type != v_type) {
        return u_type - v_type;
      }

      if (priority_hits[u.id] != priority_hits[v.id]) {
        return priority_hits[v.id] ? 1 : -1;
      }

      return cmp(u, v);
    });

    // If we have more results than fit, limit each type of result to 3, so
    // we show 3 applications, then 3 users, etc.
    var type_count = 0;
    var current_type = null;
    for (var ii = 0; ii < list.length; ii++) {
      if (list.length <= config.limit) {
        break;
      }
      if (list[ii].type != current_type) {
        current_type = list[ii].type;
        type_count = 1;
      } else {
        type_count++;
        if (type_count > 3) {
          list.splice(ii, 1);
          ii--;
        }
      }
    }

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
});
