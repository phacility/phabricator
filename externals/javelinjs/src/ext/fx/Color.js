/**
 * @provides javelin-color
 * @requires javelin-install
 * @javelin
 */

JX.install('Color', {

  statics: {

    rgbRegex: new RegExp('([\\d]{1,3})', 'g'),

    rgbToHex: function(str, as_array) {
      var rgb = str.match(JX.Color.rgbRegex);
      var hex = [0, 1, 2].map(function(index) {
        return ('0' + (rgb[index] - 0).toString(16)).substr(-2, 2);
      });
      return as_array ? hex : '#' + hex.join('');
    },

    hexRegex: new RegExp('^[#]{0,1}([\\w]{1,2})([\\w]{1,2})([\\w]{1,2})$'),

    hexToRgb: function(str, as_array) {
      var hex = str.match(JX.Color.hexRegex);
      var rgb = hex.slice(1).map(function(bit) {
        return parseInt(bit.length == 1 ? bit + bit : bit, 16);
      });
      return as_array ? rgb : 'rgb(' + rgb + ')';
    }

  }

});
