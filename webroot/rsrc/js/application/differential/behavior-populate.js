/**
 * @provides javelin-behavior-differential-populate
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           phabricator-tooltip
 *           changeset-view-manager
 */

JX.behavior('differential-populate', function(config) {

  for (var ii = 0; ii < config.changesetViewIDs.length; ii++) {
    var id = config.changesetViewIDs[ii];
    var view = JX.ChangesetViewManager.getForNode(JX.$(id));
    if (view.shouldAutoload()) {
      view.setStabilize(true).load();
    }
  }

  JX.Stratcom.listen(
    'click',
    'differential-load',
    function(e) {
      var meta = e.getNodeData('differential-load');
      var changeset = JX.$(meta.id);
      var view = JX.ChangesetViewManager.getForNode(changeset);

      view.load();
      var routable = view.getRoutable();
      if (routable) {
        routable.setPriority(2000);
      }

      if (meta.kill) {
        e.kill();
      }
    });

  JX.Stratcom.listen(
    'click',
    'show-more',
    function(e) {
      e.kill();

      var changeset = e.getNode('differential-changeset');
      var view = JX.ChangesetViewManager.getForNode(changeset);
      var data = e.getNodeData('show-more');
      var target = e.getNode('context-target');

      view.loadContext(data.range, target);
    });

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
