/**
 * @provides javelin-behavior-differential-show-field-details
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('differential-show-field-details', function() {

  JX.Stratcom.listen(
    'click',
    'differential-show-field-details',
    function(e) {
      var node = e.getNode('tag:td');
      var data = JX.Stratcom.getData(node);
      var details = JX.DOM.scry(
        node,
        'div',
        'differential-field-detail');
      for (var i=0; i < details.length; i++) {
        if (!data.detailsShown) {
          JX.DOM.show(details[i]);
        } else {
          JX.DOM.hide(details[i]);
        }
      }
      data.detailsShown = !data.detailsShown;
      e.kill();
    });

});
