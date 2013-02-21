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
      load_inline_comments();
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

      var saveURL = "/pholio/inline/save/";

      var inlineComment = new JX.Request(saveURL, function(r) {
        JX.DOM.appendContent(
          JX.$('mock-inline-comments'),
          JX.$H(r.contentHTML));
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

    function load_inline_comments() {
      var data = JX.Stratcom.getData(JX.$(config.mainID));
      var comment_holder = JX.$('mock-inline-comments');
      JX.DOM.setContent(comment_holder, '');

      var inline_comments_url = "/pholio/inline/" + data['imageID'] + "/";
      var inline_comments = new JX.Request(inline_comments_url, function(r) {

        if (r.length > 0) {
          for(i=0; i < r.length; i++) {
            var inlineSelection = JX.$N(
              'div',
              {
                id: r[i].phid + "_selection",
                className: 'pholio-mock-select-border'
              });

            JX.Stratcom.addData(
              inlineSelection,
              {phid: r[i].phid});

            JX.Stratcom.addSigil(inlineSelection, "image_selection");
            JX.DOM.appendContent(comment_holder, JX.$H(r[i].contentHTML));

            JX.DOM.appendContent(wrapper, inlineSelection);

            JX.$V(r[i].x, r[i].y).setPos(inlineSelection);
            JX.$V(r[i].width, r[i].height).setDim(inlineSelection);

            if (r[i].transactionphid == null) {

              var inlineDraft = JX.$N(
                'div',
                {
                  className: 'pholio-mock-select-fill',
                  id: r[i].phid + "_fill"
                });

              JX.$V(r[i].x, r[i].y).setPos(inlineDraft);
              JX.$V(r[i].width, r[i].height).setDim(inlineDraft);

              JX.Stratcom.addData(
                inlineDraft,
                {phid: r[i].phid});

              JX.Stratcom.addSigil(inlineDraft, "image_selection");
              JX.DOM.appendContent(wrapper, inlineDraft);
            }
          }
        }

        JX.Stratcom.listen(
          'click',
          'inline-delete',
          function(e) {
            var data = e.getNodeData('inline-delete');
            e.kill();
            JX.DOM.hide(
              JX.$(data.phid + "_comment"),
              JX.$(data.phid + "_fill"),
              JX.$(data.phid + "_selection")
            );
          });

        JX.Stratcom.listen(
          'click',
          'inline-edit',
          function(e) {
            e.kill();
            alert("WIP");
          }
        );

        JX.Stratcom.listen(
          'mouseover',
          'image_selection',
          function(e) {
            var data = e.getNodeData('image_selection');

            var inline_comment = JX.$(data.phid + "_comment");
            JX.DOM.alterClass(inline_comment,
              'pholio-mock-inline-comment-highlight', true);
        });

        JX.Stratcom.listen(
          'mouseout',
          'image_selection',
          function(e) {
          var data = e.getNodeData('image_selection');

          var inline_comment = JX.$(data.phid + "_comment");
            JX.DOM.alterClass(inline_comment,
              'pholio-mock-inline-comment-highlight', false);
        });
      });

      inline_comments.send();
    }

    load_inline_comments();
});
