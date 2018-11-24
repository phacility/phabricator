/**
 * Javelin core; installs Javelin and Stratcom event delegation.
 *
 * @provides javelin-magical-init
 *
 * @javelin-installs JX.__rawEventQueue
 * @javelin-installs JX.__simulate
 * @javelin-installs JX.__allowedEvents
 * @javelin-installs JX.enableDispatch
 * @javelin-installs JX.onload
 * @javelin-installs JX.flushHoldingQueue
 *
 * @javelin
 */
(function() {

  if (window.JX) {
    return;
  }

  window.JX = {};

  // The holding queues hold calls to functions (JX.install() and JX.behavior())
  // before they load, so if you're async-loading them later in the document
  // the page will execute correctly regardless of the order resources arrive
  // in.

  var holding_queues = {};

  function makeHoldingQueue(name) {
    if (JX[name]) {
      return;
    }
    holding_queues[name] = [];
    JX[name] = function() { holding_queues[name].push(arguments); };
  }

  JX.flushHoldingQueue = function(name, fn) {
    for (var ii = 0; ii < holding_queues[name].length; ii++) {
      fn.apply(null, holding_queues[name][ii]);
    }
    holding_queues[name] = {};
  };

  makeHoldingQueue('install');
  makeHoldingQueue('behavior');
  makeHoldingQueue('install-init');

  var loaded = false;
  var onload = [];
  var master_event_queue = [];
  var root = document.documentElement;
  var has_add_event_listener = !!root.addEventListener;

  window.__DEV__ = !!root.getAttribute('data-developer-mode');

  JX.__rawEventQueue = function(what) {
    master_event_queue.push(what);

    var ii;
    var Stratcom = JX['Stratcom'];

    if (!loaded && what.type == 'domready') {
      var initializers = [];

      var tags = JX.DOM.scry(document.body, 'data');
      for (ii = 0; ii < tags.length; ii++) {

        // Ignore tags which are not immediate children of the document
        // body. If an attacker somehow injects arbitrary tags into the
        // content of the document, that should not give them access to
        // modify initialization behaviors.
        if (tags[ii].parentNode !== document.body) {
          continue;
        }

        var tag_kind = tags[ii].getAttribute('data-javelin-init-kind');
        var tag_data = tags[ii].getAttribute('data-javelin-init-data');
        tag_data = JX.JSON.parse(tag_data);

        initializers.push({kind: tag_kind, data: tag_data});
      }

      Stratcom.initialize(initializers);
      loaded = true;
    }

    if (loaded) {
      // Empty the queue now so that exceptions don't cause us to repeatedly
      // try to handle events.
      var local_queue = master_event_queue;
      master_event_queue = [];
      for (ii = 0; ii < local_queue.length; ++ii) {
        var evt = local_queue[ii];

        // Sometimes IE gives us events which throw when ".type" is accessed;
        // just ignore them since we can't meaningfully dispatch them. TODO:
        // figure out where these are coming from.
        try { var test = evt.type; } catch (x) { continue; }

        if (evt.type == 'domready') {
          // NOTE: Firefox interprets "document.body.id = null" as the string
          // literal "null".
          document.body && (document.body.id = '');
          for (var jj = 0; jj < onload.length; jj++) {
            onload[jj]();
          }
        }

        Stratcom.dispatch(evt);
      }
    } else {
      var target = what.srcElement || what.target;
      if (target &&
          (what.type in {click: 1, submit: 1}) &&
          target.getAttribute &&
          target.getAttribute('data-mustcapture') === '1') {
        what.returnValue = false;
        what.preventDefault && what.preventDefault();
        document.body.id = 'event_capture';

        // For versions of IE that use attachEvent, the event object is somehow
        // stored globally by reference, and all the references we push to the
        // master_event_queue will always refer to the most recent event. We
        // work around this by popping the useless global event off the queue,
        // and pushing a clone of the event that was just fired using the IE's
        // proprietary createEventObject function.
        // see: http://msdn.microsoft.com/en-us/library/ms536390(v=vs.85).aspx
        if (!add_event_listener && document.createEventObject) {
          master_event_queue.pop();
          master_event_queue.push(document.createEventObject(what));
        }

        return false;
      }
    }
  };

  JX.enableDispatch = function(target, type) {
    if (__DEV__) {
      JX.__allowedEvents[type] = true;
    }

    if (target.addEventListener) {
      target.addEventListener(type, JX.__rawEventQueue, true);
    } else if (target.attachEvent) {
      target.attachEvent('on' + type, JX.__rawEventQueue);
    }
  };

  var document_events = [
    'click',
    'dblclick',
    'change',
    'submit',
    'keypress',
    'mousedown',
    'mouseover',
    'mouseout',
    'keyup',
    'keydown',
    'input',
    'drop',
    'dragenter',
    'dragleave',
    'dragover',
    'paste',
    'touchstart',
    'touchmove',
    'touchend',
    'touchcancel',
    'load'
  ];

  //  Simulate focus and blur in old versions of IE using focusin and focusout
  //  TODO: Document the gigantic IE mess here with focus/blur.
  //  TODO: beforeactivate/beforedeactivate?
  //  http://www.quirksmode.org/blog/archives/2008/04/delegating_the.html
  if (!has_add_event_listener) {
    document_events.push('focusin', 'focusout');
  }

  //  Opera is multilol: it propagates focus / blur oddly
  if (window.opera) {
    document_events.push('focus', 'blur');
  }

  if (__DEV__) {
    JX.__allowedEvents = {};
    if ('onpagehide' in window) {
      JX.__allowedEvents.unload = true;
    }
  }

  var ii;
  for (ii = 0; ii < document_events.length; ++ii) {
    JX.enableDispatch(root, document_events[ii]);
  }

  // In particular, we're interested in capturing window focus/blur here so
  // long polls can abort when the window is not focused.
  var window_events = [
    ('onpagehide' in window) ? 'pagehide' : 'unload',
    'resize',
    'scroll',
    'focus',
    'blur',
    'popstate',
    'hashchange',

    // In Firefox, if the user clicks in the window then drags the cursor
    // outside of the window and releases the mouse button, we don't get this
    // event unless we listen for it as a window event.
    'mouseup'
  ];

  try {
    if (window.localStorage) {
      window_events.push('storage');
    }
  } catch (storage_exception) {
    // See PHI985. In Firefox, accessing "window.localStorage" may throw an
    // exception if cookies are disabled.
  }

  for (ii = 0; ii < window_events.length; ++ii) {
    JX.enableDispatch(window, window_events[ii]);
  }

  JX.__simulate = function(node, event) {
    if (!has_add_event_listener) {
      var e = {target: node, type: event};
      JX.__rawEventQueue(e);
      if (e.returnValue === false) {
        return false;
      }
    }
  };

  if (has_add_event_listener) {
    document.addEventListener('DOMContentLoaded', function() {
      JX.__rawEventQueue({type: 'domready'});
    }, true);
  } else {
    var ready =
      'if (this.readyState == "complete") {' +
        'JX.__rawEventQueue({type: "domready"});' +
      '}';

    // NOTE: Don't write a 'src' attribute, because "javascript:void(0)" causes
    // a mixed content warning in IE8 if the page is served over SSL.
    document.write(
      '<script' +
      ' defer="defer"' +
      ' onreadystatechange="' + ready + '"' +
      '><\/sc' + 'ript' + '>');
  }

  JX.onload = function(func) {
    if (loaded) {
      func();
    } else {
      onload.push(func);
    }
  };

})();
