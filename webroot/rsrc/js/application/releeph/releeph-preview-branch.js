/**
 * @provides javelin-behavior-releeph-preview-branch
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-uri
 *           javelin-request
 */

JX.behavior('releeph-preview-branch', function(config) {

  var uri = JX.$U(config.uri);
  for (var param_name in config.params.static) {
    var value = config.params.static[param_name];
    uri.setQueryParam(param_name, value);
  }

  var output = JX.$(config.outputID);

  var dynamics = config.params.dynamic;

  function renderPreview() {
    for (var param_name in dynamics) {
      var node_id = dynamics[param_name];
      var input = JX.$(node_id);
      uri.setQueryParam(param_name, input.value);
    }
    var request = new JX.Request(uri, function(response) {
      JX.DOM.setContent(output, JX.$H(response.markup));
    });
    request.send();
  }

  renderPreview();

  for (var ii in dynamics) {
    var node_id = dynamics[ii];
    var input = JX.$(node_id);
    JX.DOM.listen(
      input,
      ['keyup', 'click', 'change'],
      null,
      function() {
        renderPreview();
      }
    );
  }

});
