/**
 * Javelin core; installs Javelin and Stratcom event delegation.
 *
 * @provides javelin-magical-init
 * @nopackage
 *
 * @javelin-installs JX.__rawEventQueue
 * @javelin-installs JX.__simulate
 * @javelin-installs JX.enableDispatch
 * @javelin-installs JX.onload
 *
 * @javelin
 */
(function() {


  if (window.JX) {
    return;
  }

  window.JX = {};
  window['__DEV__'] = window['__DEV__'] || 0;

  var loaded = false;
  var onload = [];
  var master_event_queue = [];
  var root = document.documentElement;
  var has_add_event_listener = !!root.addEventListener;

  JX.__rawEventQueue = function(what) {
    master_event_queue.push(what);


    // Evade static analysis - JX.Stratcom
    var Stratcom = JX['Stratcom'];
    if (Stratcom && Stratcom.ready) {
      //  Empty the queue now so that exceptions don't cause us to repeatedly
      //  try to handle events.
      var local_queue = master_event_queue;
      master_event_queue = [];
      for (var ii = 0; ii < local_queue.length; ++ii) {
        var evt = local_queue[ii];

        //  Sometimes IE gives us events which throw when ".type" is accessed;
        //  just ignore them since we can't meaningfully dispatch them. TODO:
        //  figure out where these are coming from.
        try { var test = evt.type; } catch (x) { continue; }

        if (!loaded && evt.type == 'domready') {
          document.body && (document.body.id = null);
          loaded = true;

          for (var ii = 0; ii < onload.length; ii++) {
            onload[ii]();
          }

        }

        Stratcom.dispatch(evt);
      }
    } else {
      var target = what.srcElement || what.target;
      if (target &&
          (what.type in {click: 1, submit: 1}) &&
          (/ FI_CAPTURE /).test(' ' + target.className + ' ')) {
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
  }

  JX.enableDispatch = function(target, type) {
    if (target.addEventListener) {
      target.addEventListener(type, JX.__rawEventQueue, true);
    } else if (target.attachEvent) {
      target.attachEvent('on' + type, JX.__rawEventQueue);
    }
  };

  var document_events = [
    'click',
    'change',
    'keypress',
    'mousedown',
    'mouseover',
    'mouseout',
    'mouseup',
    'keydown',
    'drop',
    'dragenter',
    'dragleave',
    'dragover'
  ];

  //  Simulate focus and blur in old versions of IE using focusin and focusout
  //  TODO: Document the gigantic IE mess here with focus/blur.
  //  TODO: beforeactivate/beforedeactivate?
  //  http://www.quirksmode.org/blog/archives/2008/04/delegating_the.html
  if (!has_add_event_listener) {
    document_events.push('focusin', 'focusout');
  }

  //  Opera is multilol: it propagates focus / blur odd, and submit differently
  if (window.opera) {
    document_events.push('focus', 'blur');
  } else {
    document_events.push('submit');
  }

  for (var ii = 0; ii < document_events.length; ++ii) {
    JX.enableDispatch(root, document_events[ii]);
  }

  //  In particular, we're interested in capturing window focus/blur here so
  //  long polls can abort when the window is not focused.
  var window_events = [
    ('onpagehide' in window) ? 'pagehide' : 'unload',
    'resize',
    'focus',
    'blur'
  ];

  for (var ii = 0; ii < window_events.length; ++ii) {
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
      "if (this.readyState == 'complete') {" +
        "JX.__rawEventQueue({type: 'domready'});" +
      "}";

    document.write(
      '<script' +
      ' defer="defer"' +
      ' src="javascript:void(0)"' +
      ' onreadystatechange="' + ready + '"' +
      '><\/sc' + 'ript\>');
  }

  JX.onload = function(func) {
    if (loaded) {
      func();
    } else {
      onload.push(func);
    }
  }


})();
