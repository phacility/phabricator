/**
 * @provides javelin-behavior-phabricator-nav
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-magical-init
 *           javelin-vector
 *           javelin-util
 *           javelin-fx
 * @javelin
 */

JX.behavior('phabricator-nav', function(config) {

  var app = JX.$(config.appID);
  var content = JX.$(config.contentID);
  var local = config.localID ? JX.$(config.localID) : null;
  var main = JX.$(config.mainID);


// - Sliding Menu Animations ---------------------------------------------------

  var animations = [];
  function slide_menu(position) {
    var app_width = 150;
    var local_width = local ? 300 : 0;

    var shifts = {
      0: 0,
      1: app_width - 10,
      2: app_width + local_width
    };
    var shift = shifts[position];

    while (animations.length) {
      animations.pop().stop();
    }
    animations.push(build_animation(app, -shift));
    local && animations.push(build_animation(local, -shift + app_width));
    animations.push(build_animation(content, -shift + app_width + local_width));

    select_button(position);
  }

  function build_animation(element, target) {
    return new JX.FX(element)
      .setDuration(100)
      .start({left: [JX.$V(element).x, target]});
  }


// - Sliding Menu Buttons ------------------------------------------------------

  var button_positions = {
    0: [JX.$('phone-menu1'), JX.$('tablet-menu1')],
    1: [JX.$('phone-menu2')],
    2: [JX.$('phone-menu3'), JX.$('tablet-menu2')]
  };

  for (var k in button_positions) {
    for (var ii = 0; ii < button_positions[k].length; ii++) {
      var onclick = function(p, e) {
        e.kill();
        slide_menu(p);
      };
      onclick = JX.bind(null, onclick, k);
      JX.DOM.listen(
        button_positions[k][ii],
        ['touchstart', 'mousedown'],
        null,
        onclick);
    }
  }

  function select_button(position) {
    for (var k in button_positions) {
      for (var ii = 0; ii < button_positions[k].length; ii++) {
        JX.DOM.alterClass(
          button_positions[k][ii],
          'nav-button-selected',
          (k == position));
      }
    }
  }


// - Flexible Navigation Column ------------------------------------------------

  if (config.dragID) {
    var dragging;
    var track;

    var drag = JX.$(config.dragID);
    JX.enableDispatch(document.body, 'mousemove');

    JX.DOM.listen(drag, 'mousedown', null, function(e) {
      dragging = JX.$V(e);

      // Show the "col-resize" cursor on the whole document while we're
      // dragging, since the mouse will slip off the actual bar fairly often and
      // we don't want it to flicker.
      JX.DOM.alterClass(document.body, 'jx-drag-col', true);

      track = [
        {
          element: local,
          parameter: 'width',
          start: JX.Vector.getDim(local).x,
          scale: 1,

          width: JX.Vector.getDim(local).x,
          minWidth: 1,
          minScale: 1
        },
        {
          element: drag,
          parameter: 'left',
          start: JX.$V(drag).x,
          scale: 1
        },
        {
          element: content,
          parameter: 'marginLeft',
          start: parseInt(getComputedStyle(content).marginLeft, 10),
          scale: 1,

          width: JX.Vector.getDim(content).x,
          minWidth: 300,
          minScale: -1
        }
      ];

      e.kill();
    });

    JX.Stratcom.listen('mousemove', null, function(e) {
      if (!dragging) {
        return;
      }

      var dx = JX.$V(e).x - dragging.x;
      var panel;

      for (var k = 0; k < track.length; k++) {
        panel = track[k];
        if (!panel.minWidth) {
          continue;
        }
        var new_width = panel.width + (dx * panel.minScale);
        if (new_width < panel.minWidth) {
          dx = (panel.minWidth - panel.width) * panel.minScale;
        }
      }

      for (var k = 0; k < track.length; k++) {
        panel = track[k];
        var v = (panel.start + (dx * panel.scale));
        panel.element.style[panel.parameter] = v + 'px';
      }
    });

    JX.Stratcom.listen('mouseup', null, function(e) {
      if (!dragging) {
        return;
      }
      JX.DOM.alterClass(document.body, 'jx-drag-col', false);
      dragging = false;
    });

    var collapsed = false;
    JX.Stratcom.listen('differential-filetree-toggle', null, function(e) {
      collapsed = !collapsed;
      JX.DOM.alterClass(main, 'local-nav-collapsed', collapsed);
    });
  }


// - Scroll --------------------------------------------------------------------

  // When the user scrolls down on the desktop, we move the application and
  // local navs up until they hit the top of the page.

  JX.Stratcom.listen(['scroll', 'resize'], null, function(e) {
    if (JX.Device.getDevice() != 'desktop') {
      return;
    }

    var y = Math.max(0, 44 - JX.Vector.getScroll().y);
    app.style.top = y + 'px';
    if (local) {
      local.style.top = y + 'px';
    }
  });


// - Navigation Reset ----------------------------------------------------------

  JX.Stratcom.listen('phabricator-device-change', null, function(event) {
    app.style.left = '';
    app.style.top = '';
    if (local) {
      local.style.left = '';
      local.style.width = '';
      local.style.top = '';
    }
    if (drag) {
      drag.style.left = '';
    }
    content.style.marginLeft = '';

    select_button(2);
  });

});
