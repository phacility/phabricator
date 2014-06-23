/**
 * @provides javelin-behavior-differential-show-field-details
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('differential-show-field-details', function() {

  JX.Stratcom.listen(
    'click',
    ['differential-results-row-show', 'tag:a'],
    function(e) {
      toggle(e, true);
    });

  JX.Stratcom.listen(
    'click',
    ['differential-results-row-hide', 'tag:a'],
    function(e) {
      toggle(e, false);
    });

  function toggle(e, show) {
    e.kill();

    var f = show ? JX.DOM.show : JX.DOM.hide;
    var g = show ? JX.DOM.hide : JX.DOM.show;

    var table = e.getNode('differential-results-table');
    var rows  = JX.DOM.scry(table, 'tr', 'differential-results-row-toggle');
    for (var ii = 0; ii < rows.length; ii++) {
      f(rows[ii]);
    }

    g(JX.DOM.find(table, 'tr', 'differential-results-row-show'));
    f(JX.DOM.find(table, 'tr', 'differential-results-row-hide'));
  }

});
