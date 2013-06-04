/**
 * @provides javelin-behavior-phriction-document-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('phriction-document-preview', function(config) {

  var preview = JX.$(config.preview);
  var textarea = JX.$(config.textarea);

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    return {
      document : textarea.value
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(textarea, 'keydown', null, trigger);
  request.start();
});
