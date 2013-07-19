/**
 * @provides javelin-behavior-pholio-mock-edit
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-workflow
 *           phabricator-phtize
 *           phabricator-drag-and-drop-file-upload
 */
JX.behavior('pholio-mock-edit', function(config) {
  var pht = JX.phtize(config.pht);

  var nodes = {
    list: JX.$(config.listID),
    drop: JX.$(config.dropID)
  };

  var uploading = [];

  var drop = new JX.PhabricatorDragAndDropFileUpload(nodes.drop)
    .setURI(config.uploadURI);

  drop.listen('didBeginDrag', function(e) {
    JX.DOM.alterClass(nodes.drop, 'pholio-drop-active', true);
  });

  drop.listen('didEndDrag', function(e) {
    JX.DOM.alterClass(nodes.drop, 'pholio-drop-active', false);
  });

  drop.listen('willUpload', function(file) {
    var node = render_uploading();
    uploading.push({node: node, file: file});
    nodes.list.appendChild(node);
  });

  drop.listen('didUpload', function(file) {
    var node;
    for (var ii = 0; ii < uploading.length; ii++) {
      if (uploading[ii].file === file) {
        node = uploading[ii].node;
        uploading.splice(ii, 1);
        break;
      }
    }

    JX.DOM.setContent(node, pht('uploaded'));

    new JX.Workflow(config.renderURI, {filePHID: file.getPHID()})
      .setHandler(function(response) {
        JX.DOM.replace(node, JX.$H(response.markup));
      })
      .start();
  });

  drop.start();


/* -(  Deleting Images  )---------------------------------------------------- */


  // When the user clicks the "X" on an image, we replace it with a "click to
  // undo" element. If they click to undo, we put the original node back in the
  // DOM.
  JX.Stratcom.listen('click', 'pholio-drop-remove', function(e) {
    e.kill();

    var node = e.getNode('pholio-drop-image');
    var undo = render_undo();

    JX.DOM.listen(undo, 'click', 'pholio-drop-undo', function(e) {
      e.kill();
      JX.DOM.replace(undo, node);
    });

    JX.DOM.replace(node, undo);
  });


/* -(  Rendering  )---------------------------------------------------------- */


  var render_uploading = function() {
    return JX.$N(
      'div',
      {className: 'pholio-drop-uploading'},
      pht('uploading'));
  };

  var render_undo = function() {
    var link = JX.$N(
      'a',
      {href: '#', sigil: 'pholio-drop-undo'},
      pht('undo'));

    return JX.$N(
      'div',
      {className: 'pholio-drop-undo'},
      [pht('removed'), ' ', link]);
  };


});
