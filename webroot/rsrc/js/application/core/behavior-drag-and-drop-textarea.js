/**
 * @provides javelin-behavior-aphront-drag-and-drop-textarea
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-drag-and-drop-file-upload
 *           phabricator-paste-file-upload
 */

JX.behavior('aphront-drag-and-drop-textarea', function(config) {

  var target = JX.$(config.target);

  function onupload(f) {
    var v = target.value;
    var insert = '{F' + f.id + '}';

    // NOTE: This works well in Safari, Firefox and Chrome. We'll probably get
    // less-good behavior on IE, but I think IE doesn't support drag-and-drop
    // or paste uploads anyway.

    // Set the insert position to the end of the text, so we get reasonable
    // default behavior.
    var s = v.length;
    var e = v.length;

    // If possible, refine the insert position based on the current selection.
    if ('selectionStart' in target) {
      s = target.selectionStart;
      e = target.selectionEnd;
    }

    // Build the new text.
    v = v.substring(0, s) + insert + v.substring(e, v.length);
    // Replace the current value with the new text.
    target.value = v;

    // If possible, place the cursor after the inserted text.
    if ('setSelectionRange' in target) {
      target.focus();
      target.setSelectionRange(s + insert.length, s + insert.length);
    }
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

