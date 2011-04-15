/**
 * @provides javelin-behavior-differential-edit-inline-comments
 * @requires javelin-lib-dev
 */

JX.behavior('differential-edit-inline-comments', function(config) {

  var selecting = false;
  var reticle = JX.$N('div', {className: 'differential-reticle'});
  JX.DOM.hide(reticle);
  document.body.appendChild(reticle);

  var origin = null;
  var target = null;
  var root   = null;
  var changeset = null;
  var workflow = false;
  var is_new = false;

  function updateReticle() {
    var top = origin;
    var bot = target;
    if (JX.$V(top).y > JX.$V(bot).y) {
      var tmp = top;
      top = bot;
      bot = tmp;
    }
    var code = target.nextSibling;

    var pos = JX.$V(top).add(1 + JX.$V.getDim(target).x, 0);
    var dim = JX.$V.getDim(code).add(-4, 0);
    dim.y = (JX.$V(bot).y - pos.y) + JX.$V.getDim(bot).y;

    pos.setPos(reticle);
    dim.setDim(reticle);

    JX.DOM.show(reticle);
  }

  function hideReticle() {
    JX.DOM.hide(reticle);
  }

  function finishSelect() {
    selecting = false;
    workflow = false;
    hideReticle();
  }

  function drawInlineComment(table, anchor, r) {
    copyRows(table, JX.$N('div', JX.HTML(r.markup)), anchor);
    finishSelect();
  }

  function isOnRight(node) {
    return node.parentNode.firstChild != node;
  }

  function isNewFile(node) {
    var data = JX.Stratcom.getData(root);
    return isOnRight(node) || (data.left != data.right);
  }

  function getRowNumber(th_node) {
    try {
      return parseInt(th_node.id.match(/^C\d+[ON]L(\d+)$/)[1], 10);
    } catch (x) {
      return undefined;
    }
  }

  function isInlineCommentNode(target) {
    return target &&
            (!JX.DOM.isType(target, 'tr')
             || target.className.indexOf('inline') !== -1);

  }

  function findInlineCommentTarget(target) {
    while (isInlineCommentNode(target)) {
      target = target.nextSibling;
    }
    return target;
  }

  JX.Stratcom.listen(
    'mousedown',
    ['differential-changeset', 'tag:th'],
    function(e) {
      if (workflow  ||
          selecting ||
          getRowNumber(e.getTarget()) === undefined) {
        return;
      }

      selecting = true;
      root = e.getNode('differential-changeset');

      origin = target = e.getTarget();

      var data = e.getNodeData('differential-changeset');
      if (isOnRight(target)) {
        changeset = data.left;
      } else {
        changeset = data.right;
      }

      updateReticle();

      e.kill();
    });

  JX.Stratcom.listen(
    'mouseover',
    ['differential-changeset', 'tag:th'],
    function(e) {
      if (!selecting ||
          workflow ||
          (getRowNumber(e.getTarget()) === undefined) ||
          (isOnRight(e.getTarget()) != isOnRight(origin)) ||
          (e.getNode('differential-changeset') !== root)) {
        return;
      }

      target = e.getTarget();

      updateReticle();
    });

  JX.Stratcom.listen(
    'mouseup',
    null,
    function(e) {
      if (workflow || !selecting) {
        return;
      }

      var o = getRowNumber(origin);
      var t = getRowNumber(target);

      var insert;
      var len;
      if (t < o) {
        len = (o - t);
        o = t;
        insert = origin.parentNode;
      } else {
        len = (t - o);
        insert = target.parentNode;
      }

      var data = {
        op: 'new',
        changeset: changeset,
        number: o,
        length: len,
        is_new: isNewFile(target) ? 1 : 0,
        on_right: isOnRight(target) ? 1 : 0
      };

      workflow = true;

      var w = new JX.Workflow(config.uri, data)
        .setHandler(function(r) {
          // Skip over any rows which contain inline feedback. Don't mimic this!
          // We're shipping around raw HTML here for performance reasons, but
          // normally you should use sigils to encode this kind of data on
          // the document.
          var target = findInlineCommentTarget(insert.nextSibling);
          drawInlineComment(insert.parentNode, target, r);
          finishSelect();
          JX.Stratcom.invoke('differential-inline-comment-update');
        })
        .setCloseHandler(finishSelect);


      w.listen('error', function(e) {
        // TODO: uh, tell the user I guess
        finishSelect();
        JX.Stratcom.context().stop();
      });

      w.start();

      e.kill();
    });

  JX.Stratcom.listen(
    ['mouseover', 'mouseout'],
    'differential-inline-comment',
    function(e) {
      if (selecting || workflow) {
        return;
      }

      if (e.getType() == 'mouseout') {
        hideReticle();
      } else {
        root = e.getNode('differential-changeset');

        var data = e.getNodeData('differential-inline-comment');
        var change = e.getNodeData('differential-changeset');

        var prefix;
        if (data.on_right) {
          prefix = 'C' + (change.left) + 'NL';
        } else {
          prefix = 'C' + (change.right) + 'OL';
        }

        origin = JX.$(prefix + data.number);
        target = JX.$(prefix + (parseInt(data.number, 10) +
                                parseInt(data.length, 10)));

        updateReticle();
      }
    });

  JX.Stratcom.listen(
    'click',
    [['differential-inline-comment', 'differential-inline-reply']],
    function(e) {
      new JX.Workflow(config.uri, e.getNodeData('differential-inline-reply'))
        .setHandler(function(r) {
          var base_row =
            findInlineCommentTarget(
              e.getNode('differential-inline-comment')
               .parentNode
               .parentNode
            );
          drawInlineComment(base_row.parentNode, base_row, r);
          JX.Stratcom.invoke('differential-inline-comment-update');
        })
        .start();

      e.kill();
    }
  );

  JX.Stratcom.listen(
    'click',
    [['differential-inline-comment', 'differential-inline-delete'],
     ['differential-inline-comment', 'differential-inline-edit']],
    function(e) {
      var data = {
        op: e.getNode('differential-inline-edit') ? 'edit' : 'delete',
        id: e.getNodeData('differential-inline-comment').id,
        on_right: e.getNodeData('differential-inline-comment').on_right,
      };
      new JX.Workflow(config.uri, data)
        .setHandler(function(r) {
          var base_row = e.getNode('differential-inline-comment')
            .parentNode
            .parentNode;
          if (data.op == 'edit' && r.markup) {
            drawInlineComment(base_row.parentNode, base_row, r);
          }
          JX.DOM.remove(base_row);
          JX.Stratcom.invoke('differential-inline-comment-update');
        })
        .start();
      e.kill();
    });

});
