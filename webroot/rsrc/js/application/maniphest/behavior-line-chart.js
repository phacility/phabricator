/**
 * @provides javelin-behavior-line-chart
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-chart
 */

JX.behavior('line-chart', function(config) {
  var chart_node = JX.$(config.chartNodeID);

  var chart = new JX.Chart(chart_node);

  function onresponse(r) {
    chart.setData(r);
  }

  new JX.Request(config.dataURI, onresponse)
    .send();
});
