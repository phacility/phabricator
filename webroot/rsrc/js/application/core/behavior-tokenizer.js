/**
 * @provides javelin-behavior-aphront-basic-tokenizer
 * @requires javelin-behavior
 *           javelin-typeahead
 *           javelin-tokenizer
 *           javelin-typeahead-preloaded-source
 *           javelin-typeahead-ondemand-source
 *           javelin-dom
 *           javelin-stratcom
 */

JX.behavior('aphront-basic-tokenizer', function(config) {
  var root = JX.$(config.id);

  var datasource;
  if (config.ondemand) {
    datasource = new JX.TypeaheadOnDemandSource(config.src);
  } else {
    datasource = new JX.TypeaheadPreloadedSource(config.src);
  }

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
