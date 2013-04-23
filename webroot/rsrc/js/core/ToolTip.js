/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-vector
 * @provides phabricator-tooltip
 * @javelin
 */

JX.install('Tooltip', {

  statics : {
    _node : null,

    show : function(root, scale, align, content) {
      if (__DEV__) {
        switch (align) {
          case 'N':
          case 'E':
          case 'S':
          case 'W':
            break;
          default:
            JX.$E(
              "Only alignments 'N' (north), 'E' (east), 'S' (south), " +
              "and 'W' (west) are supported."
            );
            break;
        }
      }

      var node = JX.$N(
        'div',
        { className: 'jx-tooltip-container jx-tooltip-align-' + align },
        [
          JX.$N('div', { className: 'jx-tooltip' }, content),
          JX.$N('div', { className: 'jx-tooltip-anchor' })
        ]);

      node.style.maxWidth  = scale + 'px';

      JX.Tooltip.hide();
      this._node = node;

      // Append the tip to the document, but offscreen, so we can measure it.
      node.style.left = '-10000px';
      document.body.appendChild(node);

      var p = JX.$V(root);
      var d = JX.Vector.getDim(root);
      var n = JX.Vector.getDim(node);

      // Move the tip so it's nicely aligned.

      switch (align) {
        case 'N':
          node.style.left = parseInt(p.x - ((n.x - d.x) / 2)) + 'px';
          node.style.top  = parseInt(p.y - n.y) + 'px';
          break;
        case 'E':
          node.style.left = parseInt(p.x + d.x) + 'px';
          node.style.top  = parseInt(p.y - ((n.y - d.y) / 2)) + 'px';
          break;
        case 'S':
          node.style.left = parseInt(p.x - ((n.x - d.x) / 2)) + 'px';
          node.style.top  = parseInt(p.y + d.y + 5) + 'px';
          break;
        case 'W':
          node.style.left = parseInt(p.x - n.x - 5) + 'px';
          node.style.top  = parseInt(p.y - ((n.y - d.y) / 2)) + 'px';
          break;
      }
    },

    hide : function() {
      if (this._node) {
        JX.DOM.remove(this._node);
        this._node = null;
      }
    }
  }
});
