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
    ['mouseover', 'mouseout'],
    ['differential-changeset', 'tag:td'],
    function(e) {
      var t = e.getTarget();

      // NOTE: Using className is not best practice, but the diff UI is perf
      // sensitive.
      if (!t.className.match(/cov/)) {
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
        if (t.className.match(/cov-C/)) {
          msg = 'Covered';
          highlight_class = 'source-cov-C';
        } else if (t.className.match(/cov-U/)) {
          msg = 'Not Covered';
          highlight_class = 'source-cov-U';
        } else if (t.className.match(/cov-N/)) {
          msg = 'Not Executable';
          highlight_class = 'source-cov-N';
        }

        if (msg) {
          JX.Tooltip.show(t, 120, 'E', msg);
        }

        if (highlight_class) {
          highlighted = t.previousSibling;
          JX.DOM.alterClass(highlighted, highlight_class, true);
        }
      }

    });



});
