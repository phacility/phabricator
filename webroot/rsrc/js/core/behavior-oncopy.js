/**
 * @provides javelin-behavior-phabricator-oncopy
 * @requires javelin-behavior
 *           javelin-dom
 */

/**
 * Tools like Paste and Differential don't normally respond to the clipboard
 * 'copy' operation well, because when a user copies text they'll get line
 * numbers and other metadata.
 *
 * To improve this behavior, applications can embed markers that delimit
 * metadata (left of the marker) from content (right of the marker). When
 * we get a copy event, we strip out all the metadata and just copy the
 * actual text.
 */
JX.behavior('phabricator-oncopy', function() {

  var zws = '\u200B'; // Unicode Zero-Width Space

  JX.enableDispatch(document.body, 'copy');
  JX.Stratcom.listen(
    ['copy'],
    null,
    function(e) {

      var selection;
      var text;
      if (window.getSelection) {
        selection = window.getSelection();
        text = selection.toString();
      } else {
        selection = document.selection;
        text = selection.createRange().text;
      }

      if (text.indexOf(zws) == -1) {
        // If there's no marker in the text, just let it copy normally.
        return;
      }

      var result = [];

      // Strip everything before the marker (and the marker itself) out of the
      // text. If a line doesn't have the marker, throw it away (the assumption
      // is that it's a line number or part of some other meta-text).
      var lines = text.split('\n');
      var pos;
      for (var ii = 0; ii < lines.length; ii++) {
        pos = lines[ii].indexOf(zws);
        if (pos == -1 && ii !== 0) {
          continue;
        }
        result.push(lines[ii].substring(pos + 1));
      }
      result = result.join('\n');

      var rawEvent = e.getRawEvent();
      var clipboardData = 'clipboardData' in rawEvent ?
        rawEvent.clipboardData :
        window.clipboardData;
      clipboardData.setData('Text', result);
      e.prevent();
    });
});
