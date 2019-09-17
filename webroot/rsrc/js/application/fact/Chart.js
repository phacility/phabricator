/**
 * @provides javelin-chart
 * @requires phui-chart-css
 *           d3
 *           javelin-chart-curtain-view
 *           javelin-chart-function-label
 */
JX.install('Chart', {

  construct: function(root_node) {
    this._rootNode = root_node;

    JX.Stratcom.listen('resize', null, JX.bind(this, this._redraw));
  },

  members: {
    _rootNode: null,
    _data: null,
    _chartContainerNode: null,
    _curtain: null,

    setData: function(blob) {
      this._data = blob;
      this._redraw();
    },

    _redraw: function() {
      if (!this._data) {
        return;
      }

      var hardpoint = this._rootNode;
      var curtain = this._getCurtain();
      var container_node = this._getChartContainerNode();

      var content = [
        container_node,
        curtain.getNode(),
      ];

      JX.DOM.setContent(hardpoint, content);

      // Remove the old chart (if one exists) before drawing the new chart.
      JX.DOM.setContent(container_node, []);

      var viewport = JX.Vector.getDim(container_node);
      var config = this._data;

      function css_function(n) {
        return n + '(' + JX.$A(arguments).slice(1).join(', ') + ')';
      }

      var padding = {};
      if (JX.Device.isDesktop()) {
        padding = {
          top: 24,
          left: 48,
          bottom: 48,
          right: 12
        };
      } else {
        padding = {
          top: 12,
          left: 36,
          bottom: 24,
          right: 4
        };
      }

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

      var svg = d3.select(container_node).append('svg')
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

      curtain.reset();

      for (var idx = 0; idx < config.datasets.length; idx++) {
        var dataset = config.datasets[idx];

        switch (dataset.type) {
          case 'stacked-area':
            this._newStackedArea(g, dataset, x, y, div, curtain);
            break;
        }
      }

      curtain.redraw();

      g.append('g')
        .attr('class', 'x axis')
        .attr('transform', css_function('translate', 0, size.height))
        .call(xAxis);

      g.append('g')
        .attr('class', 'y axis')
        .attr('transform', css_function('translate', 0, 0))
        .call(yAxis);
    },

    _newStackedArea: function(g, dataset, x, y, div, curtain) {
      var ii;

      var to_date = JX.bind(this, this._newDate);

      var area = d3.area()
        .x(function(d) { return x(to_date(d.x)); })
        .y0(function(d) {
          // When the area is positive, draw it above the X axis. When the area
          // is negative, draw it below the X axis. We currently avoid having
          // functions which cross the X axis by clever construction.
          if (d.y0 >= 0 && d.y1 >= 0) {
            return y(d.y0);
          }

          if (d.y0 <= 0 && d.y1 <= 0) {
            return y(d.y0);
          }

          return y(0);
        })
        .y1(function(d) { return y(d.y1); });

      var line = d3.line()
        .x(function(d) { return x(to_date(d.x)); })
        .y(function(d) { return y(d.y1); });

      for (ii = 0; ii < dataset.data.length; ii++) {
        var label = new JX.ChartFunctionLabel(dataset.labels[ii]);

        var fill_color = label.getFillColor() || label.getColor();

        g.append('path')
          .style('fill', fill_color)
          .attr('d', area(dataset.data[ii]));

        var stroke_color = label.getColor();

        g.append('path')
          .attr('class', 'line')
          .style('stroke', stroke_color)
          .attr('d', line(dataset.data[ii]));

        curtain.addFunctionLabel(label);
      }

      // Now that we've drawn all the areas and lines, draw the dots.
      for (ii = 0; ii < dataset.data.length; ii++) {
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

            var y = parseInt(d.y1);

            var label = d.n + ' Points';

            var view =
              d_y + '-' + d_m + '-' + d_d + ': ' + y + '<br />' +
              label;

            div
              .html(view)
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
    },

    _getCurtain: function() {
      if (!this._curtain) {
        this._curtain = new JX.ChartCurtainView();
      }
      return this._curtain;
    },

    _getChartContainerNode: function() {
      if (!this._chartContainerNode) {
        var attrs = {
          className: 'chart-container'
        };

        this._chartContainerNode = JX.$N('div', attrs);
      }
      return this._chartContainerNode;
    }

  }

});
