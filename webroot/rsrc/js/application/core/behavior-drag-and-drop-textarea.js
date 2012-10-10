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
    JX.TextAreaUtils.setSelectionText(target, '{F' + f.getID() + '}');
  }

  if (JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    var drop = new JX.PhabricatorDragAndDropFileUpload(target)
      .setActivatedClass(config.activatedClass)
      .setURI(config.uri);
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

