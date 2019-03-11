/**
 * @provides javelin-workboard-order-template
 * @requires javelin-install
 * @javelin
 */

JX.install('WorkboardOrderTemplate', {

  construct: function(order) {
    this._orderKey = order;
  },

  properties: {
    hasHeaders: false,
    canReorder: false
  },

  members: {
    _orderKey: null,

    getOrderKey: function() {
      return this._orderKey;
    }

  }

});
