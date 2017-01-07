/**
 * @provides javelin-behavior-diff-preview-link
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('diff-preview-link', function(config, statics) {
  if (statics.initialized) {
    return;
  }
  statics.initialized = true;

  var pht = JX.phtize(config.pht);

  // After inline comment previews are rendered, hook up the links to the
  // comments that are visible on the current page.
  function link_inline_preview(e) {
    var root = e.getData().rootNode;
    var links = JX.DOM.scry(root, 'a',  'differential-inline-preview-jump');

    for (var ii = 0; ii < links.length; ii++) {
      var data = JX.Stratcom.getData(links[ii]);
      try {
        JX.$(data.anchor);
        links[ii].href = '#' + data.anchor;
        JX.DOM.setContent(links[ii], pht('view'));
      } catch (ignored) {
        // This inline comment isn't visible, e.g. on some other diff.
      }
    }

  }

  JX.Stratcom.listen('EditEngine.didCommentPreview', null, link_inline_preview);
});
