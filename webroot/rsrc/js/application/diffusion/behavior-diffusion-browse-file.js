/**
 * @provides javelin-behavior-diffusion-browse-file
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-tooltip
 */

JX.behavior('diffusion-browse-file', function(config, statics) {
  if (statics.installed) {
    return;
  }
  statics.installed = true;

  var map = config.labels;

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    ['phabricator-source', 'tag:td'],
    function(e) {
      var target = e.getTarget();

      // NOTE: We're using raw classnames instead of sigils and metadata here
      // because these elements are unusual: there are a lot of them on the
      // page, and rendering all the extra metadata to do this in a normal way
      // would be needlessly expensive. This is an unusual case.

      if (!target.className.match(/cov-/)) {
        return;
      }

      if (e.getType() == 'mouseout') {
        JX.Tooltip.hide();
        return;
      }

      for (var k in map) {
        if (!target.className.match(k)) {
          continue;
        }

        var label = map[k];
        JX.Tooltip.show(target, 300, 'E', label);
        break;
      }
    });
});
