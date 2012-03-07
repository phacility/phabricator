/**
 * @provides javelin-behavior-aphront-basic-tokenizer
 * @requires javelin-behavior
 *           javelin-typeahead
 *           javelin-tokenizer
 *           javelin-typeahead-preloaded-source
 *           javelin-typeahead-ondemand-source
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-util
 */

JX.behavior('aphront-basic-tokenizer', function(config) {
  var root = JX.$(config.id);

  var datasource;
  if (config.ondemand) {
    datasource = new JX.TypeaheadOnDemandSource(config.src);
  } else {
    datasource = new JX.TypeaheadPreloadedSource(config.src);
  }

  // Sort results so that the viewing user always comes up first; after that,
  // prefer unixname matches to realname matches.

  var sort_handler = function(value, list, cmp) {
    var priority_hits = {};
    var self_hits     = {};

    var tokens = this.tokenize(value);

    for (var ii = 0; ii < list.length; ii++) {
      var item = list[ii];
      if (!item.priority) {
        continue;
      }

      if (config.username && item.priority == config.username) {
        self_hits[item.id] = true;
      }

      for (var jj = 0; jj < tokens.length; jj++) {
        if (item.priority.substr(0, tokens[jj].length) == tokens[jj]) {
          priority_hits[item.id] = true;
        }
      }
    }

    list.sort(function(u, v) {
      if (self_hits[u.id] != self_hits[v.id]) {
        return self_hits[v.id] ? 1 : -1;
      }

      if (priority_hits[u.id] != priority_hits[v.id]) {
        return priority_hits[v.id] ? 1 : -1;
      }

      return cmp(u, v);
    });
  };

  datasource.setSortHandler(JX.bind(datasource, sort_handler));
  datasource.setTransformer(
    function(object) {
      return {
        name : object[0],
        display : object[0],
        uri : object[1],
        id : object[2],
        priority : object[3]
      };
    });

  var typeahead = new JX.Typeahead(
    root,
    JX.DOM.find(root, 'input', 'tokenizer-input'));
  typeahead.setDatasource(datasource);

  var tokenizer = new JX.Tokenizer(root);
  tokenizer.setTypeahead(typeahead);

  if (config.limit) {
    tokenizer.setLimit(config.limit);
  }

  if (config.value) {
    tokenizer.setInitialValue(config.value);
  }

  JX.Stratcom.addData(root, {'tokenizer' : tokenizer});

  tokenizer.start();

});
