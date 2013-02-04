/**
 * @provides javelin-behavior-pholio-mock-view
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 *           javelin-event
 */
JX.behavior('pholio-mock-view', function(config) {
  var is_dragging = false;
  var wrapper = JX.$('mock-wrapper');
  var image;
  var imageData;
  var startPos;
  var endPos;
  var selection;

  JX.Stratcom.listen(
    'click', // Listen for clicks...
    'mock-thumbnail', // ...on nodes with sigil "mock-thumbnail".
    function(e) {
      var data = e.getNodeData('mock-thumbnail');

      var main = JX.$(config.mainID);
      JX.Stratcom.addData(
        main,
        {
          fullSizeURI: data['fullSizeURI'],
          imageID: data['imageID']
        });

      main.src = data.fullSizeURI;

      JX.DOM.setContent(wrapper,main);
    });


  function draw_rectangle(node, current, init) {
    JX.$V(
      Math.abs(current.x-init.x),
      Math.abs(current.y-init.y))
    .setDim(node);

    JX.$V(
      (current.x-init.x < 0) ? current.x:init.x,
      (current.y-init.y < 0) ? current.y:init.y)
    .setPos(node);
  }

  function getRealXY(parent, point) {
    var pos = {x: (point.x - parent.x), y: (point.y - parent.y)};

    if (pos.x < 0) pos.x = 0;
    else if (pos.x > image.clientWidth) pos.x = image.clientWidth - 1;

    if (pos.y < 0) pos.y = 0;
    else if (pos.y > image.clientHeight) pos.y = image.clientHeight - 2;

    return pos;
  }

  JX.Stratcom.listen('mousedown', 'mock-wrapper', function(e) {
    if (!e.isNormalMouseEvent()) {
      return;
    }

    image = JX.$(config.mainID);
    imageData = JX.Stratcom.getData(image);

    e.getRawEvent().target.draggable = false;
    is_dragging = true;

    startPos = getRealXY(JX.$V(wrapper),JX.$V(e));

    selection = JX.$N(
      'div',
      {className: 'pholio-mock-select'}
    );


    JX.$V(startPos.x,startPos.y).setPos(selection);

    JX.DOM.appendContent(wrapper, selection);


  });

  JX.enableDispatch(document.body, 'mousemove');
  JX.Stratcom.listen('mousemove',null, function(e) {
    if (!is_dragging) {
      return;
    }

    draw_rectangle(selection, getRealXY(JX.$V(wrapper), JX.$V(e)), startPos);
  });

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (!is_dragging) {
        return;
      }
      is_dragging = false;

      endPos = getRealXY(JX.$V(wrapper), JX.$V(e));

      comment = window.prompt("Add your comment");
      if (comment == null || comment == "") {
        selection.remove();
        return;
      }

      selection.title = comment;

      console.log("ImageID: " + imageData['imageID'] +
        ", coords: (" + Math.min(startPos.x, endPos.x) + "," +
        Math.min(startPos.y, endPos.y) + ") -> (" +
        Math.max(startPos.x,endPos.x) + "," + Math.max(startPos.y,endPos.y) +
        "), comment: " + comment);

    });

});



