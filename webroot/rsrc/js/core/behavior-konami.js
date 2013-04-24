/**
 * @provides javelin-behavior-konami
 * @requires javelin-behavior
 *           javelin-stratcom
 */

JX.behavior('konami', function() {
  var sequence = [ 38, 38, 40, 40, 37, 39, 37, 39, 66, 65, 13 ];
  var seen = [];

  JX.Stratcom.listen('keyup', null, function(e) {
    if (!sequence) {
      return;
    }

    seen.push(e.getRawEvent().keyCode);

    while (seen.length) {
      var mismatch = false;
      for (var i = 0; i < seen.length; ++i) {
        if (seen[i] != sequence[i]) {
          mismatch = true;
          break;
        }
      }
      if (!mismatch) {
        break;
      }
      seen.shift();
    }

    if (seen.length == sequence.length) {
      sequence = seen = null;
      activate();
    }
  });

  var prefixes = { '-webkit-': 1, '-moz-': 1, '-o-': 1, '-ms-': 1, '': 1 };

  function generateCSS(selector, props) {
    var ret = selector + '{';
    for (var key in props) {
      ret += key + ':' + props[key] + ';';
    }
    return ret + '}';
  }

  function generateAllCSS(selector, props) {
    var more_props = {};
    for (var key in props) {
      for (var prefix in prefixes) {
        more_props[prefix + key] = props[key];
      }
    }
    return generateCSS(selector, more_props);
  }

  function modifyCSS(rule, key, value) {
    rule.setProperty(key, value, '');
  }

  function modifyAllCSS(rule, key, value) {
    for (var prefix in prefixes) {
      modifyCSS(rule, prefix + key, value);
    }
  }

  var top_rule;

  function activate() {
    var matrix = document.createElement('style');
    matrix.textContent = [
      generateAllCSS('html', {
        background: '#000'
      }),
      generateAllCSS('body', {
        perspective: '2048px',
        background: 'transparent'
      }),
      generateAllCSS('*', {
        'transform-style': 'preserve-3d'
      }),
      generateAllCSS('body > *', {
      })
    ].join('\n');
    document.head.appendChild(matrix);

    top_rule = matrix.sheet.cssRules[3].style;

    var first_event = null;
    document.body.addEventListener('mousemove', function(e) {
      if (!first_event) {
        first_event = {x: e.screenX, y: e.screenY};
      }
      var dx = (e.screenX - first_event.x);
      var dy = (e.screenY - first_event.y);

      var x = -(dx / window.innerWidth) * (Math.PI / 2);
      var y = (dy / window.innerHeight) * (Math.PI / 2);
      var body_rotate = 'rotateY(' + x + 'rad) rotateX(' + y + 'rad)';
      modifyAllCSS(top_rule, 'transform', body_rotate);
    }, false);
  }
});
