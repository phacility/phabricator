/**
 * @provides javelin-behavior-pholio-mock-view
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 *           javelin-magical-init
 *           javelin-request
 */
JX.behavior('pholio-mock-view', function(config) {
  var is_dragging = false;
  var wrapper = JX.$('mock-wrapper');
  var image;
  var imageData;
  var startPos;
  var endPos;

  var selection_border;
  var selection_fill;

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


  function draw_rectangle(nodes, current, init) {
    for (var ii = 0; ii < nodes.length; ii++) {
      var node = nodes[ii];

      JX.$V(
        Math.abs(current.x-init.x),
        Math.abs(current.y-init.y))
      .setDim(node);

      JX.$V(
        (current.x-init.x < 0) ? current.x:init.x,
        (current.y-init.y < 0) ? current.y:init.y)
      .setPos(node);
    }
  }

  function getRealXY(parent, point) {
    var pos = {x: (point.x - parent.x), y: (point.y - parent.y)};
    var dim = JX.Vector.getDim(image);

    pos.x = Math.max(0, Math.min(pos.x, dim.x));
    pos.y = Math.max(0, Math.min(pos.y, dim.y));

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

    selection_border = JX.$N(
      'div',
      {className: 'pholio-mock-select-border'});

    selection_fill = JX.$N(
      'div',
      {className: 'pholio-mock-select-fill'});

    JX.$V(startPos.x, startPos.y).setPos(selection_border);
    JX.$V(startPos.x, startPos.y).setPos(selection_fill);

    JX.DOM.appendContent(wrapper, [selection_border, selection_fill]);
  });

  JX.enableDispatch(document.body, 'mousemove');
  JX.Stratcom.listen('mousemove',null, function(e) {
    if (!is_dragging) {
      return;
    }

    draw_rectangle(
      [selection_border, selection_fill],
      getRealXY(JX.$V(wrapper),
      JX.$V(e)), startPos);
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
        JX.DOM.remove(selection_border);
        JX.DOM.remove(selection_fill);
        return;
      }

      selection_fill.title = comment;

      var saveURL = "/pholio/inline/" + imageData['imageID'] + "/";

      var inlineComment = new JX.Request(saveURL, function(r) {

      });

      var commentToAdd = {
        mockID: config.mockID,
        imageID: imageData['imageID'],
        startX: Math.min(startPos.x, endPos.x),
        startY: Math.min(startPos.y, endPos.y),
        endX: Math.max(startPos.x,endPos.x),
        endY: Math.max(startPos.y,endPos.y),
        comment: comment};


      inlineComment.addData(commentToAdd);
      inlineComment.send();

    });

});



