/**
 * @requires javelin-install
 * @provides phabricator-textareautils
 * @javelin
 */

JX.install('TextAreaUtils', {
  statics : {
    getSelectionRange : function(area) {
      var v = area.value;

      // NOTE: This works well in Safari, Firefox and Chrome. We'll probably get
      // less-good behavior on IE.

      var s = v.length;
      var e = v.length;

      if ('selectionStart' in area) {
        s = area.selectionStart;
        e = area.selectionEnd;
      }

      return {start: s, end: e};
    },

    getSelectionText : function(area) {
      var v = area.value;
      var r = JX.TextAreaUtils.getSelectionRange(area);
      return v.substring(r.start, r.end);
    },

    setSelectionRange : function(area, start, end) {
      if ('setSelectionRange' in area) {
        area.focus();
        area.setSelectionRange(start, end);
      }
    },

    setSelectionText : function(area, text) {
      var v = area.value;
      var r = JX.TextAreaUtils.getSelectionRange(area);

      v = v.substring(0, r.start) + text + v.substring(r.end, v.length);
      area.value = v;

      JX.TextAreaUtils.setSelectionRange(area, r.start, r.start + text.length);
    }
  }
});
