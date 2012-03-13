/**
 * @provides javelin-behavior-phabricator-tooltips
 * @requires javelin-behavior
 *           javelin-stratcom
 *           phabricator-tooltip
 * @javelin
 */

JX.behavior('phabricator-tooltips', function(config) {

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'has-tooltip',
    function (e) {
      if (e.getType() == 'mouseout') {
        JX.Tooltip.hide();
        return;
      }

      var data = e.getNodeData('has-tooltip');

      JX.Tooltip.show(
        e.getTarget(),
        data.size || 120,
        data.align || 'N',
        data.tip);
    });

});
