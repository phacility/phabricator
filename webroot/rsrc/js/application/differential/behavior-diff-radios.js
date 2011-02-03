/**
 * @provides javelin-behavior-differential-diff-radios
 * @requires javelin-lib-dev
 */

JX.behavior('differential-diff-radios', function(config) {
  JX.Stratcom.listen(
    'click',
    'new-radio',
    function(e) {
      var target = e.getTarget();
      var adjust;
      var node;
      var reset = false;
      for (var ii = 0; ii < config.radios.length; ii++) {
        node = JX.$(config.radios[ii]);
        if (node.value >= target.value) {
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
