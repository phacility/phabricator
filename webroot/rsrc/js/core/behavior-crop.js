/**
 * @provides javelin-behavior-aphront-crop
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-vector
 *           javelin-magical-init
 */

 JX.behavior('aphront-crop', function(config) {

  var dragging = false;
  var startX, startY;
  var finalX, finalY;

  var cropBox = JX.$(config.cropBoxID);
  var basePos = JX.$V(cropBox);
  cropBox.style.height = config.height + 'px';
  cropBox.style.width = config.width + 'px';
  var baseD = JX.$V(config.width, config.height);

  var image = JX.DOM.find(cropBox, 'img', 'crop-image');
  image.style.height = (config.imageH * config.scale) + 'px';
  image.style.width = (config.imageW * config.scale) + 'px';
  var imageD = JX.$V(
    config.imageW * config.scale,
    config.imageH * config.scale
  );
  var minLeft = baseD.x - imageD.x;
  var minTop = baseD.y - imageD.y;

  var ondrag = function(e) {
    e.kill();
    dragging = true;
    var p = JX.$V(e);
    startX = p.x;
    startY = p.y;
  };

  var onmove = function(e) {
    if (!dragging) {
      return;
    }
    e.kill();

    var p = JX.$V(e);
    var dx = startX - p.x;
    var dy = startY - p.y;
    var imagePos = JX.$V(image);
    var moveLeft = imagePos.x - basePos.x - dx;
    var moveTop = imagePos.y - basePos.y - dy;

    image.style.left = Math.min(Math.max(minLeft, moveLeft), 0) + 'px';
    image.style.top = Math.min(Math.max(minTop, moveTop), 0) + 'px';

    // reset these; a new beginning!
    startX = p.x;
    startY = p.y;

    // save off where we are right now
    imagePos = JX.$V(image);
    finalX = Math.abs(imagePos.x - basePos.x);
    finalY = Math.abs(imagePos.y - basePos.y);
    JX.DOM.find(cropBox, 'input', 'crop-x').value = finalX;
    JX.DOM.find(cropBox, 'input', 'crop-y').value = finalY;
  };

  var ondrop = function() {
    if (!dragging) {
      return;
    }
    dragging = false;
  };

  // NOTE: Javelin does not dispatch mousemove by default.
  JX.enableDispatch(cropBox, 'mousemove');

  JX.DOM.listen(cropBox, 'mousedown', [],  ondrag);
  JX.DOM.listen(cropBox, 'mousemove', [],  onmove);
  JX.DOM.listen(cropBox, 'mouseup',   [],  ondrop);
  JX.DOM.listen(cropBox, 'mouseout',  [],  ondrop);

});
