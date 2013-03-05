/**
 * @provides javelin-behavior-load-blame
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-request
 */

JX.behavior('load-blame', function(config) {

  new JX.Request(location.href, function (response) {
    JX.DOM.setContent(JX.$(config.id), JX.$H(response));
  }).send();

});
