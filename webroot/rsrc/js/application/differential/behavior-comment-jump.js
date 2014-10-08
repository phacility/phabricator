/**
 * @provides javelin-behavior-differential-comment-jump
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('differential-comment-jump', function() {
  function handle_jump(offset) {
    return function(e) {
      var parent = JX.$('differential-review-stage');
      var clicked = e.getNode('differential-inline-comment');
      var inlines = JX.DOM.scry(parent, 'div', 'differential-inline-comment');
      var jumpto = null;

      for (var ii = 0; ii < inlines.length; ii++) {
        if (inlines[ii] == clicked) {
          jumpto = inlines[(ii + offset + inlines.length) % inlines.length];
          break;
        }
      }
      JX.Stratcom.invoke('differential-toggle-file-request', null, {
        element: jumpto
      });
      JX.DOM.scrollTo(jumpto);
      e.kill();
    };
  }

  JX.Stratcom.listen('click', 'differential-inline-prev', handle_jump(-1));
  JX.Stratcom.listen('click', 'differential-inline-next', handle_jump(+1));
});
