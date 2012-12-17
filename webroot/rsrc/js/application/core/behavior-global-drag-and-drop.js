/**
 * @provides javelin-behavior-global-drag-and-drop
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-uri
 *           javelin-mask
 *           phabricator-drag-and-drop-file-upload
 */

JX.behavior('global-drag-and-drop', function(config) {
  if (!JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    return;
  }

  var pending = 0;
  var files = [];
  var errors = false;

  if (config.ifSupported) {
    JX.$(config.ifSupported).style.display = '';
  }

  var drop = new JX.PhabricatorDragAndDropFileUpload(document.documentElement)
    .setURI(config.uploadURI);

  drop.listen('didBeginDrag', function(f) {
    JX.Mask.show();
    JX.DOM.show(JX.$(config.instructions));
  });

  drop.listen('didEndDrag', function(f) {
    JX.Mask.hide();
    JX.DOM.hide(JX.$(config.instructions));
  });

  drop.listen('willUpload', function(f) {
    pending++;
  });

  drop.listen('didUpload', function(f) {
    files.push(f);

    pending--;
    if (pending == 0 && !errors) {
      // If whatever the user dropped in has finished uploading, send them to
      // their uploads.
      var uri;
      uri = JX.$U(config.browseURI);
      var ids = [];
      for (var ii = 0; ii < files.length; ii++) {
        ids.push(files[ii].getID());
      }
      uri.setQueryParam('h', ids.join(','));

      files = [];

      uri.go();
    }
  });

  drop.listen('didError', function(f) {
    pending--;
    errors = true;
  });

  drop.start();
});

