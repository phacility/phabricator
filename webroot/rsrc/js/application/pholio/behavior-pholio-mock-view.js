/**
 * @provides javelin-behavior-pholio-mock-view
 * @requires javelin-behavior
 *           javelin-util
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 *           javelin-magical-init
 *           javelin-request
 */
JX.behavior('pholio-mock-view', function(config) {
  var is_dragging = false;

  var drag_begin;
  var drag_end;
  var panel = JX.$(config.panelID);
  var viewport = JX.$(config.viewportID);

  var selection_border;
  var selection_fill;
  var active_image;

  var inline_comments = {};

  function get_image(id) {
    for (var ii = 0; ii < config.images.length; ii++) {
      if (config.images[ii].id == id) {
        return config.images[ii];
      }
    }
    return null;
  }

  function onload_image(id) {
    if (active_image.id != id) {
      // The user has clicked another image before this one loaded, so just
      // bail.
      return;
    }

    active_image.tag = this;
    redraw_image();
  }

  function redraw_image() {
    if (!active_image || !active_image.tag) {
      return;
    }

    var tag = active_image.tag;

    // If the image is too wide for the viewport, scale it down so it fits.
    // (If it is too tall, we just let the user scroll.)
    var w = JX.Vector.getDim(panel);
    w.x -= 40;
    if (w.x < tag.naturalWidth) {
      var scale = w.x / tag.naturalWidth;
      tag.width = Math.floor(scale * tag.naturalWidth);
      tag.height = Math.floor(scale * tag.naturalHeight);
    } else {
      tag.width = tag.naturalWidth;
      tag.height = tag.naturalHeight;
    }

    var new_y = (JX.Vector.getViewport().y * 0.85) - 150;
    new_y = Math.max(320, new_y);

    if (tag.height + 40 < new_y) {
      panel.style.height = new_y + 'px';
      viewport.style.top = Math.floor(((new_y + 40) - tag.height) / 2) + 'px';
    } else {
      panel.style.height = '';
      viewport.style.top = '';
    }

    // NOTE: This also clears inline comment reticles.
    JX.DOM.setContent(viewport, tag);

    redraw_inlines(active_image.id);
  }

  function select_image(image_id) {
    active_image = get_image(image_id);
    active_image.tag = null;

    var img = JX.$N('img', {className: 'pholio-mock-image'});
    img.onload = JX.bind(img, onload_image, active_image.id);
    img.src = active_image.fullURI;

    var thumbs = JX.DOM.scry(
      JX.$('pholio-mock-carousel'),
      'div',
      'mock-thumbnail');

    for(var k in thumbs) {
      var thumb_meta = JX.Stratcom.getData(thumbs[k]);

      JX.DOM.alterClass(
        thumbs[k],
        'pholio-mock-carousel-thumb-current',
        (active_image.id == thumb_meta.imageID));
    }

    load_inline_comments();
  }

  JX.Stratcom.listen(
    'click',
    'mock-thumbnail',
    function(e) {
      e.kill();
      select_image(e.getNodeData('mock-thumbnail').imageID);
    });

  // Select and show the first image.
  select_image(config.images[0].id);

  JX.Stratcom.listen('mousedown', 'mock-panel', function(e) {
    if (!e.isNormalMouseEvent()) {
      return;
    }

    if (drag_begin) {
      return;
    }

    e.kill();

    is_dragging = true;
    drag_begin = get_image_xy(JX.$V(e));
    drag_end = drag_begin;

    redraw_selection();
  });

  JX.enableDispatch(document.body, 'mousemove');
  JX.Stratcom.listen('mousemove', null, function(e) {
    if (!is_dragging) {
      return;
    }
    drag_end = get_image_xy(JX.$V(e));
    redraw_selection();
  });

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'image_selection',
    function(e) {
      var data = e.getNodeData('image_selection');
      var comment = JX.$(data.phid + "_comment");
      var highlight = (e.getType() == 'mouseover');

      JX.DOM.alterClass(
        comment,
        'pholio-mock-inline-comment-highlight',
        highlight);
  });

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'inline_comment',
    function(e) {
      var data = e.getNodeData('inline_comment');
      var selection = JX.$(data.phid + "_selection");
      var highlight = (e.getType() == 'mouseover');

      JX.DOM.alterClass(
        selection,
        'pholio-mock-inline-comment-highlight',
        highlight);
  });

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (!is_dragging) {
        return;
      }
      is_dragging = false;

      drag_end = get_image_xy(JX.$V(e));

      var create_inline = new JX.Request("/pholio/inline/save/", function(r) {
        JX.DOM.appendContent(JX.$('pholio-mock-image-container'), JX.$H(r));

        var dialog = JX.$('pholio-new-inline-comment-dialog');

        var viewportVector = JX.$V(viewport);
        var viewportDimensions = JX.Vector.getDim(viewport);

        JX.$V(
          // TODO: This is a little funky for now.
          Math.max(drag_begin.x, drag_end.x),
          Math.max(drag_begin.y, drag_end.y)
        ).setPos(dialog);

        });
      create_inline.addData({mockID: config.mockID});
      create_inline.send();

    });

  function redraw_inlines(id) {
    if (!active_image) {
      return;
    }

    if (active_image.id != id) {
      return;
    }

    var comment_holder = JX.$('mock-inline-comments');
    JX.DOM.setContent(comment_holder, '');

    var inlines = inline_comments[active_image.id];
    if (!inlines || !inlines.length) {
      return;
    }

    for (var ii = 0; ii < inlines.length; ii++) {
      var inline = inlines[ii];
      JX.DOM.appendContent(comment_holder, JX.$H(inline.contentHTML));

      if (!active_image.tag) {
        // The image itself hasn't loaded yet, so we can't draw the inline
        // reticles.
        continue;
      }

      var inlineSelection = JX.$N(
        'div',
        {
          id: inline.phid + "_selection",
          className: 'pholio-mock-select-border'
        });

      JX.Stratcom.addData(
        inlineSelection,
        {phid: inline.phid});

      JX.Stratcom.addSigil(inlineSelection, "image_selection");

      JX.DOM.appendContent(viewport, inlineSelection);

      position_inline_rectangle(inline, inlineSelection);

      if (!inline.transactionphid) {

        var inlineDraft = JX.$N(
          'div',
          {
            className: 'pholio-mock-select-fill',
            id: inline.phid + "_fill"
          });

        position_inline_rectangle(inline, inlineDraft);

        JX.Stratcom.addData(
          inlineDraft,
          {phid: inline.phid});

        JX.Stratcom.addSigil(inlineDraft, "image_selection");
        JX.DOM.appendContent(viewport, inlineDraft);
      }
    }
  }

  function position_inline_rectangle(inline, rect) {
    var scale = active_image.tag.width / active_image.tag.naturalWidth;

    JX.$V(scale * inline.x, scale * inline.y).setPos(rect);
    JX.$V(scale * inline.width, scale * inline.height).setDim(rect);
  }

  function get_image_xy(p) {
    var img = active_image.tag;
    var imgp = JX.$V(img);

    var scale = 1 / get_image_scale();

    var x = scale * Math.max(0, Math.min(p.x - imgp.x, img.width));
    var y = scale * Math.max(0, Math.min(p.y - imgp.y, img.height));

    return {
      x: x,
      y: y
    };
  }

  function get_image_scale() {
    var img = active_image.tag;
    return img.width / img.naturalWidth;
  }

  function redraw_selection() {
    selection_border = selection_border || JX.$N(
      'div',
      {className: 'pholio-mock-select-border'});

    selection_fill = selection_fill || JX.$N(
      'div',
      {className: 'pholio-mock-select-fill'});

    var p = JX.$V(
      Math.min(drag_begin.x, drag_end.x),
      Math.min(drag_begin.y, drag_end.y));

    var d = JX.$V(
      Math.max(drag_begin.x, drag_end.x) - p.x,
      Math.max(drag_begin.y, drag_end.y) - p.y);

    var scale = get_image_scale();

    p.x *= scale;
    p.y *= scale;
    d.x *= scale;
    d.y *= scale;

    var nodes = [selection_border, selection_fill];
    for (var ii = 0; ii < nodes.length; ii++) {
      var node = nodes[ii];
      viewport.appendChild(node);
      p.setPos(node);
      d.setDim(node);
    }
  }

  function clear_selection() {
    selection_fill && JX.DOM.remove(selection_fill);
    selection_border && JX.DOM.remove(selection_border);
  }

  function load_inline_comments() {
    var comment_holder = JX.$('mock-inline-comments');
    JX.DOM.setContent(comment_holder, '');

    var id = active_image.id;
    var inline_comments_uri = "/pholio/inline/" + id + "/";

    new JX.Request(inline_comments_uri, function(r) {
      inline_comments[id] = r;
      redraw_inlines(id);
    }).send();
  }

  JX.Stratcom.listen(
    'click',
    'inline-delete',
    function(e) {
      var data = e.getNodeData('inline-delete');
      e.kill();
      interrupt_typing();

      JX.DOM.hide(
        JX.$(data.phid + "_comment"),
        JX.$(data.phid + "_fill"),
        JX.$(data.phid + "_selection"));

      var deleteURI = '/pholio/inline/delete/' + data.id + '/';
      var del = new JX.Request(deleteURI, function(r) {

        });
      del.send();

    });

  JX.Stratcom.listen(
    'click',
    'inline-edit',
    function(e) {
      var data = e.getNodeData('inline-edit');
      e.kill();

      interrupt_typing();

      var editURI = "/pholio/inline/edit/" + data.id + '/';

      var edit_dialog = new JX.Request(editURI, function(r) {
        var dialog = JX.$N(
          'div',
          {
            className: 'pholio-edit-inline-popup'
          },
          JX.$H(r));

        JX.DOM.setContent(JX.$(data.phid + '_comment'), dialog);
      });

      edit_dialog.send();
    });

  JX.Stratcom.listen(
    'click',
    'inline-edit-cancel',
    function(e) {
      var data = e.getNodeData('inline-edit-cancel');
      e.kill();
      load_inline_comment(data.id);
  });

  JX.Stratcom.listen(
    'click',
    'inline-edit-submit',
    function(e) {
      var data = e.getNodeData('inline-edit-submit');
      var editURI = "/pholio/inline/edit/" + data.id + '/';
      e.kill();

      var edit = new JX.Request(editURI, function(r) {
        load_inline_comment(data.id);
      });
      edit.addData({
        op: 'update',
        content: JX.DOM.find(JX.$(data.phid + '_comment'), 'textarea').value
      });
      edit.send();
  });

  JX.Stratcom.listen(
    'click',
    'inline-save-cancel',
    function(e) {
      e.kill();
      interrupt_typing();
    }
  );

  JX.Stratcom.listen(
    'click',
    'inline-save-submit',
    function(e) {
      e.kill();

      var new_content = JX.DOM.find(
        JX.$('pholio-new-inline-comment-dialog'),
        'textarea').value;

      if (new_content == null || new_content.length == 0) {
        alert("Empty comment")
        return;
      }

      var saveURI = "/pholio/inline/save/";

      var inlineComment = new JX.Request(saveURI, function(r) {
        if (!inline_comments[active_image.id]) {
          inline_comments[active_image.id] = [];
        }
        inline_comments[active_image.id].push(r);

        interrupt_typing();
        redraw_inlines(active_image.id);
      });

      var commentToAdd = {
        mockID: config.mockID,
        op: 'save',
        imageID: active_image.id,
        startX: Math.min(drag_begin.x, drag_end.x),
        startY: Math.min(drag_begin.y, drag_end.y),
        endX: Math.max(drag_begin.x, drag_end.x),
        endY: Math.max(drag_begin.y, drag_end.y),
        comment: new_content
      };

      inlineComment.addData(commentToAdd);
      inlineComment.send();
    }
  );

  function load_inline_comment(id) {
    var viewInlineURI = '/pholio/inline/view/' + id + '/';
    var inline_comment = new JX.Request(viewInlineURI, function(r) {
      JX.DOM.replace(JX.$(r.phid + '_comment'), JX.$H(r.contentHTML));
    });
    inline_comment.send();
  }

  function interrupt_typing() {
    clear_selection();

    try {
      JX.DOM.remove(JX.$('pholio-new-inline-comment-dialog'));
    } catch (x) {
      // TODO: For now, ignore this.
    }

    drag_begin = null;
  }

  load_inline_comments();

  JX.Stratcom.listen('resize', null, redraw_image);
  redraw_image();

});
