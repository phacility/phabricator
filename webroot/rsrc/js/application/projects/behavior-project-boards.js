/**
 * @provides javelin-behavior-project-boards
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-vector
 *           javelin-stratcom
 *           javelin-workflow
 *           phabricator-draggable-list
 *           phabricator-drag-and-drop-file-upload
 *           javelin-workboard
 */

JX.behavior('project-boards', function(config, statics) {


  function onupdate(col) {
    var data = JX.Stratcom.getData(col);
    var cards = finditems(col);

    // Update the count of tasks in the column header.
    if (!data.countTagNode) {
      data.countTagNode = JX.$(data.countTagID);
      JX.DOM.show(data.countTagNode);
    }

    var sum = 0;
    for (var ii = 0; ii < cards.length; ii++) {
      // TODO: Allow this to be computed in some more clever way.
      sum += 1;
    }

    // TODO: This is a little bit hacky, but we don't have a PHUIX version of
    // this element yet.

    var over_limit = (data.pointLimit && (sum > data.pointLimit));

    var display_value = sum;
    if (data.pointLimit) {
      display_value = sum + ' / ' + data.pointLimit;
    }
    JX.DOM.setContent(JX.$(data.countTagContentID), display_value);


    var panel_map = {
      'project-panel-empty': !cards.length,
      'project-panel-over-limit': over_limit
    };
    var panel = JX.DOM.findAbove(col, 'div', 'workpanel');
    for (var p in panel_map) {
      JX.DOM.alterClass(panel, p, !!panel_map[p]);
    }

    var color_map = {
      'phui-tag-shade-disabled': (sum === 0),
      'phui-tag-shade-blue': (sum > 0 && !over_limit),
      'phui-tag-shade-red': (over_limit)
    };
    for (var c in color_map) {
      JX.DOM.alterClass(data.countTagNode, c, !!color_map[c]);
    }
  }


  function colsort(u, v) {
    var ud = JX.Stratcom.getData(u).sort || [];
    var vd = JX.Stratcom.getData(v).sort || [];

    for (var ii = 0; ii < ud.length; ii++) {

      if (parseInt(ud[ii]) < parseInt(vd[ii])) {
        return 1;
      }
      if (parseInt(ud[ii]) > parseInt(vd[ii])) {
        return -1;
      }
    }

    return 0;
  }

  function onedit(column, r) {
    var new_card = JX.$H(r.tasks).getNode();
    var new_data = JX.Stratcom.getData(new_card);
    var items = finditems(column);
    var edited = false;
    var remove_index = null;

    for (var ii = 0; ii < items.length; ii++) {
      var item = items[ii];

      var data = JX.Stratcom.getData(item);
      var phid = data.objectPHID;

      if (phid == new_data.objectPHID) {
        if (r.data.removeFromBoard) {
          remove_index = ii;
        }
        items[ii] = new_card;
        data = new_data;
        edited = true;
      }

      data.sort = r.data.sortMap[data.objectPHID] || data.sort;
    }

    // this is an add then...!
    if (!edited) {
      items[items.length + 1] = new_card;
      new_data.sort = r.data.sortMap[new_data.objectPHID] || new_data.sort;
    }

    if (remove_index !== null) {
      items.splice(remove_index, 1);
    }

    items.sort(colsort);

    JX.DOM.setContent(column, items);

    onupdate(column);
  };

  function update_statics(update_config) {
    statics.boardID = update_config.boardID;
    statics.projectPHID = update_config.projectPHID;
    statics.order = update_config.order;
    statics.moveURI = update_config.moveURI;
    statics.createURI = update_config.createURI;
  }

  function setup() {

    JX.Stratcom.listen(
      'click',
      ['edit-project-card'],
      function(e) {
        e.kill();
        var column = e.getNode('project-column');
        var request_data = {
          responseType: 'card',
          columnPHID: JX.Stratcom.getData(column).columnPHID,
          order: statics.order
        };
        new JX.Workflow(e.getNode('tag:a').href, request_data)
          .setHandler(JX.bind(null, onedit, column))
          .start();
      });

    JX.Stratcom.listen(
      'click',
      ['column-add-task'],
      function (e) {

        // We want the 'boards-dropdown-menu' behavior to see this event and
        // close the dropdown, but don't want to follow the link.
        e.prevent();

        var column_data = e.getNodeData('column-add-task');
        var column_phid = column_data.columnPHID;

        var request_data = {
          responseType: 'card',
          columnPHID: column_phid,
          projects: column_data.projectPHID,
          order: statics.order
        };

        var cols = getcolumns();
        var ii;
        var column;
        for (ii = 0; ii < cols.length; ii++) {
          if (JX.Stratcom.getData(cols[ii]).columnPHID == column_phid) {
            column = cols[ii];
            break;
          }
        }
        new JX.Workflow(statics.createURI, request_data)
          .setHandler(JX.bind(null, onedit, column))
          .start();
      });

    JX.Stratcom.listen('click', 'boards-dropdown-menu', function(e) {
      var data = e.getNodeData('boards-dropdown-menu');
      if (data.menu) {
        return;
      }

      e.kill();

      var list = JX.$H(data.items).getFragment().firstChild;

      var button = e.getNode('boards-dropdown-menu');
      data.menu = new JX.PHUIXDropdownMenu(button);
      data.menu.setContent(list);
      data.menu.open();

      JX.DOM.listen(list, 'click', 'tag:a', function(e) {
        if (!e.isNormalClick()) {
          return;
        }
        data.menu.close();
      });
    });

    JX.Stratcom.listen(
      'quicksand-redraw',
      null,
      function (e) {
        var data = e.getData();
        if (!data.newResponse.boardConfig) {
          return;
        }
        var new_config;
        if (data.fromServer) {
          new_config = data.newResponse.boardConfig;
          statics.boardConfigCache[data.newResponseID] = new_config;
        } else {
          new_config = statics.boardConfigCache[data.newResponseID];
          statics.boardID = new_config.boardID;
        }
        update_statics(new_config);
      });

    return true;
  }

  if (!statics.setup) {
    update_statics(config);
    var current_page_id = JX.Quicksand.getCurrentPageID();
    statics.boardConfigCache = {};
    statics.boardConfigCache[current_page_id] = config;
    statics.setup = setup();
  }

  if (!statics.workboard) {
    statics.workboard = new JX.Workboard(config)
      .setUploadURI(config.uploadURI)
      .setCoverURI(config.coverURI)
      .setMoveURI(config.moveURI)
      .setChunkThreshold(config.chunkThreshold)
      .start();
  }

  statics.workboard.addBoard(config.projectPHID, JX.$(config.boardID));

});
