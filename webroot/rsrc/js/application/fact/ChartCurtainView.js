/**
 * @provides javelin-chart-curtain-view
 */
JX.install('ChartCurtainView', {

  construct: function() {
    this._labels = [];
  },

  members: {
    _node: null,
    _labels: null,
    _labelsNode: null,

    getNode: function() {
      if (!this._node) {
        var attr = {
          className: 'chart-curtain'
        };

        this._node = JX.$N('div', attr);
      }
      return this._node;
    },

    reset: function() {
      this._labels = [];
    },

    addFunctionLabel: function(label) {
      this._labels.push(label);
      return this;
    },

    redraw: function() {
      var content = [this._getFunctionLabelsNode()];

      JX.DOM.setContent(this.getNode(), content);
      return this;
    },

    _getFunctionLabelsNode: function() {
      if (!this._labels.length) {
        return null;
      }

      if (!this._labelsNode) {
        var list_attrs = {
          className: 'chart-function-label-list'
        };

        var labels = JX.$N('ul', list_attrs);

        var items = [];
        for (var ii = 0; ii < this._labels.length; ii++) {
          items.push(this._newFunctionLabelItem(this._labels[ii]));
        }

        JX.DOM.setContent(labels, items);

        this._labelsNode = labels;
      }

      return this._labelsNode;
    },

    _newFunctionLabelItem: function(label) {
      var item_attrs = {
        className: 'chart-function-label-list-item'
      };

      var icon = new JX.PHUIXIconView()
        .setIcon(label.getIcon());

      // Charts may use custom colors, so we can't rely on the CSS classes
      // which only provide standard colors like "red" and "blue".
      icon.getNode().style.color = label.getColor();

      var content = [
        icon.getNode(),
        label.getName()
      ];

      return JX.$N('li', item_attrs, content);
    }

  }

});
