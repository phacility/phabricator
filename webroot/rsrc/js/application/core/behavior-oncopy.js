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

  var zws = "\u200B"; // Unicode Zero-Width Space

  document.body.oncopy = function(e) {

    var selection = window.getSelection();
    var text = selection.toString();

    if (text.indexOf(zws) == -1) {
      // If there's no marker in the text, just let it copy normally.
      return;
    }

    var result = [];

    // Strip everything before the marker (and the marker itself) out of the
    // text. If a line doesn't have the marker, throw it away (the assumption
    // is that it's a line number or part of some other meta-text).
    var lines = text.split("\n");
    var pos;
    for (var ii = 0; ii < lines.length; ii++) {
      pos = lines[ii].indexOf(zws);
      if (pos == -1 && ii != 0) {
        continue;
      }
      result.push(lines[ii].substring(pos + 1));
    }
    result = result.join("\n");

    if (e.clipboardData) {
      // Safari and Chrome support this easy, straightforward mechanism.
      e.clipboardData.setData('Text', result);
      e.preventDefault();
    } else {

      // In Firefox, we have to create a <pre> and select the text in it, then
      // let the copy event fire. It has to be a <pre> because Firefox won't
      // copy returns properly out of a div, even if it has 'whitespace: pre'.
      // There's been a bug open for 10 (!) years:
      //
      //   https://bugzilla.mozilla.org/show_bug.cgi?id=116083

      var style = {
        position: 'absolute',
        left:     '-10000px'
      };
      var pre = JX.$N('pre', {style: style}, result);
      document.body.appendChild(pre);

      // Select the text in the <pre>.
      var range = document.createRange();
      range.selectNodeContents(pre);
      selection.removeAllRanges();
      selection.addRange(range);

      setTimeout(function() { JX.DOM.remove(pre); }, 0);

      // TODO: I tried to restore the old selection range but it doesn't seem
      // to work or give me any errors. So you lose your selection when you
      // copy. Oh well?
    }
  }
});
