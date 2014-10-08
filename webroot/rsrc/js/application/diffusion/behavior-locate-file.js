/**
 * @provides javelin-behavior-diffusion-locate-file
 * @requires javelin-behavior
 *           javelin-diffusion-locate-file-source
 *           javelin-dom
 *           javelin-typeahead
 *           javelin-uri
 */

JX.behavior('diffusion-locate-file', function(config) {
  var control = JX.$(config.controlID);
  var input = JX.$(config.inputID);

  var datasource = new JX.DiffusionLocateFileSource(config.uri);

  var typeahead = new JX.Typeahead(control, input);
  typeahead.setDatasource(datasource);

  typeahead.listen('choose', function(r) {
    JX.$U(config.browseBaseURI + r.ref).go();
  });

  var started = false;
  JX.DOM.listen(input, 'click', null, function() {
    if (!started) {
      started = true;
      typeahead.start();
    }
  });

});
