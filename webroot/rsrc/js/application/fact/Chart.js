/**
 * @provides javelin-chart
 * @requires phui-chart-css
 *           d3
 */
JX.install('Chart', {

  construct: function(root_node) {
    this._rootNode = root_node;

    JX.Stratcom.listen('resize', null, JX.bind(this, this._redraw));
  },

  members: {
    _rootNode: null,
    _data: null,

    setData: function(blob) {
      this._data = blob;
      this._redraw();
    },

    _redraw: function() {
      if (!this._data) {
        return;
      }

      var hardpoint = this._rootNode;

      // Remove the old chart (if one exists) before drawing the new chart.
      JX.DOM.setContent(hardpoint, []);

      var viewport = JX.Vector.getDim(hardpoint);
      var config = this._data;

      function css_function(n) {
        return n + '(' + JX.$A(arguments).slice(1).join(', ') + ')';
      }

      var padding = {
        top: 24,
        left: 48,
        bottom: 48,
        right: 32
      };

      var size = {
        frameWidth: viewport.x,
        frameHeight: viewport.y,
      };

      size.width = size.frameWidth - padding.left - padding.right;
      size.height = size.frameHeight - padding.top - padding.bottom;

      var x = d3.scaleTime()
        .range([0, size.width]);

      var y = d3.scaleLinear()
        .range([size.height, 0]);

      var xAxis = d3.axisBottom(x);
      var yAxis = d3.axisLeft(y);

      var svg = d3.select('#' + hardpoint.id).append('svg')
        .attr('width', size.frameWidth)
        .attr('height', size.frameHeight)
        .attr('class', 'chart');

      var g = svg.append('g')
          .attr(
            'transform',
            css_function('translate', padding.left, padding.top));

      g.append('rect')
          .attr('class', 'inner')
          .attr('width', size.width)
          .attr('height', size.height);

      x.domain([this._newDate(config.xMin), this._newDate(config.xMax)]);
      y.domain([config.yMin, config.yMax]);

      var div = d3.select('body')
        .append('div')
        .attr('class', 'chart-tooltip')
        .style('opacity', 0);

      for (var idx = 0; idx < config.datasets.length; idx++) {
        var dataset = config.datasets[idx];

        switch (dataset.type) {
          case 'stacked-area':
            this._newStackedArea(g, dataset, x, y, div);
            break;
        }
      }

      g.append('g')
        .attr('class', 'x axis')
        .attr('transform', css_function('translate', 0, size.height))
        .call(xAxis);

      g.append('g')
        .attr('class', 'y axis')
        .attr('transform', css_function('translate', 0, 0))
        .call(yAxis);
    },

    _newStackedArea: function(g, dataset, x, y, div) {
      var to_date = JX.bind(this, this._newDate);

      var area = d3.area()
        .x(function(d) { return x(to_date(d.x)); })
        .y0(function(d) { return y(d.y0); })
        .y1(function(d) { return y(d.y1); });

      var line = d3.line()
        .x(function(d) { return x(to_date(d.x)); })
        .y(function(d) { return y(d.y1); });

      for (var ii = 0; ii < dataset.data.length; ii++) {
        g.append('path')
          .style('fill', dataset.color[ii % dataset.color.length])
          .style('opacity', '0.15')
          .attr('d', area(dataset.data[ii]));

        g.append('path')
          .attr('class', 'line')
          .attr('d', line(dataset.data[ii]));

        g.selectAll('dot')
          .data(dataset.events[ii])
          .enter()
          .append('circle')
          .attr('class', 'point')
          .attr('r', 3)
          .attr('cx', function(d) { return x(to_date(d.x)); })
          .attr('cy', function(d) { return y(d.y1); })
          .on('mouseover', function(d) {
            var dd = to_date(d.x);

            var d_y = dd.getFullYear();

            // NOTE: Javascript months are zero-based. See PHI1017.
            var d_m = dd.getMonth() + 1;

            var d_d = dd.getDate();

            div
              .html(d_y + '-' + d_m + '-' + d_d + ': ' + d.y1)
              .style('opacity', 0.9)
              .style('left', (d3.event.pageX - 60) + 'px')
              .style('top', (d3.event.pageY - 38) + 'px');
            })
          .on('mouseout', function() {
            div.style('opacity', 0);
          });

      }
    },

    _newDate: function(epoch) {
      return new Date(epoch * 1000);
    }

  }

});
