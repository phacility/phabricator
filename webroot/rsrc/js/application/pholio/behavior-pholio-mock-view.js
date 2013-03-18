/**
 * @provides javelin-behavior-pholio-mock-view
 * @requires javelin-behavior
 *           javelin-util
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 *           javelin-magical-init
 *           javelin-request
 *           javelin-history
 *           javelin-workflow
 *           javelin-mask
 *           javelin-behavior-device
 *           phabricator-keyboard-shortcut
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


/* -(  Stage  )-------------------------------------------------------------- */


  var stage = (function() {
    var loading = false;
    var stageElement = JX.$(config.panelID);
    var viewElement = JX.$(config.viewportID);
    var gutterElement = JX.$('mock-inline-comments');
    var reticles = [];
    var cards = [];
    var inline_phid_map = {};

    function begin_load() {
      if (loading) {
        return;
      }
      loading = true;
      clear_stage();
      draw_loading();
    }

    function end_load() {
      if (!loading) {
        return;
      }
      loading = false;
      draw_loading();
    }

    function draw_loading() {
      JX.DOM.alterClass(stageElement, 'pholio-image-loading', loading);
    }

    function add_inline_node(node, phid) {
      inline_phid_map[phid] = (inline_phid_map[phid] || []);
      inline_phid_map[phid].push(node);
    }

    function add_reticle(reticle, phid) {
      mark_ref(reticle, phid);

      reticles.push(reticle);
      add_inline_node(reticle, phid);

      viewElement.appendChild(reticle);
    }

    function clear_stage() {
      for (var ii = 0; ii < reticles.length; ii++) {
        JX.DOM.remove(reticles[ii]);
      }
      for (var ii = 0; ii < cards.length; ii++) {
        JX.DOM.remove(cards[ii]);
      }
      reticles = [];
      cards = [];
      inline_phid_map = {};
    }

    function highlight_inline(phid, show) {
      var nodes = inline_phid_map[phid] || [];
      var cls = 'pholio-mock-inline-comment-highlight';
      for (var ii = 0; ii < nodes.length; ii++) {
        JX.DOM.alterClass(nodes[ii], cls, show);
      }
    }

    function remove_inline(phid) {
      var nodes = inline_phid_map[phid] || [];
      for (var ii = 0; ii < nodes.length; ii++) {
        JX.DOM.remove(nodes[ii]);
      }
      delete inline_phid_map[phid];
    }

    function mark_ref(node, phid) {
      JX.Stratcom.addSigil(node, 'pholio-inline-ref');
      JX.Stratcom.addData(node, {phid: phid});
    }

    function add_card(card, phid) {
      mark_ref(card, phid);

      cards.push(card);
      add_inline_node(card, phid);

      gutterElement.appendChild(card);
    }

    return {
      beginLoad: begin_load,
      endLoad: end_load,
      addReticle: add_reticle,
      clearStage: clear_stage,
      highlightInline: highlight_inline,
      removeInline: remove_inline,
      addCard: add_card
    };
  })();

  function get_image_index(id) {
    for (var ii = 0; ii < config.images.length; ii++) {
      if (config.images[ii].id == id) {
        return ii;
      }
    }
    return null;
  }

  function get_image(id) {
    var idx = get_image_index(id);
    if (idx === null) {
      return idx;
    }
    return config.images[idx];
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

  function switch_image(delta) {
    if (!active_image) {
      return;
    }
    var idx = get_image_index(active_image.id)
    idx = (idx + delta + config.images.length) % config.images.length;
    select_image(config.images[idx].id);
  }

  function redraw_image() {

    // Force the stage to scale as a function of the viewport size. Broadly,
    // we make the stage 95% of the height of the viewport, then scale images
    // to fit within it.
    var new_y = (JX.Vector.getViewport().y * 0.90) - 150;
    new_y = Math.max(320, new_y);
    panel.style.height = new_y + 'px';

    if (!active_image || !active_image.tag) {
      return;
    }

    var tag = active_image.tag;

    // If the image is too wide or tall for the viewport, scale it down so it
    // fits.
    var w = JX.Vector.getDim(panel);
    w.x -= 40;
    w.y -= 40;
    var scale = 1;
    if (w.x < tag.naturalWidth) {
      scale = Math.min(scale, w.x / tag.naturalWidth);
    }
    if (w.y < tag.naturalHeight) {
      scale = Math.min(scale, w.y / tag.naturalHeight);
    }

    if (scale < 1) {
      tag.width = Math.floor(scale * tag.naturalWidth);
      tag.height = Math.floor(scale * tag.naturalHeight);
    } else {
      tag.width = tag.naturalWidth;
      tag.height = tag.naturalHeight;
    }

    viewport.style.top = Math.floor((new_y - tag.height) / 2) + 'px';

    stage.endLoad();

    JX.DOM.setContent(viewport, tag);

    redraw_inlines(active_image.id);
  }

  function select_image(image_id) {
    active_image = get_image(image_id);
    active_image.tag = null;

    stage.beginLoad();

    var img = JX.$N('img', {className: 'pholio-mock-image'});
    img.onload = JX.bind(img, onload_image, active_image.id);
    img.src = active_image.fullURI;

    var thumbs = JX.DOM.scry(
      JX.$('pholio-mock-carousel'),
      'a',
      'mock-thumbnail');

    for(var k in thumbs) {
      var thumb_meta = JX.Stratcom.getData(thumbs[k]);

      JX.DOM.alterClass(
        thumbs[k],
        'pholio-mock-carousel-thumb-current',
        (active_image.id == thumb_meta.imageID));
    }

    load_inline_comments();

    if (image_id != config.selectedID) {
      JX.History.replace(active_image.pageURI);
    }
  }

  JX.Stratcom.listen(
    ['mousedown', 'click'],
    'mock-thumbnail',
    function(e) {
      if (!e.isNormalMouseEvent()) {
        return;
      }
      e.kill();
      select_image(e.getNodeData('mock-thumbnail').imageID);
    });

  select_image(config.selectedID);

  JX.Stratcom.listen('mousedown', 'mock-viewport', function(e) {
    if (!e.isNormalMouseEvent()) {
      return;
    }

    if (JX.Device.getDevice() != 'desktop') {
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
    'pholio-inline-ref',
    function(e) {
      var phid = e.getNodeData('pholio-inline-ref').phid;
      var show = (e.getType() == 'mouseover');
      stage.highlightInline(phid, show);
    });

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (!is_dragging) {
        return;
      }

      is_dragging = false;
      if (!config.loggedIn) {
        new JX.Workflow(config.logInLink).start();
        return;
      }

      drag_end = get_image_xy(JX.$V(e));

      resize_selection(16);

      var data = {mockID: config.mockID};
      var handler = function(r) {
        var dialog = JX.$H(r).getFragment().firstChild;
        JX.DOM.appendContent(viewport, dialog);

        JX.$V(
          Math.min(drag_begin.x, drag_end.x),
          Math.max(drag_begin.y, drag_end.y) + 4
        ).setPos(dialog);

        JX.DOM.focus(JX.DOM.find(dialog, 'textarea'));
      }

      new JX.Workflow('/pholio/inline/save/', data)
        .setHandler(handler)
        .start();
    });

  function resize_selection(min_size) {
    var start = {
      x: Math.min(drag_begin.x, drag_end.x),
      y: Math.min(drag_begin.y, drag_end.y)
    };
    var end = {
      x: Math.max(drag_begin.x, drag_end.x),
      y: Math.max(drag_begin.y, drag_end.y)
    };

    var width = end.x - start.x;
    var height = end.y - start.y;

    if (width < min_size) {
      var addon = (min_size-width)/2;

      start.x = Math.max(0, start.x - addon);
      end.x = Math.min(active_image.tag.naturalWidth, end.x + addon);

      if (start.x == 0) {
        end.x = Math.min(min_size, active_image.tag.naturalWidth);
      } else if (end.x == active_image.tag.naturalWidth) {
        start.x = Math.max(0, active_image.tag.naturalWidth - min_size);
      }
    }

    if (height < min_size) {
      var addon = (min_size-height)/2;

      start.y = Math.max(0, start.y - addon);
      end.y = Math.min(active_image.tag.naturalHeight, end.y + addon);

      if (start.y == 0) {
        end.y = Math.min(min_size, active_image.tag.naturalHeight);
      } else if (end.y == active_image.tag.naturalHeight) {
        start.y = Math.max(0, active_image.tag.naturalHeight - min_size);
      }
    }

    drag_begin = start;
    drag_end = end;
    redraw_selection();
  }

  function redraw_inlines(id) {
    if (!active_image) {
      return;
    }

    if (active_image.id != id) {
      return;
    }

    stage.clearStage();
    var comment_holder = JX.$('mock-inline-comments');
    JX.DOM.setContent(comment_holder, render_image_info(active_image));

    var inlines = inline_comments[active_image.id];
    if (!inlines || !inlines.length) {
      return;
    }

    for (var ii = 0; ii < inlines.length; ii++) {
      var inline = inlines[ii];
      var card = JX.$H(inline.contentHTML).getFragment().firstChild;

      stage.addCard(card, inline.phid);

      if (!active_image.tag) {
        // The image itself hasn't loaded yet, so we can't draw the inline
        // reticles.
        continue;
      }

      var inline_selection = render_reticle_fill();
      stage.addReticle(inline_selection, inline.phid);
      position_inline_rectangle(inline, inline_selection);

      if (!inline.transactionphid) {
        var inline_draft = render_reticle_border();
        stage.addReticle(inline_draft, inline.phid);
        position_inline_rectangle(inline, inline_draft);
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
    selection_border = selection_border || render_reticle_border();
    selection_fill = selection_fill || render_reticle_fill();

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

    var nodes = [selection_fill, selection_border];
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

      stage.removeInline(data.phid);

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

      var form = JX.$('pholio-new-inline-comment-dialog');
      var text = JX.DOM.find(form, 'textarea').value;
      if (!text.length) {
        interrupt_typing();
        return;
      }

      var data = {
        mockID: config.mockID,
        imageID: active_image.id,
        startX: Math.min(drag_begin.x, drag_end.x),
        startY: Math.min(drag_begin.y, drag_end.y),
        endX: Math.max(drag_begin.x, drag_end.x),
        endY: Math.max(drag_begin.y, drag_end.y)
      };

      var handler = function(r) {
        if (!inline_comments[active_image.id]) {
          inline_comments[active_image.id] = [];
        }
        inline_comments[active_image.id].push(r);

        interrupt_typing();
        redraw_inlines(active_image.id);
      };

      JX.Workflow.newFromForm(form, data)
        .setHandler(handler)
        .start();
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


/* -(  Keyboard Shortcuts  )------------------------------------------------- */


  new JX.KeyboardShortcut(['j', 'right'], 'Show next image.')
    .setHandler(function() {
      switch_image(1);
    })
    .register();

  new JX.KeyboardShortcut(['k', 'left'], 'Show previous image.')
    .setHandler(function() {
      switch_image(-1);
    })
    .register();

  JX.DOM.listen(panel, 'gesture.swipe.end', null, function(e) {
    var data = e.getData();

    if (data.length <= (JX.Vector.getDim(panel) / 2)) {
      // If the user didn't move their finger far enough, don't switch.
      return;
    }

    switch_image(data.direction == 'right' ? -1 : 1);
  });

/* -(  Render  )------------------------------------------------------------- */


  function render_image_info(image) {
    var info = [];

    var title = JX.$N(
      'div',
      {className: 'pholio-image-title'},
      image.title);
    info.push(title);

    var desc = JX.$N(
      'div',
      {className: 'pholio-image-description'},
      image.desc);
    info.push(desc);

    var embed = JX.$N(
      'div',
      {className: 'pholio-image-embedding'},
      JX.$H('Embed this image:<br />{M' + config.mockID +
        ', image=' + image.id + '}'));
    info.push(embed);

    // Render image dimensions and visible size. If we have this infomation
    // from the server we can display some of it immediately; otherwise, we need
    // to wait for the image to load so we can read dimension information from
    // it.

    var image_x = image.width;
    var image_y = image.height;
    var display_x = null;
    if (image.tag) {
      image_x = image.tag.naturalWidth;
      image_y = image.tag.naturalHeight;
      display_x = image.tag.width;
    }

    var visible = [];
    if (image_x) {
      visible.push([image_x, '\u00d7', image_y, 'px']);
      if (display_x) {
        var area = Math.round(100 * (display_x / image_x));
        visible.push(' ');
        visible.push(
          JX.$N(
            'span',
            {className: 'pholio-visible-size'},
            ['(', area, '%', ')']));
      }
    }

    if (visible.length) {
      info.push(visible);
    }

    var full_link = JX.$N(
      'a',
      {href: image.fullURI, target: '_blank'},
      'View Full Image');
    info.push(full_link);

    for (var ii = 0; ii < info.length; ii++) {
      info[ii] = JX.$N('div', {className: 'pholio-image-info-item'}, info[ii]);
    }
    info = JX.$N('div', {className: 'pholio-image-info'}, info);

    return info;
  }

  function render_reticle_border() {
    return JX.$N(
      'div',
      {className: 'pholio-mock-select-border'});
  }

  function render_reticle_fill() {
    return JX.$N(
      'div',
      {className: 'pholio-mock-select-fill'});
  }


/* -(  Device Lightbox  )---------------------------------------------------- */

  // On devices, we show images full-size when the user taps them instead of
  // attempting to implement inlines.

  var lightbox = null;

  JX.Stratcom.listen('click', 'mock-viewport', function(e) {
    if (!e.isNormalMouseEvent()) {
      return;
    }
    if (JX.Device.getDevice() == 'desktop') {
      return;
    }
    lightbox_attach();
    e.kill();
  });

  JX.Stratcom.listen('click', 'pholio-device-lightbox', lightbox_detach);
  JX.Stratcom.listen('resize', null, lightbox_resize);

  function lightbox_attach() {
    JX.DOM.alterClass(document.body, 'lightbox-attached', true);
    JX.Mask.show('jx-dark-mask');

    lightbox = lightbox_render();
    var image = JX.$N('img');
    image.onload = lightbox_loaded;
    setTimeout(function() {
      image.src = active_image.fullURI;
    }, 1000);
    JX.DOM.setContent(lightbox, image);
    JX.DOM.alterClass(lightbox, 'pholio-device-lightbox-loading', true);

    lightbox_resize();

    document.body.appendChild(lightbox);
  }

  function lightbox_detach() {
    JX.DOM.remove(lightbox);
    JX.Mask.hide();
    JX.DOM.alterClass(document.body, 'lightbox-attached', false);
    lightbox = null;
  }

  function lightbox_resize(e) {
    if (!lightbox) {
      return;
    }
    JX.Vector.getScroll().setPos(lightbox);
    JX.Vector.getViewport().setDim(lightbox);
  }

  function lightbox_loaded() {
    JX.DOM.alterClass(lightbox, 'pholio-device-lightbox-loading', false);
  }

  function lightbox_render() {
    var el = JX.$N('div', {className: 'pholio-device-lightbox'});
    JX.Stratcom.addSigil(el, 'pholio-device-lightbox');
    return el;
  }


/* -(  Preload  )------------------------------------------------------------ */

  var preload = [];
  for (var ii = 0; ii < config.images.length; ii++) {
    preload.push(config.images[ii].fullURI);
  }

  function preload_next() {
    next_src = preload[0];
    if (!next_src) {
      return;
    }
    preload.splice(0, 1);

    var img = JX.$N('img');
    img.onload = preload_next;
    img.onerror = preload_next;
    img.src = next_src;
  }

  preload_next();


});
