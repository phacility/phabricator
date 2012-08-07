/**
 * @provides javelin-behavior-differential-populate
 * @requires javelin-behavior
 *           javelin-workflow
 *           javelin-util
 *           javelin-dom
 *           javelin-stratcom
 *           phabricator-tooltip
 */

JX.behavior('differential-populate', function(config) {

  function onresponse(target, response) {
    JX.DOM.replace(JX.$(target), JX.$H(response.changeset));
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

  for (var k in config.registry) {
    var data = {
      ref : config.registry[k],
      whitespace: config.whitespace
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
        var align = 'E';
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
            align = 'N'; // TODO: 'W'
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
