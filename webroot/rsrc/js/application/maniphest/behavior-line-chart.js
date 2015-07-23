/**
 * @provides javelin-behavior-line-chart
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-vector
 */

JX.behavior('line-chart', function(config) {

  var h = JX.$(config.hardpoint);
  var p = JX.$V(h);
  var d = JX.Vector.getDim(h);
  var mx = 60;
  var my = 30;

  var r = new Raphael(h, d.x, d.y);

  var l = r.linechart(
    mx, my,
    d.x - (2 * mx), d.y - (2 * my),
    config.x,
    config.y,
    {
      nostroke: false,
      axis: '0 0 1 1',
      shade: true,
      gutter: 1,
      colors: config.colors || ['#2980b9']
    });

  function format(value, type) {
    switch (type) {
      case 'epoch':
        return new Date(parseInt(value, 10) * 1000).toLocaleDateString();
      case 'int':
        return parseInt(value, 10);
      default:
        return value;
    }
  }

  // Format the X axis.

  var n = 2;
  var ii = 0;
  var text = l.axis[0].text.items;
  for (var k in text) {
    if (ii++ % n) {
      text[k].attr({text: ''});
    } else {
      var cur = text[k].attr('text');
      var str = format(cur, config.xformat);
      text[k].attr({text: str});
    }
  }

  // Show values on hover.

  l.hoverColumn(function() {
    this.tags = r.set();
    for (var yy = 0; yy < config.y.length; yy++) {
      var yvalue = 0;
      for (var ii = 0; ii < config.x[0].length; ii++) {
        if (config.x[0][ii] > this.axis) {
          break;
        }
        yvalue = format(config.y[yy][ii], config.yformat);
      }

      var xvalue = format(this.axis, config.xformat);

      var tag = r.tag(
        this.x,
        this.y[yy],
        [xvalue, yvalue].join('\n'),
        180,
        24);
      tag
        .insertBefore(this)
        .attr([{fill : '#fff'}, {fill: '#000'}]);

      this.tags.push(tag);
    }
  }, function() {
    this.tags && this.tags.remove();
  });

});
