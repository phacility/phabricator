/**
 * @provides phuix-formation-flank-view
 * @requires javelin-install
 *           javelin-dom
 */

JX.install('PHUIXFormationFlankView', {

  construct: function(node, head, body, tail) {
    this._node = node;

    this._headNode = head;
    this._bodyNode = body;
    this._tailNode = tail;
  },

  properties: {
    isFixed: false,
    bannerHeight: null,
    width: null
  },

  members: {
    _node: null,
    _headNode: null,
    _bodyNode: null,
    _tailNode: null,

    getBodyNode: function() {
      return this._bodyNode;
    },

    getTailNode: function() {
      return this._tailNode;
    },

    repaint: function()  {
      if (!this.getIsFixed()) {
        return;
      }

      this._node.style.top = this.getBannerHeight() + 'px';
      this._node.style.width = this.getWidth() + 'px';

      var body = this.getBodyNode();
      var body_pos = JX.$V(body);

      var tail = this.getTailNode();
      var tail_pos = JX.$V(tail);

      var max_height = (tail_pos.y - body_pos.y);

      body.style.maxHeight = max_height + 'px';
    }
  }

});
