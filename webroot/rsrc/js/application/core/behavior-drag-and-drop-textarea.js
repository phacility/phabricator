/**
 * @provides javelin-behavior-aphront-drag-and-drop-textarea
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-drag-and-drop-file-upload
 *           phabricator-paste-file-upload
 *           phabricator-textareautils
 */

JX.behavior('aphront-drag-and-drop-textarea', function(config) {

  var target = JX.$(config.target);

  function onupload(f) {
    var text = JX.TextAreaUtils.getSelectionText(target);
    var ref = '{F' + f.getID() + '}';

    // If the user has dragged and dropped multiple files, we'll get an event
    // each time an upload completes. Rather than overwriting the first
    // reference, append the new reference if the selected text looks like an
    // existing file reference.
    if (text.match(/^\{F/)) {
      ref = text + "\n\n" + ref;
    }

    JX.TextAreaUtils.setSelectionText(target, ref);
  }

  if (JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    var drop = new JX.PhabricatorDragAndDropFileUpload(target)
      .setURI(config.uri);
    drop.listen('didBeginDrag', function(e) {
      JX.DOM.alterClass(target, config.activatedClass, true);
    });
    drop.listen('didEndDrag', function(e) {
      JX.DOM.alterClass(target, config.activatedClass, false);
    });
    drop.listen('didUpload', onupload);
    drop.start();
  }

  if (JX.PhabricatorPasteFileUpload.isSupported()) {
    var paste = new JX.PhabricatorPasteFileUpload(target)
      .setURI(config.uri);
    paste.listen('didUpload', onupload);
    paste.start();
  }

});

