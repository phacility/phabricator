/**
 * @provides javelin-behavior-differential-populate
 * @requires javelin-behavior
 *           javelin-workflow
 *           javelin-util
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-behavior-device
 *           javelin-vector
 *           phabricator-tooltip
 */

JX.behavior('differential-populate', function(config) {

  function onresponse(target_id, response) {
    // As we populate the diff, we try to hold the document scroll position
    // steady, so that, e.g., users who want to leave a comment on a diff with a
    // large number of changes don't constantly have the text area scrolled off
    // the bottom of the screen until the entire diff loads.
    //
    // There are two three major cases here:
    //
    //  - If we're near the top of the document, never scroll.
    //  - If we're near the bottom of the document, always scroll.
    //  - Otherwise, scroll if the changes were above the midline of the
    //    viewport.
    var target = JX.$(target_id);

    var old_pos = JX.Vector.getScroll();
    var old_view = JX.Vector.getViewport();
    var old_dim = JX.Vector.getDocument();

    // Number of pixels away from the top or bottom of the document which
    // count as "nearby".
    var sticky = 480;

    var near_top = (old_pos.y <= sticky);
    var near_bot = ((old_pos.y + old_view.y) >= (old_dim.y - sticky));

    var target_pos = JX.Vector.getPos(target);
    var target_dim = JX.Vector.getDim(target);
    var target_mid = (target_pos.y + (target_dim.y / 2));

    var view_mid = (old_pos.y + (old_view.y / 2));
    var above_mid = (target_mid < view_mid);

    JX.DOM.replace(target, JX.$H(response.changeset));

    if (!near_top) {
      if (near_bot || above_mid) {
        // Figure out how much taller the document got.
        var delta = (JX.Vector.getDocument().y - old_dim.y);

        window.scrollTo(old_pos.x, old_pos.y + delta);
      }
    }

    if (response.coverage) {
      for (var k in response.coverage) {
        try {
          JX.DOM.replace(JX.$(k), JX.$H(response.coverage[k]));
        } catch (ignored) {
          // Not terribly important.
        }
      }
    }
  }

  // NOTE: If you load the page at one device resolution and then resize to
  // a different one we don't re-render the diffs, because it's a complicated
  // mess and you could lose inline comments, cursor positions, etc.
  var renderer = (JX.Device.getDevice() == 'desktop') ? '2up' : '1up';

  // TODO: Once 1up works better, figure out when to show it.
  renderer = '2up';

  for (var k in config.registry) {
    var data = {
      ref : config.registry[k],
      whitespace: config.whitespace,
      renderer: renderer
    };

    new JX.Workflow(config.uri, data)
      .setHandler(JX.bind(null, onresponse, k))
      .start();
  }

  var highlighted = null;
  var highlight_class = null;

  JX.Stratcom.listen(
    'click',
    'differential-load',
    function(e) {
      var meta = e.getNodeData('differential-load');
      var diff;
      try {
        diff = JX.$(meta.id);
      } catch (e) {
        // Already loaded.
      }
      if (diff) {
        JX.DOM.setContent(
          diff,
          JX.$H('<div class="differential-loading">Loading...</div>'));
        var data = {
          ref : meta.ref,
          whitespace : config.whitespace
        };
        new JX.Workflow(config.uri, data)
          .setHandler(JX.bind(null, onresponse, meta.id))
          .start();
      }
      if (meta.kill) {
        e.kill();
      }
    });

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
