/**
 * @provides javelin-behavior-line-chart
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-vector
 *           phui-chart-css
 */

JX.behavior('line-chart', function(config) {

  function fn(n) {
    return n + '(' + JX.$A(arguments).slice(1).join(', ') + ')';
  }

  var h = JX.$(config.hardpoint);
  var d = JX.Vector.getDim(h);

  var padding = {
    top: 24,
    left: 48,
    bottom: 48,
    right: 32
  };

  var size = {
    frameWidth: d.x,
    frameHeight: d.y,
  };

  size.width = size.frameWidth - padding.left - padding.right;
  size.height = size.frameHeight - padding.top - padding.bottom;

  var x = d3.time.scale()
    .range([0, size.width]);

  var y = d3.scale.linear()
    .range([size.height, 0]);

  var xAxis = d3.svg.axis()
    .scale(x)
    .orient('bottom');

  var yAxis = d3.svg.axis()
    .scale(y)
    .orient('left');

  var svg = d3.select('#' + config.hardpoint).append('svg')
    .attr('width', size.frameWidth)
    .attr('height', size.frameHeight)
    .attr('class', 'chart');

  var g = svg.append('g')
      .attr('transform', fn('translate', padding.left, padding.top));

  g.append('rect')
      .attr('class', 'inner')
      .attr('width', size.width)
      .attr('height', size.height);

  var line = d3.svg.line()
    .x(function(d) { return x(d.date); })
    .y(function(d) { return y(d.count); });

  var data = [];
  for (var ii = 0; ii < config.x[0].length; ii++) {
    data.push(
      {
        date: new Date(config.x[0][ii] * 1000),
        count: +config.y[0][ii]
      });
  }

  x.domain(d3.extent(data, function(d) { return d.date; }));

  var yex = d3.extent(data, function(d) { return d.count; });
  yex[0] = 0;
  yex[1] = yex[1] * 1.05;
  y.domain(yex);

  g.append('path')
    .datum(data)
    .attr('class', 'line')
    .attr('d', line);

  g.append('g')
    .attr('class', 'x axis')
    .attr('transform', fn('translate', 0, size.height))
    .call(xAxis);

  g.append('g')
    .attr('class', 'y axis')
    .attr('transform', fn('translate', 0, 0))
    .call(yAxis);

  var div = d3.select('body')
    .append('div')
    .attr('class', 'chart-tooltip')
    .style('opacity', 0);

  g.selectAll('dot')
    .data(data)
    .enter()
    .append('circle')
    .attr('class', 'point')
    .attr('r', 3)
    .attr('cx', function(d) { return x(d.date); })
    .attr('cy', function(d) { return y(d.count); })
    .on('mouseover', function(d) {
      var d_y = d.date.getFullYear();
      var d_m = d.date.getMonth();
      var d_d = d.date.getDate();

      div
        .html(d_y + '-' + d_m + '-' + d_d + ': ' + d.count)
        .style('opacity', 0.9)
        .style('left', (d3.event.pageX - 60) + 'px')
        .style('top', (d3.event.pageY - 38) + 'px');
      })
    .on('mouseout', function() {
      div.style('opacity', 0);
    });

});
