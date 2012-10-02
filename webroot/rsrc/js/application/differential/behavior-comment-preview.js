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
    var preview = JX.$(config.preview);
    JX.DOM.setContent(preview, JX.$H(r));
    JX.Stratcom.invoke('differential-preview-update', null, {
      container: preview
    });
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
  request.listen('error', function (error) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H('ERROR: ' + error.info));
  });

  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(content, 'keydown', null, trigger);
  JX.DOM.listen(action,  'change',  null, trigger);
  for (var field in previewTokenizers) {
    previewTokenizers[field].listen('change', trigger);
  }

  request.start();


  function refreshInlinePreview() {
    new JX.Request(config.inlineuri, function(r) {
        var inline = JX.$(config.inline);

        JX.DOM.setContent(inline, JX.$H(r));
        JX.Stratcom.invoke('differential-preview-update', null, {
          container: inline
        });

        // Go through the previews and activate any "View" links where the
        // actual comment appears in the document.

        var links = JX.DOM.scry(
          inline,
          'a',
          'differential-inline-preview-jump');
        for (var ii = 0; ii < links.length; ii++) {
          var data = JX.Stratcom.getData(links[ii]);
          try {
            JX.$(data.anchor);
            links[ii].href = '#' + data.anchor;
            JX.DOM.setContent(links[ii], 'View');
          } catch (ignored) {
            // This inline comment isn't visible, e.g. on some other diff.
          }
        }
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
