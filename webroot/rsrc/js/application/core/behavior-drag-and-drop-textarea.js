/**
 * @provides javelin-behavior-aphront-drag-and-drop-textarea
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-drag-and-drop-file-upload
 *           phabricator-paste-file-upload
 */

JX.behavior('aphront-drag-and-drop-textarea', function(config) {

  if (JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    var target = JX.$(config.target);
    var drop = new JX.PhabricatorDragAndDropFileUpload(target)
      .setActivatedClass(config.activatedClass)
      .setURI(config.uri);

    drop.listen('didUpload', function(f) {
      // TODO: Implement some fancy cursor position stuff in Javelin so we
      // can drop it in wherever the cursor is.
      target.value = target.value + "\n{F" + f.id + "}";
    });

    drop.start();
  }

  if (JX.PhabricatorPasteFileUpload.isSupported()) {
    var target = JX.$(config.target);
    var paste = new JX.PhabricatorPasteFileUpload(target).setURI(config.uri);

    paste.listen('didUpload', function(f) {
      // TODO: Implement some fancy cursor position stuff in Javelin so we
      // can drop it in wherever the cursor is.
      target.value = target.value + '{F' + f.id + '}';
    });

    paste.start();
  }

});

