/**
 * @provides javelin-behavior-ponder-feedback-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('ponder-feedback-preview', function(config) {

  var content = JX.$(config.content);
  var question_id = config.question_id;

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    var data = {
      content : content.value,
      question_id : question_id
    };
    return data;
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(content, 'keydown', null, trigger);

  request.start();
});
