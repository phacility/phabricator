/**
 * @provides javelin-behavior-maniphest-batch-editor
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-prefab
 *           multirow-row-manager
 *           javelin-tokenizer
 *           javelin-typeahead-preloaded-source
 *           javelin-typeahead
 *           javelin-json
 */

JX.behavior('maniphest-batch-editor', function(config) {

  var root = JX.$(config.root);
  var editor_table = JX.DOM.find(root, 'table', 'maniphest-batch-actions');
  var manager = new JX.MultirowRowManager(editor_table);
  var action_rows = [];

  addRow({});

  function renderRow(data) {

    var action_select = JX.Prefab.renderSelect(
      {
        'add_project': 'Add Projects',
        'remove_project' : 'Remove Projects'/*,
        'priority': 'Change Priority',
        'add_comment': 'Comment',
        'status': 'Open / Close',
        'assign': 'Assign'*/
      });

    var tokenizer = build_tokenizer(config.sources.project)

    var r = [];
    r.push([null, action_select]);
    r.push(['batch-editor-input', tokenizer.template]);

    for (var ii = 0; ii < r.length; ii++) {
      r[ii] = JX.$N('td', {className : r[ii][0]}, r[ii][1]);
    }

    return {
      nodes : r,
      dataCallback : function() {
        return {
          action: action_select.value,
          value: JX.keys(tokenizer.object.getTokens())
        };
      }
    };
  }

  function onaddaction(e) {
    e.kill();
    addRow({});
  }

  function addRow(info) {
    var data = renderRow(info);
    var row = manager.addRow(data.nodes);
    var id = manager.getRowID(row);

    action_rows[id] = data.dataCallback;
  }

  function onsubmit(e) {
    var input = JX.$(config.input);

    var actions = [];
    for (var k in action_rows) {
      actions.push(action_rows[k]());
    }

    input.value = JX.JSON.stringify(actions);
  }

  JX.DOM.listen(
    root,
    'click',
    'add-action',
    onaddaction);

  JX.DOM.listen(
    root,
    'submit',
    null,
    onsubmit);

  manager.listen(
    'row-removed',
    function(row_id) {
      delete action_rows[row_id];
    });

  function build_tokenizer(source) {
    var template = JX.$N('div', JX.$H(config.tokenizerTemplate)).firstChild;
    template.id = '';
    var datasource = new JX.TypeaheadPreloadedSource(source);
    var typeahead = new JX.Typeahead(template);
    typeahead.setDatasource(datasource);
    var tokenizer = new JX.Tokenizer(template);
    tokenizer.setTypeahead(typeahead);
    tokenizer.start();

    return {
      object: tokenizer,
      template: template
    };
  }

});
