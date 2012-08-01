/**
 * @provides javelin-behavior-phabricator-search-typeahead
 * @requires javelin-behavior
 *           javelin-typeahead-ondemand-source
 *           javelin-typeahead
 *           javelin-dom
 *           javelin-uri
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
      name : object[0],
      display : render,
      uri : object[1],
      id : object[2]
    };
  }

  datasource.setTransformer(transform);

  var typeahead = new JX.Typeahead(JX.$(config.id), JX.$(config.input));
  typeahead.setDatasource(datasource);
  typeahead.setPlaceholder(config.placeholder);

  typeahead.listen('choose', function(r) {
    JX.$U(r.href).go();
    JX.Stratcom.context().kill();
  });

  typeahead.start();
});
