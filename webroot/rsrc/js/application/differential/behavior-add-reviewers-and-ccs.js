/**
 * @provides javelin-behavior-differential-add-reviewers-and-ccs
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-tokenizer
 *           javelin-typeahead
 *           javelin-typeahead-preloaded-source
 */

JX.behavior('differential-add-reviewers-and-ccs', function(config) {

  function buildTokenizer(id, props) {
    var root = JX.$(id);

    var datasource;
    if (props.ondemand) {
      datasource = new JX.TypeaheadOnDemandSource(props.src);
    } else {
      datasource = new JX.TypeaheadPreloadedSource(props.src);
    }

    var typeahead = new JX.Typeahead(root);
    typeahead.setDatasource(datasource);

    var tokenizer = new JX.Tokenizer(root);
    tokenizer.setTypeahead(typeahead);

    JX.Stratcom.addData(root, {'tokenizer' : tokenizer});

    tokenizer.start();

    return tokenizer;
  }

  var dynamic = {};
  for (var k in config.dynamic) {
    props = config.dynamic[k];
    dynamic[k] = {
      row : JX.$(props.row),
      tokenizer : buildTokenizer(k, props),
      actions : props.actions
    };
  }

  JX.DOM.listen(
    JX.$(config.select),
    'change',
    null,
    function(e) {
      var v = JX.$(config.select).value;
      for (var k in dynamic) {
        if (dynamic[k].actions[v]) {
          JX.DOM.show(dynamic[k].row);
          dynamic[k].tokenizer.refresh();
        } else {
          JX.DOM.hide(dynamic[k].row);
        }
      }
    });
});

