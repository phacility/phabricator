/**
 * @provides javelin-behavior-line-chart
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-vector
 *           phui-chart-css
 */

JX.behavior('line-chart', function(config) {

  function css_function(n) {
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
      .attr('transform', css_function('translate', padding.left, padding.top));

  g.append('rect')
      .attr('class', 'inner')
      .attr('width', size.width)
      .attr('height', size.height);

  function as_date(value) {
    return new Date(value * 1000);
  }

  x.domain([as_date(config.xMin), as_date(config.xMax)]);
  y.domain([config.yMin, config.yMax]);

  for (var idx = 0; idx < config.datasets.length; idx++) {
    var dataset = config.datasets[idx];

    var line = d3.svg.line()
      .x(function(d) { return x(d.xvalue); })
      .y(function(d) { return y(d.yvalue); });

    var data = [];
    for (var ii = 0; ii < dataset.x.length; ii++) {
      data.push(
        {
          xvalue: as_date(dataset.x[ii]),
          yvalue: dataset.y[ii]
        });
    }

    g.append('path')
      .datum(data)
      .attr('class', 'line')
      .style('stroke', dataset.color)
      .attr('d', line);

    g.selectAll('dot')
      .data(data)
      .enter()
      .append('circle')
      .attr('class', 'point')
      .attr('r', 3)
      .attr('cx', function(d) { return x(d.xvalue); })
      .attr('cy', function(d) { return y(d.yvalue); })
      .on('mouseover', function(d) {
        var d_y = d.xvalue.getFullYear();

        // NOTE: Javascript months are zero-based. See PHI1017.
        var d_m = d.xvalue.getMonth() + 1;

        var d_d = d.xvalue.getDate();

        div
          .html(d_y + '-' + d_m + '-' + d_d + ': ' + d.yvalue)
          .style('opacity', 0.9)
          .style('left', (d3.event.pageX - 60) + 'px')
          .style('top', (d3.event.pageY - 38) + 'px');
        })
      .on('mouseout', function() {
        div.style('opacity', 0);
      });
  }

  g.append('g')
    .attr('class', 'x axis')
    .attr('transform', css_function('translate', 0, size.height))
    .call(xAxis);

  g.append('g')
    .attr('class', 'y axis')
    .attr('transform', css_function('translate', 0, 0))
    .call(yAxis);

  var div = d3.select('body')
    .append('div')
    .attr('class', 'chart-tooltip')
    .style('opacity', 0);

});
