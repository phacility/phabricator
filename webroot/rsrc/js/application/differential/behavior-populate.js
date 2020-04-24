/**
 * @provides javelin-behavior-differential-populate
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phabricator-tooltip
 *           phabricator-diff-changeset-list
 *           phabricator-diff-changeset
 *           phuix-formation-view
 * @javelin
 */

JX.behavior('differential-populate', function(config, statics) {

  // When we perform a Quicksand navigation, deactivate the changeset lists on
  // the current page and activate the changeset lists on the new page.
  var onredraw = function(page_id) {
    // If the current page is already active, we don't need to do anything.
    if (statics.pageID === page_id) {
      return;
    }

    var ii;

    // Put the old lists to sleep.
    var old_lists = get_lists(statics.pageID);
    for (ii = 0; ii < old_lists.length; ii++) {
      old_lists[ii].sleep();
    }
    statics.pageID = null;

    // Awaken the new lists, if they exist.
    if (statics.pages.hasOwnProperty(page_id)) {
      var new_lists = get_lists(page_id);
      for (ii = 0; ii < new_lists.length; ii++) {
        new_lists[ii].wake();
      }

      statics.pageID = page_id;
    }
  };

  // Get changeset lists on the current page.
  var get_lists = function(page_id) {
    if (page_id === null) {
      return [];
    }

    return statics.pages[page_id] || [];
  };

  if (!statics.installed) {
    statics.installed = true;
    statics.pages = {};
    statics.pageID = null;

    JX.Stratcom.listen('quicksand-redraw', null, function(e) {
      onredraw(e.getData().newResponseID);
    });
  }

  var changeset_list = new JX.DiffChangesetList()
    .setTranslations(JX.phtize(config.pht))
    .setInlineURI(config.inlineURI)
    .setInlineListURI(config.inlineListURI)
    .setIsStandalone(config.isStandalone);

  if (config.formationViewID) {
    var formation_node = JX.$(config.formationViewID);
    var formation_view = new JX.PHUIXFormationView(formation_node);
    changeset_list.setFormationView(formation_view);
    formation_view.start();
  }

  for (var ii = 0; ii < config.changesetViewIDs.length; ii++) {
    var id = config.changesetViewIDs[ii];
    var node = JX.$(id);
    var changeset = changeset_list.newChangesetForNode(node);
    if (changeset.shouldAutoload()) {
      changeset.setStabilize(true).load();
    }
  }

  // Install and activate the current page.
  var page_id = JX.Quicksand.getCurrentPageID();
  statics.pages[page_id] = [changeset_list];
  onredraw(page_id);

  var highlighted = null;
  var highlight_class = null;

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    ['differential-changeset', 'tag:td'],
    function(e) {
      var t = e.getTarget();

      // NOTE: Using className is not best practice, but the diff UI is perf
      // sensitive.
      if (!t.className.match(/cov|copy/)) {
        return;
      }

      if (e.getType() == 'mouseout') {
        JX.Tooltip.hide();
        if (highlighted) {
          JX.DOM.alterClass(highlighted, highlight_class, false);
          highlighted = null;
        }
      } else {
        highlight_class = null;
        var msg;
        var align = 'W';
        var sibling = 'previousSibling';
        var width = 120;
        if (t.className.match(/cov-C/)) {
          msg = 'Covered';
          highlight_class = 'source-cov-C';
        } else if (t.className.match(/cov-U/)) {
          msg = 'Not Covered';
          highlight_class = 'source-cov-U';
        } else if (t.className.match(/cov-N/)) {
          msg = 'Not Executable';
          highlight_class = 'source-cov-N';
        } else {
          var match = /new-copy|new-move/.exec(t.className);
          if (match) {
            sibling = 'nextSibling';
            width = 500;
            msg = JX.Stratcom.getData(t).msg;
            highlight_class = match[0];
          }
        }

        if (msg) {
          JX.Tooltip.show(t, width, align, msg);
        }

        if (highlight_class) {
          highlighted = t[sibling];
          JX.DOM.alterClass(highlighted, highlight_class, true);
        }
      }

    });


});
