/**
 * @provides javelin-behavior-harbormaster-log
 * @requires javelin-behavior
 */

JX.behavior('harbormaster-log', function(config) {
  var contentNode = JX.$(config.contentNodeID);

  var following = false;
  var autoscroll = false;

  JX.DOM.listen(contentNode, 'click', 'harbormaster-log-expand', function(e) {
    if (!e.isNormalClick()) {
      return;
    }

    e.kill();

    expand(e.getNode('harbormaster-log-expand'), true);
  });

  function expand(node, is_action) {
    var row = JX.DOM.findAbove(node, 'tr');
    row = JX.DOM.findAbove(row, 'tr');

    var data = JX.Stratcom.getData(node);

    if (data.stop) {
      following = false;
      autoscroll = false;
      JX.DOM.alterClass(contentNode, 'harbormaster-log-following', false);
      return;
    }

    var uri = new JX.URI(config.renderURI)
      .addQueryParams(data);

    if (data.live && is_action) {
      following = true;
      autoscroll = true;
      JX.DOM.alterClass(contentNode, 'harbormaster-log-following', true);
    }

    var request = new JX.Request(uri, function(r) {
      var result = JX.$H(r.markup).getNode();
      var rows = [].slice.apply(result.firstChild.childNodes);

      // If we're following the bottom of the log, the result always includes
      // the last line from the previous render. Throw it away, then add the
      // new data.
      if (data.live && row.previousSibling) {
        JX.DOM.remove(row.previousSibling);
      }

      JX.DOM.replace(row, rows);

      if (data.live) {
        // If this was a live follow, scroll the new data into view. This is
        // probably intensely annoying in practice but seems cool for now.
        if (autoscroll) {
          var last_row = rows[rows.length - 1];
          var tail_pos = JX.$V(last_row).y + JX.Vector.getDim(last_row).y;
          var view_y = JX.Vector.getViewport().y;
          JX.DOM.scrollToPosition(null, (tail_pos - view_y) + 32);

          // This will fire a scroll event, but we want to keep autoscroll
          // enabled until we see an explicit scroll event by the user.
          setTimeout(function() { autoscroll = true; }, 0);
        }

        setTimeout(follow, 2000);

        for (var ii = 1; ii < (rows.length - 1); ii++) {
          JX.DOM.alterClass(rows[ii], 'harbormaster-log-appear', true);
        }
      }
    });

    request.send();
  }

  // If the user explicitly scrolls while following a log, keep live updating
  // it but stop following it with the scrollbar.
  JX.Stratcom.listen('scroll', null, function() {
    autoscroll = false;
  });

  function follow() {
    if (!following) {
      return;
    }

    var live;
    try {
      live = JX.DOM.find(contentNode, 'a', 'harbormaster-log-live');
    } catch (e) {
      return;
    }

    expand(live);
  }

  function onresponse(r) {
    JX.DOM.alterClass(contentNode, 'harbormaster-log-view-loading', false);

    JX.DOM.setContent(contentNode, JX.$H(r.markup));
  }

  var uri = new JX.URI(config.initialURI);

  new JX.Request(uri, onresponse)
    .send();

});
