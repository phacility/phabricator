/**
 * @provides javelin-behavior-maniphest-transaction-controls
 * @requires javelin-lib-dev
 */

JX.behavior('maniphest-transaction-controls', function(config) {

  var tokenizers = {};

  for (var k in config.tokenizers) {
    var tconfig = config.tokenizers[k];
    var root = JX.$(tconfig.id);
    var datasource = new JX.TypeaheadPreloadedSource(tconfig.src);

    var typeahead = new JX.Typeahead(root);
    typeahead.setDatasource(datasource);

    var tokenizer = new JX.Tokenizer(root);
    tokenizer.setTypeahead(typeahead);

    if (tconfig.limit) {
      tokenizer.setLimit(tconfig.limit);
    }

    tokenizer.start();


    if (tconfig.value) {
      for (var jj in tconfig.value) {
        tokenizer.addToken(jj, tconfig.value[jj]);
      }
    }

    tokenizers[k] = tokenizer;
  }

  JX.DOM.listen(
    JX.$(config.select),
    'change',
    null,
    function(e) {
      for (var k in config.controlMap) {
        if (k == JX.$(config.select).value) {
          JX.DOM.show(JX.$(config.controlMap[k]));
          if (tokenizers[k]) {
            tokenizers[k].refresh();
          }
        } else {
          JX.DOM.hide(JX.$(config.controlMap[k]));
        }
      }
    });

/*

  var root = JX.$(config.tokenizer);
  var datasource = new JX.TypeaheadPreloadedSource(config.src);

  var typeahead = new JX.Typeahead(root);
  typeahead.setDatasource(datasource);

  var tokenizer = new JX.Tokenizer(root);
  tokenizer.setTypeahead(typeahead);
  tokenizer.start();

  JX.DOM.listen(
    JX.$(config.select),
    'change',
    null,
    function(e) {
      if (JX.$(config.select).value == 'add_reviewers') {
        JX.DOM.show(JX.$(config.row));
        tokenizer.refresh();
      } else {
        JX.DOM.hide(JX.$(config.row));
      }
    });

*/
});

