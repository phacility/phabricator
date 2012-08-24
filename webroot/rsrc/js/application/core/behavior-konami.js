/**
 * @provides javelin-behavior-konami
 * @requires javelin-behavior
 *           javelin-stratcom
 */

JX.behavior('konami', function() {
  var sequence = [ 38, 38, 40, 40, 37, 39, 37, 39, 66, 65, 13 ];
  var seen = [];

  JX.Stratcom.listen('keyup', null, function(e) {
    if (!sequence)
      return;
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
  var body_rule, all_rule, top_rule;

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

  var characters = [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ];
  for (var i = 0x3041; i < 0x3097; ++i) {
    characters.push(String.fromCharCode(i));
  }

  function spawnText() {
    if (Math.random() > 0.10) {
      return;
    }

    var text = [];
    var length = parseInt(Math.random() * 16) + 10;
    for (var i = 0; i < length; ++i) {
      text.push(characters[parseInt(Math.random() * characters.length)]);
    }
    text = text.join(' ');

    var element = document.createElement('div');
    var z = Math.random() * 400 - 40;
    element.className = 'matrix';
    element.textContent = text;
    modifyCSS(element.style, 'left',
      Math.random() * document.body.clientWidth + 'px');
    modifyAllCSS(element.style, 'transform',
      'translateZ(' + z + 'px) rotateY(180deg)');
    document.body.appendChild(element);

    var height = element.clientHeight;
    var y = -height;
    modifyCSS(element.style, 'top', y + 'px');

    var timer = setInterval(function() {
      y += 5;
      modifyCSS(element.style, 'top', y + 'px');
      if (y > document.body.clientHeight) {
        clearInterval(timer);
        document.body.removeChild(element);
      }
    }, 20);
  }

  function spawnCat() {
    if (Math.random() > 0.05) {
      return;
    }

    var element = document.createElement('img');
    var z = Math.random() * 400 - 40;
    element.setAttribute('src', '/rsrc/image/nyan.gif');
    element.className = 'nyan';
    modifyCSS(element.style, 'top',
      Math.random() * document.body.clientHeight + 'px');
    modifyAllCSS(element.style, 'transform', 'translateZ(' + z + 'px)');
    document.body.appendChild(element);

    var width = Math.random() * 200 + 100;
    var x = -width;
    modifyCSS(element.style, 'width', width + 'px');
    modifyCSS(element.style, 'left', x + 'px');

    var timer = setInterval(function() {
      x += 3;
      modifyCSS(element.style, 'left', x + 'px');
      if (x > document.body.clientWidth) {
        clearInterval(timer);
        document.body.removeChild(element);
      }
    }, 20);
  }

  var counter = 0;
  var body_translate = '';
  var body_rotate = '';

  function zoomOut() {
    if (counter >= 20) {
      return;
    }
    ++counter;

    body_translate = 'translateZ(' + (-16 * counter) + 'px)';
    modifyAllCSS(body_rule, 'transform', body_translate + ' ' + body_rotate);
    modifyAllCSS(all_rule, 'transform', 'translateZ(' + counter + 'px)');
    modifyAllCSS(top_rule, 'transform', 'translateZ(' + (counter * 15) + 'px)');
    modifyCSS(document.documentElement.style, 'background-color',
      'rgba(0,0,0,' + (counter / 20) + ')', '');
    modifyCSS(document.getElementById('base-page').style, 'background-color',
      'rgba(255,255,255,' + (1 - counter / 20) + ')', '');
    setTimeout(zoomOut, 20 + counter * 3);
  }

  function activate() {
    var matrix = document.createElement('style');
    matrix.textContent = [
      generateAllCSS('html', {
        perspective: '500px'
      }),
      generateAllCSS('body', {
        transform: 'translateZ(0px)'
      }),
      generateAllCSS('*', {
        'transform-style': 'preserve-3d'
      }),
      generateAllCSS('body>[class|=jx],.phabricator-notification-menu', {
        transform: 'translateZ(0px)'
      }),
      generateCSS('.matrix', {
        position: 'fixed',
        width: '0',
        'font-size': '20pt',
        color: 'chartreuse',
        'text-shadow': '0 0 1em limegreen,0 0 1em limegreen,0 0 1em limegreen'
      }),
      generateCSS('.nyan', {
        position: 'fixed'
      })
    ].join('\n');
    document.head.appendChild(matrix);

    body_rule = matrix.sheet.cssRules[1].style;
    all_rule = matrix.sheet.cssRules[2].style;
    top_rule = matrix.sheet.cssRules[3].style;

    document.addEventListener('mousemove', function(e) {
      var x = e.screenX / window.innerWidth - 0.5;
      var y = -e.screenY / window.innerHeight + 0.5;
      body_rotate = 'rotateY(' + x + 'rad) rotateX(' + y + 'rad)';
      modifyAllCSS(body_rule, 'transform', body_translate + ' ' + body_rotate);
    }, false);

    zoomOut();
    setInterval(spawnText, 100);
    setInterval(spawnCat, 250);
  }
});
