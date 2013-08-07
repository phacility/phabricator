/**
 * @provides javelin-behavior-remarkup-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('remarkup-preview', function(config) {

  var preview = JX.$(config.previewID);
  var control = JX.$(config.controlID);

  var callback = function(r) {
    JX.DOM.setContent(preview, JX.$H(r));
  };

  var getdata = function() {
    return {
      text : control.value
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(control, 'keydown', null, trigger);
  request.start();

  trigger();
});
