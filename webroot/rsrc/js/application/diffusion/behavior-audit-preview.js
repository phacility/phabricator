/**
 * @provides javelin-behavior-audit-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('audit-preview', function(config) {

  var content = JX.$(config.content);
  var action = JX.$(config.action);

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    return {
      action: action.value,
      content: content.value
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(content, 'keydown', null, trigger);
  JX.DOM.listen(action, 'change', null, trigger);

  request.start();
});
