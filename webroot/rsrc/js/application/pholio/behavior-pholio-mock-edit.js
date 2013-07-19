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
    var node = JX.$N(
      'div',
      {className: 'pholio-drop-uploading'},
      pht('uploading'));
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

  // TODO: It would be nice to replace this with an "image will be removed,
  // click to undo" kind of thing.
  JX.Stratcom.listen('click', 'pholio-drop-remove', function(e) {
    e.kill();
    JX.DOM.remove(e.getNode('pholio-drop-image'));
  });

});
