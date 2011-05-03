/**
 * @provides javelin-behavior-differential-diff-radios
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('differential-diff-radios', function(config) {
  JX.Stratcom.listen(
    'click',
    'differential-new-radio',
    function(e) {
      var target = e.getTarget();
      var adjust;
      var node;
      var reset = false;
      for (var ii = 0; ii < config.radios.length; ii++) {
        node = JX.$(config.radios[ii]);
        if (parseInt(node.value, 10) >= parseInt(target.value, 10)) {
          if (node.checked) {
            node.checked = false;
            reset = true;
          }
          node.disabled = 'disabled';
        } else {
          node.disabled = '';
          if (!adjust || adjust.value < node.value) {
            adjust = node;
          }
        }
      }
      if (reset && adjust) {
        adjust.checked = 'checked';
      }
    });
});
