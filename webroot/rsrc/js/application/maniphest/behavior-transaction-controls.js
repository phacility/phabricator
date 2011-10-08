/**
 * @provides javelin-behavior-maniphest-transaction-controls
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-tokenizer
 *           javelin-typeahead
 *           javelin-typeahead-preloaded-source
 */

JX.behavior('maniphest-transaction-controls', function(config) {

  var tokenizers = {};

  for (var k in config.tokenizers) {
    var tconfig = config.tokenizers[k];
    var root = JX.$(tconfig.id);

    var datasource;
    if (tconfig.ondemand) {
      datasource = new JX.TypeaheadOnDemandSource(tconfig.src);
    } else {
      datasource = new JX.TypeaheadPreloadedSource(tconfig.src);
    }

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

});

