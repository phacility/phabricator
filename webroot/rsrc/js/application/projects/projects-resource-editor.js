/**
 * @requires javelin-behavior
 *           phabricator-prefab
 *           multirow-row-manager
 *           javelin-tokenizer
 *           javelin-typeahead-preloaded-source
 *           javelin-typeahead
 *           javelin-dom
 *           javelin-json
 *           javelin-util
 * @provides javelin-behavior-projects-resource-editor
 * @javelin
 */

JX.behavior('projects-resource-editor', function(config) {

  var root = JX.$(config.root);
  var resources_table = JX.DOM.find(root, 'table', 'resources');
  var manager = new JX.MultirowRowManager(resources_table);
  var resource_rows = [];

  for (var ii = 0; ii < config.state.length; ii++) {
    addRow(config.state[ii]);
  }

  function renderRow(data) {

    var template = JX.$N('div', JX.$H(config.tokenizerTemplate)).firstChild;
    template.id = '';
    var datasource = new JX.TypeaheadPreloadedSource(
      config.tokenizerSource);
    var typeahead = new JX.Typeahead(template);
    typeahead.setDatasource(datasource);
    var tokenizer = new JX.Tokenizer(template);
    tokenizer.setTypeahead(typeahead);
    tokenizer.setLimit(1);
    tokenizer.start();

    if (data.phid) {
      tokenizer.addToken(data.phid, data.name);
    }

    var role = JX.$N('input', {type: 'text', value : data.role || ''});

    var ownership = JX.Prefab.renderSelect(
      {0 : 'Nonowner', 1 : 'Owner'},
      data.owner || 0);

    var as_object = function() {
      var tokens = tokenizer.getTokens();
      return {
        phid : JX.keys(tokens)[0] || null,
        role : role.value,
        owner : ownership.value
      };
    }

    var r = [];
    r.push([null,                 JX.$N('label', {}, 'User:')]);
    r.push(['user-tokenizer',     template]);
    r.push(['role-label',         JX.$N('label', {}, 'Role:')]);
    r.push(['role',                role]);
    r.push([null,                 ownership]);

    for (var ii = 0; ii < r.length; ii++) {
      r[ii] = JX.$N('td', {className : r[ii][0]}, r[ii][1]);
    }

    return {
      nodes : r,
      dataCallback : as_object
    };
  }

  function onaddresource(e) {
    e.kill();
    addRow({});
  }

  function addRow(info) {
    var data = renderRow(info);
    var row = manager.addRow(data.nodes);
    var id = manager.getRowID(row);

    resource_rows[id] = data.dataCallback;
  }

  function onsubmit(e) {
    var result = [];
    for (var ii = 0; ii < resource_rows.length; ii++) {
      if (resource_rows[ii]) {
        var obj = resource_rows[ii]();
        result.push(obj);
      }
    }
    JX.$(config.input).value = JX.JSON.stringify(result);
  }

  JX.DOM.listen(
    root,
    'click',
    'add-resource',
    onaddresource);

  JX.DOM.listen(
    root,
    'submit',
    null,
    onsubmit);

  manager.listen(
    'row-removed',
    function(row_id) {
      delete resource_rows[row_id];
    });
});
