/**
 * @provides javelin-chart-function-label
 */
JX.install('ChartFunctionLabel', {

  construct: function(spec) {
    this._name = spec.name;
    this._color = spec.color;
    this._icon = spec.icon;
    this._fillColor = spec.fillColor;
  },

  members: {
    _name: null,
    _color: null,
    _icon: null,
    _fillColor: null,

    getColor: function() {
      return this._color;
    },

    getName: function() {
      return this._name;
    },

    getIcon: function() {
      return this._icon || 'fa-circle';
    },

    getFillColor: function() {
      return this._fillColor;
    }
  }
});
