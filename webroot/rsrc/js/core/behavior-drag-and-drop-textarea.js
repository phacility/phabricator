/**
 * @provides javelin-behavior-aphront-drag-and-drop-textarea
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-drag-and-drop-file-upload
 *           phabricator-textareautils
 */

JX.behavior('aphront-drag-and-drop-textarea', function(config) {

  var target = JX.$(config.target);

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

    drop.listen('didUpload', function(file) {
      JX.TextAreaUtils.insertFileReference(target, file);
    });

    drop.start();
  }

});
