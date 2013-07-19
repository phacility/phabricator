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


/* -(  Build  )-------------------------------------------------------------- */


  var build_drop_upload = function(node) {
    var drop = new JX.PhabricatorDragAndDropFileUpload(node)
      .setURI(config.uploadURI);

    drop.listen('didBeginDrag', function(e) {
      JX.DOM.alterClass(node, 'pholio-drop-active', true);
    });

    drop.listen('didEndDrag', function(e) {
      JX.DOM.alterClass(node, 'pholio-drop-active', false);
    });

    return drop;
  };

  var build_add_control = function(add_node) {
    var drop = build_drop_upload(add_node);

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
          var new_node = JX.$H(response.markup).getFragment().firstChild;
          build_update_control(new_node);

          JX.DOM.replace(node, new_node);
        })
        .start();
    });

    drop.start();
  };

  var build_list_controls = function(list_node) {
    var nodes = JX.DOM.scry(list_node, 'div', 'pholio-drop-image');
    for (var ii = 0; ii < nodes.length; ii++) {
      build_update_control(nodes[ii]);
    }
  };

  var build_update_control = function(node) {
    var drop = build_drop_upload(node);

    drop.listen('willUpload', function(file) {
      JX.DOM.alterClass(node, 'pholio-replacing', true);
    });

    drop.listen('didUpload', function(file) {
      var node_data = JX.Stratcom.getData(node);

      var data = {
        filePHID: file.getPHID(),
        replacesPHID: node_data.replacesPHID || node_data.filePHID || null,
        title: JX.DOM.find(node, 'input', 'image-title').value,
        description: JX.DOM.find(node, 'textarea', 'image-description').value
      };

      new JX.Workflow(config.renderURI, data)
        .setHandler(function(response) {
          var new_node = JX.$H(response.markup).getFragment().firstChild;
          build_update_control(new_node);

          JX.DOM.replace(node, new_node);
          JX.DOM.alterClass(node, 'pholio-replacing', false);
        })
        .start();
    });

    drop.start();
  };


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


/* -(  Init  )--------------------------------------------------------------- */

  build_add_control(nodes.drop);
  build_list_controls(nodes.list);

});
