/**
 * @provides javelin-behavior-aphront-drag-and-drop
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-file-upload
 *           phabricator-drag-and-drop-file-upload
 */

JX.behavior('aphront-drag-and-drop', function(config) {

  // The control renders hidden by default; if we don't have support for
  // drag-and-drop just leave it hidden.
  if (!JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    return;
  }

  // Show the control, since we have browser support.
  JX.$(config.control).style.display = '';

  var files = {};
  if (config.value) {
    for (var k in config.value) {
      var file = config.value[k];
      files[k] = new JX.PhabricatorFileUpload()
        .setPHID(file.phid)
        .setMarkup(file.html);
    }
  }
  var pending = 0;

  var list = JX.$(config.list);

  var drop = new JX.PhabricatorDragAndDropFileUpload(JX.$(config.list))
    .setURI(config.uri);

  drop.listen('didBeginDrag', function(e) {
    JX.DOM.alterClass(list, config.activatedClass, true);
  });

  drop.listen('didEndDrag', function(e) {
    JX.DOM.alterClass(list, config.activatedClass, false);
  });

  drop.listen('willUpload', function(f) {
    pending++;
    redraw();
  });

  drop.listen('didUpload', function(f) {
    files[f.getPHID()] = f;

    // This redraws "Upload complete!"
    pending--;
    redraw(true);

    // This redraws the instructions.
    setTimeout(redraw, 1000);
  });

  drop.start();
  redraw();

  JX.DOM.listen(
    list,
    'click',
    'aphront-attached-file-view-remove',
    function(e) {
      e.kill();
      delete files[e.getTarget().getAttribute('ref')];
      redraw();
    });

  function redraw(completed) {
    var items = [];
    for (var k in files) {
      var file = files[k];
      items.push(JX.$N('div', {}, JX.$H(file.getMarkup())));
      items.push(JX.$N(
        'input',
        {
          type: "hidden",
          name: config.name + "[" + file.getPHID() + "]",
          value: file.getPHID()
        }));
    }

    var status;
    var extra = '';
    if (!pending) {
      if (completed) {
        status = JX.$H('<strong>Upload complete!</strong>');
      } else {
        arrow = String.fromCharCode(0x21EA);
        status = JX.$H(
          arrow + ' <strong>Drag and Drop</strong> files here to upload them.');
        extra = ' drag-and-drop-file-target';
      }
    } else {
      status = JX.$H(
        'Uploading <strong>' + parseInt(pending, 10) + '</strong> files...');
    }
    status = JX.$N(
      'div',
      {className: 'drag-and-drop-instructions' + extra},
      status);

    items.push(status);
    JX.DOM.setContent(list, items);
  }

});

