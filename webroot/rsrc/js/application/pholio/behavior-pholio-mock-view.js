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
JX.behavior('pholio-mock-view', function(config, statics) {
  var is_dragging = false;

  var drag_begin;
  var drag_end;

  var selection_reticle;
  var active_image;

  var inline_comments = {};

  function get_image_index(id) {
    for (var ii = 0; ii < statics.images.length; ii++) {
      if (statics.images[ii].id == id) {
        return ii;
      }
    }
    return null;
  }

  function get_image_navindex(id) {
    for (var ii = 0; ii < statics.navsequence.length; ii++) {
      if (statics.navsequence[ii] == id) {
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
    return statics.images[idx];
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
    var idx = get_image_navindex(active_image.id);
    if (idx === null) {
      return;
    }
    idx = (idx + delta + statics.navsequence.length) %
      statics.navsequence.length;
    select_image(statics.navsequence[idx]);
  }

  function redraw_image() {
    if (!statics.enabled) {
      return;
    }
    var new_y;

    // If we don't have an image yet, just scale the stage relative to the
    // entire viewport height so the jump isn't too jumpy when the image loads.
    if (!active_image || !active_image.tag) {
      new_y = (JX.Vector.getViewport().y * 0.80);
      new_y = Math.max(320, new_y);
      statics.panel.style.height = new_y + 'px';

      return;
    }

    var tag = active_image.tag;

    // If the image is too wide for the viewport, scale it down so it fits.
    // If it is too tall, just let the viewport scroll.
    var w = JX.Vector.getDim(statics.panel);

    // Leave 24px margins on either side of the image.
    w.x -= 48;

    var scale = 1;
    if (w.x < tag.naturalWidth) {
      scale = Math.min(scale, w.x / tag.naturalWidth);
    }

    if (scale < 1) {
      tag.width = Math.floor(scale * tag.naturalWidth);
      tag.height = Math.floor(scale * tag.naturalHeight);
    } else {
      tag.width = tag.naturalWidth;
      tag.height = tag.naturalHeight;
    }

    // Scale the viewport's vertical size to the image's adjusted size.
    new_y = Math.max(320, tag.height + 48);
    statics.panel.style.height = new_y + 'px';

    statics.viewport.style.top = Math.floor((new_y - tag.height) / 2) + 'px';

    statics.stage.endLoad();

    JX.DOM.setContent(statics.viewport, tag);

    redraw_inlines(active_image.id);
  }

  function select_image(image_id) {
    active_image = get_image(image_id);
    active_image.tag = null;

    statics.stage.beginLoad();

    var img = JX.$N('img', {className: 'pholio-mock-image'});
    img.onload = JX.bind(img, onload_image, active_image.id);
    img.src = active_image.stageURI;

    var thumbs = JX.DOM.scry(
      JX.$('pholio-mock-thumb-grid'),
      'a',
      'mock-thumbnail');

    for(var k in thumbs) {
      var thumb_meta = JX.Stratcom.getData(thumbs[k]);

      JX.DOM.alterClass(
        thumbs[k],
        'pholio-mock-thumb-grid-current',
        (active_image.id == thumb_meta.imageID));
    }

    load_inline_comments();
    if (image_id != statics.selectedID) {
      JX.History.replace(active_image.pageURI);
    }
  }

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
    var addon;

    if (width < min_size) {
      addon = (min_size-width)/2;

      start.x = Math.max(0, start.x - addon);
      end.x = Math.min(active_image.tag.naturalWidth, end.x + addon);

      if (start.x === 0) {
        end.x = Math.min(min_size, active_image.tag.naturalWidth);
      } else if (end.x == active_image.tag.naturalWidth) {
        start.x = Math.max(0, active_image.tag.naturalWidth - min_size);
      }
    }

    if (height < min_size) {
      addon = (min_size-height)/2;

      start.y = Math.max(0, start.y - addon);
      end.y = Math.min(active_image.tag.naturalHeight, end.y + addon);

      if (start.y === 0) {
        end.y = Math.min(min_size, active_image.tag.naturalHeight);
      } else if (end.y == active_image.tag.naturalHeight) {
        start.y = Math.max(0, active_image.tag.naturalHeight - min_size);
      }
    }

    drag_begin = start;
    drag_end = end;
    redraw_selection();
  }

  function render_image_header(image) {
    // Render image dimensions and visible size. If we have this information
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
      if (display_x) {
        var area = Math.round(100 * (display_x / image_x));
        visible.push(
          JX.$N(
            'span',
            {className: 'pholio-visible-size'},
            [area, '%']));
        visible.push(' ');
      }
      visible.push(['(', image_x, ' \u00d7 ', image_y, ')']);
    }

    return visible;
  }

  function redraw_inlines(id) {
    if (!statics.enabled) {
      return;
    }

    if (!active_image) {
      return;
    }

    if (active_image.id != id) {
      return;
    }

    statics.stage.clearStage();
    var comment_holder = JX.$('mock-image-description');
    JX.DOM.setContent(comment_holder, render_image_info(active_image));

    var image_header = JX.$('mock-image-header');
    JX.DOM.setContent(image_header, render_image_header(active_image));

    var inlines = inline_comments[active_image.id];
    if (!inlines || !inlines.length) {
      return;
    }

    for (var ii = 0; ii < inlines.length; ii++) {
      var inline = inlines[ii];

      if (!active_image.tag) {
        // The image itself hasn't loaded yet, so we can't draw the inline
        // reticles.
        continue;
      }

      var classes = [];
      if (!inline.transactionPHID) {
        classes.push('pholio-mock-reticle-draft');
      } else {
        classes.push('pholio-mock-reticle-final');
      }

      var inline_selection = render_reticle(classes,
        'pholio-mock-comment-icon phui-font-fa fa-comment');
      statics.stage.addReticle(inline_selection, inline.id);
      position_inline_rectangle(inline, inline_selection);
    }
  }

  function position_inline_rectangle(inline, rect) {
    var scale = get_image_scale();

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
    return Math.min(
      img.width / img.naturalWidth,
      img.height / img.naturalHeight);
  }

  function redraw_selection() {
    if (!statics.enabled) {
      return;
    }

    var classes = ['pholio-mock-reticle-selection'];
    selection_reticle = selection_reticle || render_reticle(classes, '');

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

    statics.viewport.appendChild(selection_reticle);
    p.setPos(selection_reticle);
    d.setDim(selection_reticle);
  }

  function clear_selection() {
    selection_reticle && JX.DOM.remove(selection_reticle);
    selection_reticle = null;
  }

  function load_inline_comments() {
    var id = active_image.id;
    var inline_comments_uri = '/pholio/inline/list/' + id + '/';

    new JX.Request(inline_comments_uri, function(r) {
      inline_comments[id] = r;
      redraw_inlines(id);
    }).send();
  }


/* -(  Render  )------------------------------------------------------------- */


  function render_image_info(image) {
    var info = [];

    var buttons = [];

    var classes = ['pholio-image-button'];

    if (image.isViewable) {
      classes.push('pholio-image-button-active');
    } else {
      classes.push('pholio-image-button-disabled');
    }

    buttons.push(
      JX.$N(
        'div',
        {
          className: classes.join(' ')
        },
        JX.$N(
          image.isViewable ? 'a' : 'span',
          {
            href: image.fullURI,
            target: '_blank',
            className: 'pholio-image-button-link'
          },
          JX.$H(statics.fullIcon))));

    classes = ['pholio-image-button', 'pholio-image-button-active'];

    buttons.push(
      JX.$N(
        'form',
        {
          className: classes.join(' '),
          action: image.downloadURI,
          method: 'POST',
          sigil: 'download'
        },
        JX.$N(
          'button',
          {
            href: image.downloadURI,
            className: 'pholio-image-button-link'
          },
          JX.$H(statics.downloadIcon))));

    if (image.title === '') {
      image.title = 'Untitled Masterpiece';
    }
    var title = JX.$N(
      'div',
      {className: 'pholio-image-title'},
      image.title);
    info.push(title);

    if (!image.isObsolete) {
      var img_len = statics.currentSetSize;
      var rev = JX.$N(
        'div',
        {className: 'pholio-image-revision'},
        JX.$H('Current Revision (' + img_len + ' images)'));
      info.push(rev);
    } else {
      var prev = JX.$N(
        'div',
        {className: 'pholio-image-revision'},
        JX.$H('(Previous Revision)'));
      info.push(prev);
    }

    for (var ii = 0; ii < info.length; ii++) {
      info[ii] = JX.$N('div', {className: 'pholio-image-info-item'}, info[ii]);
    }
    info = JX.$N('div', {className: 'pholio-image-info'}, info);

    if (image.descriptionMarkup === '') {
      return [buttons, info];
    } else {
      var desc = JX.$N(
        'div',
        {className: 'pholio-image-description'},
        JX.$H(image.descriptionMarkup));
      return [buttons, info, desc];
    }
  }

  function render_reticle(classes, inner_classes) {
    var inner = JX.$N('div', {className: inner_classes});
    var outer = JX.$N(
      'div',
      {className: ['pholio-mock-reticle'].concat(classes).join(' ')}, inner);
    return outer;
  }


/* -(  Device Lightbox  )---------------------------------------------------- */

  // On devices, we show images full-size when the user taps them instead of
  // attempting to implement inlines.

  var lightbox = null;

  function lightbox_attach() {
    JX.DOM.alterClass(document.body, 'lightbox-attached', true);
    JX.Mask.show('jx-dark-mask');

    lightbox = lightbox_render();
    var image = JX.$N('img');
    image.onload = lightbox_loaded;
    setTimeout(function() {
      image.src = active_image.stageURI;
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

  function lightbox_resize() {
    if (!statics.enabled) {
      return;
    }
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


  function preload_next() {
    var next_src = statics.preload[0];
    if (!next_src) {
      return;
    }
    statics.preload.splice(0, 1);

    var img = JX.$N('img');
    img.onload = preload_next;
    img.onerror = preload_next;
    img.src = next_src;
  }


/* -(  Installaton  )-------------------------------------------------------- */


  function update_statics(data) {
    statics.enabled = true;

    statics.mockID = data.mockID;
    statics.commentFormID = data.commentFormID;
    statics.images = data.images;
    statics.selectedID = data.selectedID;
    statics.loggedIn = data.loggedIn;
    statics.logInLink = data.logInLink;
    statics.navsequence = data.navsequence;
    statics.downloadIcon = data.downloadIcon;
    statics.fullIcon = data.fullIcon;
    statics.currentSetSize = data.currentSetSize;

    statics.stage = (function() {
      var loading = false;
      var stageElement = JX.$(data.panelID);
      var viewElement = JX.$(data.viewportID);
      var reticles = [];

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

      function add_reticle(reticle, id) {
        mark_ref(reticle, id);
        reticles.push(reticle);
        viewElement.appendChild(reticle);
      }

      function clear_stage() {
        var ii;
        for (ii = 0; ii < reticles.length; ii++) {
          JX.DOM.remove(reticles[ii]);
        }
        reticles = [];
      }

      function mark_ref(node, id) {
        JX.Stratcom.addSigil(node, 'pholio-inline-ref');
        JX.Stratcom.addData(node, {inlineID: id});
      }

      return {
        beginLoad: begin_load,
        endLoad: end_load,
        addReticle: add_reticle,
        clearStage: clear_stage
      };
    })();

    statics.panel = JX.$(data.panelID);
    statics.viewport = JX.$(data.viewportID);

    select_image(data.selectedID);

    load_inline_comments();
    if (data.loggedIn && data.commentFormID) {
      JX.DOM.invoke(JX.$(data.commentFormID), 'shouldRefresh');
    }
    redraw_image();

    statics.preload = [];
    for (var ii = 0; ii < data.images.length; ii++) {
      statics.preload.push(data.images[ii].stageURI);
    }

    preload_next();

  }

  function install_extra_listeners() {
    JX.DOM.listen(statics.panel, 'gesture.swipe.end', null, function(e) {
      var data = e.getData();

      if (data.length <= (JX.Vector.getDim(statics.panel) / 2)) {
        // If the user didn't move their finger far enough, don't switch.
        return;
      }
      switch_image(data.direction == 'right' ? -1 : 1);
    });
  }

  function install_mock_view() {
    JX.enableDispatch(document.body, 'mouseenter');
    JX.enableDispatch(document.body, 'mouseleave');

    JX.Stratcom.listen(
      ['mouseenter', 'mouseover'],
      'mock-panel',
      function(e) {
        JX.DOM.alterClass(e.getNode('mock-panel'), 'mock-has-cursor', true);
      });

    JX.Stratcom.listen('mouseleave', 'mock-panel', function(e) {
      var node = e.getNode('mock-panel');
      if (e.getTarget() == node) {
        JX.DOM.alterClass(node, 'mock-has-cursor', false);
      }
    });

    JX.Stratcom.listen(
      'click',
      'mock-thumbnail',
      function(e) {
        if (!e.isNormalMouseEvent()) {
          return;
        }
        e.kill();
        select_image(e.getNodeData('mock-thumbnail').imageID);
      });

    JX.Stratcom.listen('mousedown', 'mock-viewport', function(e) {
      if (!e.isNormalMouseEvent()) {
        return;
      }

      if (JX.Device.getDevice() != 'desktop') {
        return;
      }

      if (JX.Stratcom.pass()) {
        return;
      }

      if (is_dragging) {
        return;
      }

      e.kill();

      if (!active_image.isImage) {
        // If this is a PDF or something like that, we eat the event but we
        // don't let users add inlines to the thumbnail.
        return;
      }

      is_dragging = true;
      drag_begin = get_image_xy(JX.$V(e));
      drag_end = drag_begin;

      redraw_selection();
    });

    JX.enableDispatch(document.body, 'mousemove');
    JX.Stratcom.listen('mousemove', null, function(e) {
      if (!statics.enabled) {
        return;
      }
      if (!is_dragging) {
        return;
      }
      drag_end = get_image_xy(JX.$V(e));
      redraw_selection();
    });

    JX.Stratcom.listen(
      'mousedown',
      'pholio-inline-ref',
      function(e) {
        e.kill();

        var id = e.getNodeData('pholio-inline-ref').inlineID;

        var active_id = active_image.id;
        var handler = function(r) {
          var inlines = inline_comments[active_id];

          for (var ii = 0; ii < inlines.length; ii++) {
            if (inlines[ii].id == id) {
              if (r.id) {
                inlines[ii] = r;
              } else {
                inlines.splice(ii, 1);
              }
              break;
            }
          }

          redraw_inlines(active_id);
          JX.DOM.invoke(JX.$(statics.commentFormID), 'shouldRefresh');
        };

        new JX.Workflow('/pholio/inline/' + id + '/')
          .setHandler(handler)
          .start();
      });

    JX.Stratcom.listen(
      'mouseup',
      null,
      function(e) {
        if (!statics.enabled) {
          return;
        }
        if (!is_dragging) {
          return;
        }

        is_dragging = false;
        if (!statics.loggedIn) {
          new JX.Workflow(statics.logInLink).start();
          return;
        }

        drag_end = get_image_xy(JX.$V(e));

        resize_selection(16);

        var data = {
          mockID: statics.mockID,
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

          redraw_inlines(active_image.id);
          JX.DOM.invoke(JX.$(statics.commentFormID), 'shouldRefresh');
        };

        clear_selection();

        new JX.Workflow('/pholio/inline/', data)
          .setHandler(handler)
          .start();
      });

    JX.Stratcom.listen('resize', null, redraw_image);


    /* Keyboard Shortcuts */
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


    /* Lightbox listeners */
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

    JX.Stratcom.listen(
      'quicksand-redraw',
      null,
      function (e) {
        var data = e.getData();
        var new_config;
        if (!data.newResponse.mockViewConfig) {
          statics.enabled = false;
          return;
        }
        if (data.fromServer) {
          new_config = data.newResponse.mockViewConfig;
        } else {
          new_config = statics.mockViewConfigCache[data.newResponseID];
        }
        update_statics(new_config);
        if (data.fromServer) {
          install_extra_listeners();
        }
      });
  }

  if (!statics.installed) {
    var current_page_id = JX.Quicksand.getCurrentPageID();
    statics.mockViewConfigCache = {};
    statics.mockViewConfigCache[current_page_id] = config;
    update_statics(config);

    statics.installed = install_mock_view();
    install_extra_listeners();
  }

});
