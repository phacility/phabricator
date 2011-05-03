/**
 * @provides javelin-behavior-differential-feedback-preview
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-request
 *           javelin-util
 */

JX.behavior('differential-feedback-preview', function(config) {

  var action = JX.$(config.action);
  var content = JX.$(config.content);
  var preview = JX.$(config.preview);

  var aval = null;//action.value;
  var cval = null;//content.value;
  var defer = null;
  var min = null;
  var request = null;

  function check() {
    if (request || (min && (new Date().getTime() < min))) {
      // Waiting on an async or just got one back, rate-limit.
      return;
    }

    defer && defer.stop();

    if (action.value !== aval || content.value !== cval) {
      aval = action.value;
      cval = content.value;

      request = new JX.Request(config.uri, function(r) {
        preview && JX.DOM.setContent(preview, JX.$H(r));
        min = new Date().getTime() + 500;
        defer && defer.stop();
        defer = JX.defer(check, 500);
      });
      request.listen('finally', function() { request = null; });
      request.setData({action : aval, content : cval});
      // If we don't get a response back soon, retry on the next action.
      request.setTimeout(2000);
      request.send();
    } else {
      defer = JX.defer(check, 2000);
    }
  }

  JX.DOM.listen(content, 'keydown', null, check);
  JX.DOM.listen(action,  'change',  null, check);

  check();


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
