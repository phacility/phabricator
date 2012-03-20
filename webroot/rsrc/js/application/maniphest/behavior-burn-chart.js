/**
 * @provides javelin-behavior-burn-chart
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-vector
 */

JX.behavior('burn-chart', function(config) {


  var h = JX.$(config.hardpoint);
  var p = JX.$V(h);
  var d = JX.Vector.getDim(h);
  var mx = 60;
  var my = 30;

  var r = Raphael(p.x, p.y, d.x, d.y);

  var l = r.linechart(
    mx, my,
    d.x - (2 * mx), d.y - (2 * my),
    config.x,
    config.y,
    {
      nostroke: false,
      axis: "0 0 1 1",
      shade: true,
      gutter: 1,
      colors: ['#d06']
    });


  // Convert the epoch timestamps on the X axis into readable dates.

  var n = 2;
  var ii = 0;
  var text = l.axis[0].text.items;
  for (var k in text) {
    if (ii++ % n) {
      text[k].attr({text: ''});
    } else {
      var cur = text[k].attr('text');
      var date = new Date(parseInt(cur, 10) * 1000);
      var str = date.toLocaleDateString();
      text[k].attr({text: str});
    }
  }

  l.hoverColumn(function() {

    var open = 0;
    for (var ii = 0; ii < config.x[0].length; ii++) {
      if (config.x[0][ii] > this.axis) {
        break;
      }
      open = config.y[0][ii];
    }

    var date  = new Date(parseInt(this.axis, 10) * 1000).toLocaleDateString();
    var total = open + " Open Tasks";

    var tag = r.tag(
      this.x,
      this.y[0],
      [date, total].join("\n"),
      180,
      24);
    tag
      .insertBefore(this)
      .attr([{fill : '#fff'}, {fill: '#000'}]);

    this.tags = r.set();
    this.tags.push(tag);
  }, function() {
    this.tags && this.tags.remove();
  });

});

