/**
 * @provides javelin-behavior-differential-add-reviewers
 * @requires javelin-lib-dev
 */

JX.behavior('differential-add-reviewers', function(config) {

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
});

