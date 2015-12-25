/**
 * @provides javelin-behavior-phabricator-trail
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-vector
 * @javelin
 */

JX.behavior('phabricator-trail', function() {
  var last = null;
  var trail = [];

  var n = 0;
  JX.Stratcom.listen('mousemove', null, function(e) {
    var v = JX.$V(e);

    if (!last) {
      last = v;
      return;
    }

    var dx = v.x - last.x;
    var dy = v.y - last.y;
    var spacing = 24;

    if ((dx * dx) + (dy * dy) < (spacing * spacing)) {
      // Mouse hasn't moved far enough, just bail.
      return;
    }

    var node;
    // If the trail is too long, throw away the end.
    while (trail.length > 8) {
      node = trail[0];
      JX.DOM.remove(node);
      trail.splice(0, 1);
    }

    var color;
    if (n % 2) {
      color = '#c0392b';
    } else {
      color = '#139543';
    }

    n++;

    var icon;
    if (Math.random() > 0.5) {
      icon = 'fa-star';
    } else {
      icon = 'fa-tree';
    }

    node = JX.$N(
      'span',
      {
        className: 'phui-icon-view phui-font-fa ph-bounceout ' + icon,
        style: {
          position: 'absolute',
          color: color,
          zIndex: 20151225
        }
      },
      null);

    var size = JX.Vector.getDim(node);

    last.x -= size.x / 2;
    last.y -= size.y / 2;

    last.setPos(node);

    trail.push(node);
    document.body.appendChild(node);

    last = v;
  });

});
