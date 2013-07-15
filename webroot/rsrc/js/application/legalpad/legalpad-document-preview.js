/**
 * @provides javelin-behavior-legalpad-document-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('legalpad-document-preview', function(config) {

  var preview = JX.$(config.preview);
  var title = JX.$(config.title);
  var text = JX.$(config.text);

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    return {
      title : title.value,
      text : text.value
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(title, 'keydown', null, trigger);
  JX.DOM.listen(text,  'keydown', null, trigger);
  request.start();

});
