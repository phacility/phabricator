/**
 * @provides javelin-behavior-differential-feedback-preview
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-request
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('differential-feedback-preview', function(config) {

  var action = JX.$(config.action);
  var content = JX.$(config.content);

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    return {
      content : content.value,
      action : action.value
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(content, 'keydown', null, trigger);
  JX.DOM.listen(action,  'change',  null, trigger);

  request.start();



  function refreshInlinePreview() {
    new JX.Request(config.inlineuri, function(r) {
        JX.DOM.setContent(JX.$(config.inline), JX.$H(r));
      })
      .setTimeout(5000)
      .send();
  }

  JX.Stratcom.listen(
    'differential-inline-comment-update',
    null,
    refreshInlinePreview);

  setTimeout(refreshInlinePreview, 0);
});
