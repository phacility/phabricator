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
  var previewTokenizers = {};
  for (var field in config.previewTokenizers) {
    var tokenizer = JX.$(config.previewTokenizers[field]);
    previewTokenizers[field] = JX.Stratcom.getData(tokenizer).tokenizer;
  }

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    var data = {
      content : content.value,
      action : action.value
    };
    for (var field in previewTokenizers) {
      data[field] = JX.keys(previewTokenizers[field].getTokens()).join(',');
    }
    return data;
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(content, 'keydown', null, trigger);
  JX.DOM.listen(action,  'change',  null, trigger);
  for (var field in previewTokenizers) {
    previewTokenizers[field].listen('change', trigger);
  }

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

  refreshInlinePreview();
});
