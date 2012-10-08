/**
 * @provides javelin-behavior-files-drag-and-drop
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-uri
 *           phabricator-drag-and-drop-file-upload
 */

JX.behavior('files-drag-and-drop', function(config) {

  // The control renders hidden by default; if we don't have support for
  // drag-and-drop just leave it hidden.
  if (!JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    return;
  }

  var pending = 0;
  var files = [];
  var errors = false;

  var control = JX.$(config.control);
  // Show the control, since we have browser support.
  control.style.display = '';

  var drop = new JX.PhabricatorDragAndDropFileUpload(JX.$(config.target))
    .setActivatedClass(config.activatedClass)
    .setURI(config.uri);

  drop.listen('willUpload', function(f) {
    pending++;
    redraw();
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
      uri.setQueryParam('h', ids.join('-'));

      // Reset so if you hit 'back' into the bfcache the page is still in a
      // sensible state.
      redraw();
      files = [];

      uri.go();
    } else {
      redraw();
    }
  });

  drop.listen('didError', function(f) {
    pending--;
    errors = true;
    redraw();
  });

  drop.start();
  redraw();

  function redraw() {

    var status;
    if (pending) {
      status = 'Uploading ' + pending + ' files...';
    } else {
      var arrow = String.fromCharCode(0x21EA);
      status = JX.$H(
        arrow + ' <strong>Drag and Drop</strong> files here to upload them.');
    }

    JX.DOM.setContent(control, status);
  }

});

