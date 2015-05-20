/**
 * @provides javelin-behavior-time-typeahead
 * @requires javelin-behavior
 *           javelin-util
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-typeahead-static-source
 */

JX.behavior('time-typeahead', function(config) {
  var root = JX.$(config.timeID);
  var datasource = new JX.TypeaheadStaticSource(config.timeValues);
  datasource.setTransformer(function(v) {
    var attributes = {'className' : 'phui-time-typeahead-value'};
    var display = JX.$N('div', attributes, v[1]);
    var object = {
      'id' : v[0],
      'name' : v[1],
      'display' : display,
      'uri' : null
    };
    return object;
  });
  datasource.setSortHandler(function(value, list) {
    list.sort(function(u,v){
      return (u.id > v.id) ? 1 : -1;
    });
  });
  datasource.setMaximumResultCount(24);
  var typeahead = new JX.Typeahead(
    root,
    JX.DOM.find(root, 'input', null));
  typeahead.setDatasource(datasource);
  typeahead.start();
});
