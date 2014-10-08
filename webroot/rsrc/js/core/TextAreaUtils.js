/**
 * @requires javelin-install
 *           javelin-dom
 *           javelin-vector
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
    },

    /**
     * Get the document pixel positions of the beginning and end of a character
     * range in a textarea.
     */
    getPixelDimensions: function(area, start, end) {
      var v = area.value;

      // We're using zero-width spaces to make sure the spans get some
      // height even if there's no text in the metrics tag.

      var head = v.substring(0, start);
      var before = JX.$N('span', {}, '\u200b');
      var body = v.substring(start, end);
      var after = JX.$N('span', {}, '\u200b');

      // Create a similar shadow element which we can measure.
      var metrics = JX.$N(
        'var',
        {
          className: area.className,
        },
        [head, before, body, after]);

      // If the textarea has a scrollbar, force a scrollbar on the shadow
      // element too.
      if (area.scrollHeight > area.clientHeight) {
        metrics.style.overflowY = 'scroll';
      }

      area.parentNode.appendChild(metrics);

      // Adjust the positions we read out of the document to account for the
      // current scroll position of the textarea.
      var metrics_pos = JX.Vector.getPos(metrics);
      metrics_pos.x += area.scrollLeft;
      metrics_pos.y += area.scrollTop;

      var area_pos = JX.Vector.getPos(area);
      var before_pos = JX.Vector.getPos(before);
      var after_pos = JX.Vector.getPos(after);

      JX.DOM.remove(metrics);

      return {
        start: {
          x: area_pos.x + (before_pos.x - metrics_pos.x),
          y: area_pos.y + (before_pos.y - metrics_pos.y)
        },
        end: {
          x: area_pos.x + (after_pos.x - metrics_pos.x),
          y: area_pos.y + (after_pos.y - metrics_pos.y)
        }
      };
    }

  }
});
