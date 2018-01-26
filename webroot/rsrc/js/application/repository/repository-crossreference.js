/**
 * @provides javelin-behavior-repository-crossreference
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-uri
 */

JX.behavior('repository-crossreference', function(config, statics) {

  var highlighted;
  var linked = [];

  var isMac = navigator.platform.indexOf('Mac') > -1;
  var signalKey = isMac ? 91 /*COMMAND*/ : 17 /*CTRL*/;
  function isSignalkey(event) {
    return isMac ?
      event.getRawEvent().metaKey :
      event.getRawEvent().ctrlKey;
  }

  var classHighlight = 'crossreference-item';
  var classMouseCursor = 'crossreference-cursor';

  // TODO maybe move the dictionary part of this list to the server?
  var class_map = {
    nc : 'class',
    nf : 'function',
    na : null,
    nb : 'builtin',
    n : null
  };

  function link(element, lang) {
    JX.DOM.alterClass(element, 'repository-crossreference', true);
    linked.push(element);
    JX.DOM.listen(
      element,
      ['mouseover', 'mouseout', 'click'],
      'tag:span',
      function(e) {
        if (e.getType() === 'mouseout') {
          unhighlight();
          return;
        }
        if (!isSignalkey(e)) {
          return;
        }

        var target = e.getTarget();

        try {
          // If we're in an inline comment, don't link symbols.
          if (JX.DOM.findAbove(target, 'div', 'differential-inline-comment')) {
            return;
          }
        } catch (ex) {
          // Continue if we're not inside an inline comment.
        }

        // If only part of the symbol was edited, the symbol name itself will
        // have another "<span />" inside of it which highlights only the
        // edited part. Skip over it.
        if (JX.DOM.isNode(target, 'span') && (target.className === 'bright')) {
          target = target.parentNode;
        }

        if (e.getType() === 'mouseover') {
          while (target && target !== document.body) {
            if (JX.DOM.isNode(target, 'span') &&
               (target.className in class_map)) {
              highlighted = target;
              JX.DOM.alterClass(highlighted, classHighlight, true);
              break;
            }
            target = target.parentNode;
          }
        } else if (e.getType() === 'click') {
          openSearch(target, lang);
        }
      });
  }
  function unhighlight() {
    highlighted && JX.DOM.alterClass(highlighted, classHighlight, false);
    highlighted = null;
  }

  function openSearch(target, lang) {
    var symbol = target.textContent || target.innerText;
    var query = {
      lang : lang,
      repositories : config.repositories.join(','),
      jump : true
    };
    var c = target.className;
    c = c.replace(classHighlight, '').trim();

    if (class_map[c]) {
      query.type = class_map[c];
    }

    if (target.hasAttribute('data-symbol-context')) {
      query.context = target.getAttribute('data-symbol-context');
    }

    if (target.hasAttribute('data-symbol-name')) {
      symbol = target.getAttribute('data-symbol-name');
    }

    var line = getLineNumber(target);
    if (line !== null) {
      query.line = line;
    }

    var path = getPath(target);
    if (path !== null) {
      query.path = path;
    }

    var char = getChar(target);
    if (char !== null) {
      query.char = char;
    }

    var uri = JX.$U('/diffusion/symbol/' + symbol + '/');
    uri.addQueryParams(query);
    window.open(uri);
  }

  function linkAll() {
    var blocks = JX.DOM.scry(document.body, 'div', 'remarkup-code-block');
    for (var i = 0; i < blocks.length; ++i) {
      if (blocks[i].hasAttribute('data-code-lang')) {
        var lang = blocks[i].getAttribute('data-code-lang');
        link(blocks[i], lang);
      }
    }
  }

  function getLineNumber(target) {

    // Figure out the line number by finding the most recent "<th />" in this
    // row with a number in it. We may need to skip over one "<th />" if the
    // diff is being displayed in unified mode.

    var cell = JX.DOM.findAbove(target, 'td');
    if (!cell) {
      return null;
    }

    var row = JX.DOM.findAbove(target, 'tr');
    if (!row) {
      return null;
    }

    var ii;

    var cell_list = [];
    for (ii = 0; ii < row.childNodes.length; ii++) {
      cell_list.push(row.childNodes[ii]);
    }
    cell_list.reverse();

    var found = false;
    for (ii = 0; ii < cell_list.length; ii++) {
      if (cell_list[ii] === cell) {
        found = true;
      }

      if (found && JX.DOM.isType(cell_list[ii], 'th')) {
        var int_value = parseInt(cell_list[ii].textContent, 10);
        if (int_value) {
          return int_value;
        }
      }
    }

    return null;
  }

  function getPath(target) {
    // This method works in Differential, when browsing a changset.
    var changeset;
    try {
      changeset = JX.DOM.findAbove(target, 'div', 'differential-changeset');
      return JX.Stratcom.getData(changeset).path;
    } catch (ex) {
      // Ignore.
    }

    // This method works in Diffusion, when viewing the content of a file at
    // a particular commit.
    var file;
    try {
      file = JX.DOM.findAbove(target, 'div', 'diffusion-file-content-view');
      return JX.Stratcom.getData(file).path;
    } catch (ex) {
      // Ignore.
    }

    return null;
  }

  function getChar(target) {
    var cell = JX.DOM.findAbove(target, 'td');
    if (!cell) {
      return null;
    }

    var char = 1;
    for (var ii = 0; ii < cell.childNodes.length; ii++) {
      var node = cell.childNodes[ii];

      if (node === target) {
        return char;
      }

      var content = '' + node.textContent;

      // Strip off any ZWS characters. These are marker characters used to
      // improve copy/paste behavior.
      content = content.replace(/\u200B/g, '');

      char += content.length;
    }

    return null;
  }

  if (config.container) {
    link(JX.$(config.container), config.lang);
  } else if (config.section) {
    linkAll(JX.$(config.section));
  }

  JX.Stratcom.listen(
    'differential-preview-update',
    null,
    function(e) {
      linkAll(e.getData().container);
    });


  JX.Stratcom.listen(
    ['keydown', 'keyup'],
    null,
    function(e) {
      if (e.getRawEvent().keyCode !== signalKey) {
        return;
      }
      setCursorMode(e.getType() === 'keydown');

      if (!statics.active) {
        unhighlight();
      }
    });

  JX.Stratcom.listen(
    'blur',
    null,
    function(e) {
      if (e.getTarget()) {
        return;
      }
      unhighlight();
      setCursorMode(false);
    });

  function setCursorMode(active) {
    statics.active = active;
    linked.forEach(function(element) {
      JX.DOM.alterClass(element, classMouseCursor, statics.active);
    });
  }
});
