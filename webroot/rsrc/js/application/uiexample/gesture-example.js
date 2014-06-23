/**
 * @requires javelin-stratcom
 *           javelin-behavior
 *           javelin-vector
 *           javelin-dom
 * @provides javelin-behavior-phabricator-gesture-example
 */

JX.behavior('phabricator-gesture-example', function(config) {

  var strokes = [];
  var current = [];

  var root = JX.$(config.rootID);

  var canvas = JX.$N('canvas');
  var d = JX.Vector.getDim(root);
  canvas.width = d.x;
  canvas.height = d.y;
  root.appendChild(canvas);

  var cxt = canvas.getContext('2d');
  JX.Stratcom.listen(
    'gesture.swipe.end',
    null,
    function(e) {
      var stroke = get_stroke(e);
      strokes.push(stroke);
      current = [];
      redraw();
    });

  JX.Stratcom.listen(
    'gesture.swipe.move',
    null,
    function(e) {
      var stroke = get_stroke(e);
      current = [stroke];
      redraw();
    });

  JX.Stratcom.listen(
    'gesture.swipe.cancel',
    null,
    function() {
      current = [];
      redraw();
    });

  function get_stroke(e) {
    var data = e.getData();
    var p = JX.$V(root);
    return [
      data.p0.x - p.x,
      data.p0.y - p.y,
      data.p1.x - p.x,
      data.p1.y - p.y
    ];
  }

  function redraw() {
    cxt.fillStyle = '#dfdfdf';
    cxt.fillRect(0, 0, d.x, d.y);

    var s;
    var ii;
    for (ii = 0; ii < strokes.length; ii++) {
      s = strokes[ii];
      cxt.strokeStyle = 'rgba(0, 0, 0, 0.50)';
      cxt.beginPath();
        cxt.moveTo(s[0], s[1]);
        cxt.lineTo(s[2], s[3]);
      cxt.stroke();
    }

    for (ii = 0; ii < current.length; ii++) {
      s = current[ii];
      cxt.strokeStyle = 'rgba(255, 0, 0, 1)';
      cxt.beginPath();
        cxt.moveTo(s[0], s[1]);
        cxt.lineTo(s[2], s[3]);
      cxt.stroke();
    }
  }

  redraw();
});
