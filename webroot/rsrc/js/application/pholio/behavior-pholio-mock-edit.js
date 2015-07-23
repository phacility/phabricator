/**
 * @provides javelin-behavior-pholio-mock-edit
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-workflow
 *           javelin-quicksand
 *           phabricator-phtize
 *           phabricator-drag-and-drop-file-upload
 *           phabricator-draggable-list
 */
JX.behavior('pholio-mock-edit', function(config, statics) {
  var pht = JX.phtize(config.pht);
  var uploading = [];


/* -(  Deleting Images  )---------------------------------------------------- */


  // When the user clicks the "X" on an image, we replace it with a "click to
  // undo" element. If they click to undo, we put the original node back in the
  // DOM.
  var pholio_drop_remove = function(e) {
    e.kill();

    var node = e.getNode('pholio-drop-image');
    var undo = render_undo();

    JX.DOM.listen(undo, 'click', 'pholio-drop-undo', function(e) {
      e.kill();
      JX.DOM.replace(undo, node);
      synchronize_order();
    });

    JX.DOM.replace(node, undo);
    synchronize_order();
  };


/* -(  Reordering Images  )-------------------------------------------------- */


  // Reflect the display order in a hidden input.
  var synchronize_order = function() {
    var items = statics.draglist.findItems();
    var order = [];
    for (var ii = 0; ii < items.length; ii++) {
      order.push(JX.Stratcom.getData(items[ii]).filePHID);
    }
    statics.nodes.order.value = order.join(',');
  };


  var build_draglist = function(node) {
    var draglist = new JX.DraggableList('pholio-drop-image', node)
      .setGhostNode(JX.$N('div', {className: 'drag-ghost'}))
      .setFindItemsHandler(function() {
        return JX.DOM.scry(node, 'div', 'pholio-drop-image');
      });

    // Only let the user drag images by the handle, not the whole entry.
    draglist.listen('shouldBeginDrag', function(e) {
      if (!e.getNode('pholio-drag-handle')) {
        JX.Stratcom.context().prevent();
      }
    });
    draglist.listen('didDrop', synchronize_order);
    return draglist;
  };


/* -(  Build  )-------------------------------------------------------------- */


  var build_drop_upload = function(node) {
    var drop = new JX.PhabricatorDragAndDropFileUpload(node)
      .setURI(config.uploadURI);

    drop.listen('didBeginDrag', function() {
      JX.DOM.alterClass(node, 'pholio-drop-active', true);
    });

    drop.listen('didEndDrag', function() {
      JX.DOM.alterClass(node, 'pholio-drop-active', false);
    });

    return drop;
  };

  var build_add_control = function(add_node) {
    var drop = build_drop_upload(add_node);

    drop.listen('willUpload', function(file) {
      var node = render_uploading();
      uploading.push({node: node, file: file});
      statics.nodes.list.appendChild(node);
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
          synchronize_order();
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

    drop.listen('willUpload', function() {
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
          synchronize_order();
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


  function update_statics(data, page_id, no_build) {
    statics.nodes = {
      list: JX.$(data.listID),
      drop: JX.$(data.dropID),
      order: JX.$(data.orderID)
    };

    if (!statics.mockEditCache[page_id]) {
      statics.mockEditCache[page_id] = {};
    }
    statics.mockEditCache[page_id].config = config;
    statics.mockEditCache[page_id].nodes = statics.nodes;

    if (no_build !== true) {
      build_add_control(statics.nodes.drop);
      build_list_controls(statics.nodes.list);
      statics.draglist = build_draglist(statics.nodes.list);
      statics.mockEditCache[page_id].draglist = statics.draglist;
    } else {
      statics.draglist = statics.mockEditCache[page_id].draglist;
    }
    synchronize_order();
  }

  function install() {
    statics.mockEditCache = {};
    JX.Stratcom.listen('click', 'pholio-drop-remove', pholio_drop_remove);
    JX.Stratcom.listen(
      'quicksand-redraw',
      null,
      function (e) {
        e.kill();

        var data = e.getData();
        if (!data.newResponse.mockEditConfig) {
          return;
        }
        if (data.fromServer) {
          // we ran update_statics(config) below already
        } else {
          var page_id = data.newResponseID;
          var new_config = statics.mockEditCache[page_id].config;
          update_statics(new_config, page_id, true);
        }
      });
    return true;
  }

  statics.installed = statics.installed || install();
  update_statics(config, JX.Quicksand.getCurrentPageID(), false);
});
