/**
 * @provides javelin-behavior-punchcard
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-vector
 *           raphael-g-dot
 */

JX.behavior('punchcard', function(config) {

  var h = JX.$(config.hardpoint);
  var p = JX.Vector.getPos(h);
  var d = JX.Vector.getDim(h);
  var mx = 10;
  var my = 10;

  var r = Raphael(p.x, p.y, d.x, d.y);
  // TODO(mshang): Internationalize this.
  var axisy = ["Sun", "Sat", "Fri", "Thu", "Wed", "Tue", "Mon"];
  var axisx = ["12a", "1a", "2a", "3a", "4a", "5a", "6a", "7a",
               "8a", "9a", "10a", "11a", "12p", "1p", "2p", "3p",
               "4p", "5p", "6p", "7p", "8p", "9p", "10p", "11p"];

  var counts = [];
  var days = [];
  var hours = [];

  for (var ii in config.counts) {
    for (var jj = 0; jj < config.counts[ii].length; jj++) {
      counts = counts.concat(config.counts[ii][jj]);
      days = days.concat(-parseInt(ii));
      hours = hours.concat(jj);
    }
  }
  
  r.dotchart(
    mx,
    my,
    d.x - (2 * mx),
    d.y - (2 * my),
    hours,
    days,
    counts,
    {
      symbol: "o", 
      max: 10, 
      heat: true, 
      axis: "0 0 1 1", 
      axisxstep: 23, 
      axisystep: 6, 
      axisxlabels: axisx, 
      axisxtype: " ",
      axisylabels: axisy,
      axisytype: " ", 
    }
  );
});

