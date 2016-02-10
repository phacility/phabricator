/**
 * @provides javelin-behavior-aphront-drag-and-drop-textarea
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-drag-and-drop-file-upload
 *           phabricator-textareautils
 */

JX.behavior('aphront-drag-and-drop-textarea', function(config) {

  var target = JX.$(config.target);

  function onupload(f) {
    var ref = '{F' + f.getID() + '}';

    // If we're inserting immediately after a "}" (usually, another file
    // reference), put some newlines before our token so that multiple file
    // uploads get laid out more nicely.
    var range = JX.TextAreaUtils.getSelectionRange(target);
    var before = target.value.substring(0, range.start);
    if (before.match(/\}$/)) {
      ref = '\n\n' + ref;
    }

    JX.TextAreaUtils.setSelectionText(target, ref, false);
  }

  if (JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    var drop = new JX.PhabricatorDragAndDropFileUpload(target)
      .setURI(config.uri)
      .setChunkThreshold(config.chunkThreshold);
    drop.listen('didBeginDrag', function() {
      JX.DOM.alterClass(target, config.activatedClass, true);
    });
    drop.listen('didEndDrag', function() {
      JX.DOM.alterClass(target, config.activatedClass, false);
    });
    drop.listen('didUpload', onupload);
    drop.start();
  }

});
