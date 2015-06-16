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
    n : null,
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
          highlighted && JX.DOM.alterClass(highlighted, classHighlight, false);
          highlighted = null;
          return;
        }
        if (!isSignalkey(e)) {
          return;
        }
        if (e.getType() === 'mouseover') {
          var target = e.getTarget();
          while (target !== document.body) {
            if (JX.DOM.isNode(target, 'span') &&
               (target.className in class_map)) {
              highlighted = target;
              JX.DOM.alterClass(highlighted, classHighlight, true);
              break;
            }
            target = target.parentNode;
          }
        } else if (e.getType() === 'click') {
          openSearch(highlighted, lang);
        }
      });
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
      statics.active = (e.getType() === 'keydown');
      linked.forEach(function(element) {
        JX.DOM.alterClass(element, classMouseCursor, statics.active);
      });

      if (!statics.active) {
        highlighted && JX.DOM.alterClass(highlighted, classHighlight, false);
        highlighted = null;
      }
    });
});
