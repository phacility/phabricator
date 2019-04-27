/**
 * Javelin utility functions.
 *
 * @provides javelin-util
 *
 * @javelin-installs JX.$E
 * @javelin-installs JX.$A
 * @javelin-installs JX.$AX
 * @javelin-installs JX.isArray
 * @javelin-installs JX.copy
 * @javelin-installs JX.bind
 * @javelin-installs JX.bag
 * @javelin-installs JX.keys
 * @javelin-installs JX.log
 * @javelin-installs JX.id
 * @javelin-installs JX.now
 *
 * @javelin
 */

/**
 * Throw an exception and attach the caller data in the exception.
 *
 * @param  string  Exception message.
 */
JX.$E = function(message) {
  var e = new Error(message);
  var caller_fn = JX.$E.caller;
  if (caller_fn) {
    e.caller_fn = caller_fn.caller;
  }
  throw e;
};


/**
 * Convert an array-like object (usually ##arguments##) into a real Array. An
 * "array-like object" is something with a ##length## property and numerical
 * keys. The most common use for this is to let you call Array functions on the
 * magical ##arguments## object.
 *
 *   JX.$A(arguments).slice(1);
 *
 * @param  obj     Array, or array-like object.
 * @return Array   Actual array.
 */
JX.$A = function(object) {
  // IE8 throws "JScript object expected" when trying to call
  // Array.prototype.slice on a NodeList, so just copy items one by one here.
  var r = [];
  for (var ii = 0; ii < object.length; ii++) {
    r.push(object[ii]);
  }
  return r;
};


/**
 * Cast a value into an array, by wrapping scalars into singletons. If the
 * argument is an array, it is returned unmodified. If it is a scalar, an array
 * with a single element is returned. For example:
 *
 *   JX.$AX([3]); // Returns [3].
 *   JX.$AX(3);   // Returns [3].
 *
 * Note that this function uses a @{function:JX.isArray} check whether or not
 * the argument is an array, so you may need to convert array-like objects (such
 * as ##arguments##) into real arrays with @{function:JX.$A}.
 *
 * This function is mostly useful to create methods which accept either a
 * value or a list of values.
 *
 * @param  wild    Scalar or Array.
 * @return Array   If the argument was a scalar, an Array with the argument as
 *                 its only element. Otherwise, the original Array.
 */
JX.$AX = function(maybe_scalar) {
  return JX.isArray(maybe_scalar) ? maybe_scalar : [maybe_scalar];
};


/**
 * Checks whether a value is an array.
 *
 *   JX.isArray(['an', 'array']); // Returns true.
 *   JX.isArray('Not an Array');  // Returns false.
 *
 * @param  wild     Any value.
 * @return bool     true if the argument is an array, false otherwise.
 */
JX.isArray = Array.isArray || function(maybe_array) {
  return Object.prototype.toString.call(maybe_array) == '[object Array]';
};


/**
 * Copy properties from one object to another. If properties already exist, they
 * are overwritten.
 *
 *   var cat  = {
 *     ears: 'clean',
 *     paws: 'clean',
 *     nose: 'DIRTY OH NOES'
 *   };
 *   var more = {
 *     nose: 'clean',
 *     tail: 'clean'
 *   };
 *
 *   JX.copy(cat, more);
 *
 *   // cat is now:
 *   //  {
 *   //    ears: 'clean',
 *   //    paws: 'clean',
 *   //    nose: 'clean',
 *   //    tail: 'clean'
 *   //  }
 *
 * NOTE: This function does not copy the ##toString## property or anything else
 * which isn't enumerable or is somehow magic or just doesn't work. But it's
 * usually what you want.
 *
 * @param  obj Destination object, which properties should be copied to.
 * @param  obj Source object, which properties should be copied from.
 * @return obj Modified destination object.
 */
JX.copy = function(copy_dst, copy_src) {
  for (var k in copy_src) {
    copy_dst[k] = copy_src[k];
  }
  return copy_dst;
};


/**
 * Create a function which invokes another function with a bound context and
 * arguments (i.e., partial function application) when called; king of all
 * functions.
 *
 * Bind performs context binding (letting you select what the value of ##this##
 * will be when a function is invoked) and partial function application (letting
 * you create some function which calls another one with bound arguments).
 *
 * = Context Binding =
 *
 * Normally, when you call ##obj.method()##, the magic ##this## object will be
 * the ##obj## you invoked the method from. This can be undesirable when you
 * need to pass a callback to another function. For instance:
 *
 *   COUNTEREXAMPLE
 *   var dog = new JX.Dog();
 *   dog.barkNow(); // Makes the dog bark.
 *
 *   JX.Stratcom.listen('click', 'bark', dog.barkNow); // Does not work!
 *
 * This doesn't work because ##this## is ##window## when the function is
 * later invoked; @{method:JX.Stratcom.listen} does not know about the context
 * object ##dog##. The solution is to pass a function with a bound context
 * object:
 *
 *   var dog = new JX.Dog();
 *   var bound_function = JX.bind(dog, dog.barkNow);
 *
 *   JX.Stratcom.listen('click', 'bark', bound_function);
 *
 * ##bound_function## is a function with ##dog## bound as ##this##; ##this##
 * will always be ##dog## when the function is called, no matter what
 * property chain it is invoked from.
 *
 * You can also pass ##null## as the context argument to implicitly bind
 * ##window##.
 *
 * = Partial Function Application =
 *
 * @{function:JX.bind} also performs partial function application, which allows
 * you to bind one or more arguments to a function. For instance, if we have a
 * simple function which adds two numbers:
 *
 *   function add(a, b) { return a + b; }
 *   add(3, 4); // 7
 *
 * Suppose we want a new function, like this:
 *
 *   function add3(b) { return 3 + b; }
 *   add3(4); // 7
 *
 * Instead of doing this, we can define ##add3()## in terms of ##add()## by
 * binding the value ##3## to the ##a## argument:
 *
 *   var add3_bound = JX.bind(null, add, 3);
 *   add3_bound(4); // 7
 *
 * Zero or more arguments may be bound in this way. This is particularly useful
 * when using closures in a loop:
 *
 *   COUNTEREXAMPLE
 *   for (var ii = 0; ii < button_list.length; ii++) {
 *     button_list[ii].onclick = function() {
 *       JX.log('You clicked button number '+ii+'!'); // Fails!
 *     };
 *   }
 *
 * This doesn't work; all the buttons report the highest number when clicked.
 * This is because the local ##ii## is captured by the closure. Instead, bind
 * the current value of ##ii##:
 *
 *   var func = function(button_num) {
 *     JX.log('You clicked button number '+button_num+'!');
 *   }
 *   for (var ii = 0; ii < button_list.length; ii++) {
 *     button_list[ii].onclick = JX.bind(null, func, ii);
 *   }
 *
 * @param  obj|null  Context object to bind as ##this##.
 * @param  function  Function to bind context and arguments to.
 * @param  ...       Zero or more arguments to bind.
 * @return function  New function which invokes the original function with
 *                   bound context and arguments when called.
 */
JX.bind = function(context, func, more) {
  if (__DEV__) {
    if (typeof func != 'function') {
      JX.$E(
        'JX.bind(context, <yuck>, ...): '+
        'Attempting to bind something that is not a function.');
    }
  }

  var bound = JX.$A(arguments).slice(2);
  if (func.bind) {
    return func.bind.apply(func, [context].concat(bound));
  }

  return function() {
    return func.apply(context || window, bound.concat(JX.$A(arguments)));
  };
};


/**
 * "Bag of holding"; function that does nothing. Primarily, it's used as a
 * placeholder when you want something to be callable but don't want it to
 * actually have an effect.
 *
 * @return void
 */
JX.bag = function() {
  // \o\ \o/ /o/ woo dance party
};


/**
 * Convert an object's keys into a list. For example:
 *
 *   JX.keys({sun: 1, moon: 1, stars: 1}); // Returns: ['sun', 'moon', 'stars']
 *
 * @param  obj    Object to retrieve keys from.
 * @return list   List of keys.
 */
JX.keys = Object.keys || function(obj) {
  var r = [];
  for (var k in obj) {
    r.push(k);
  }
  return r;
};


/**
 * Identity function; returns the argument unmodified. This is primarily useful
 * as a placeholder for some callback which may transform its argument.
 *
 * @param   wild  Any value.
 * @return  wild  The passed argument.
 */
JX.id = function(any) {
  return any;
};


if (!window.console || !window.console.log) {
  if (window.opera && window.opera.postError) {
    window.console = {log: function(m) { window.opera.postError(m); }};
  } else {
    window.console = {log: function() {}};
  }
}


/**
 * Print a message to the browser debugging console (like Firebug).
 *
 * @param  string Message to print to the browser debugging console.
 * @return void
 */
JX.log = function(message) {
  // "JX.log()" accepts "Error" in addition to "string". Only try to
  // treat the argument as a "sprintf()" pattern if it's a string.
  if (typeof message === 'string') {
    message = JX.sprintf.apply(null, arguments);
  }
  window.console.log(message);
};

JX.sprintf = function(pattern) {
  var argv = Array.prototype.slice.call(arguments);
  argv.reverse();

  // Pop off the pattern argument.
  argv.pop();

  var len = pattern.length;
  var output = '';
  for (var ii = 0; ii < len; ii++) {
    var c = pattern.charAt(ii);

    if (c !== '%') {
      output += c;
      continue;
    }

    ii++;

    var next = pattern.charAt(ii);
    if (next === '%') {
      // This is "%%" (that is, an escaped "%" symbol), so just add a literal
      // "%" to the result.
      output += '%';
      continue;
    }

    if (next === 's') {
      if (!argv.length) {
        throw new Error(
          'Too few arguments to "JX.sprintf(...)" for pattern: ' + pattern);
      }

      output += '' + argv.pop();

      continue;
    }

    if (next === '') {
      throw new Error(
        'Pattern passed to "JX.sprintf(...)" ends with "%": ' + pattern);
    }

    throw new Error(
      'Unknown conversion "%' + c + '" passed to "JX.sprintf(...)" in ' +
      'pattern: ' + pattern);
  }

  if (argv.length) {
    throw new Error(
      'Too many arguments to "JX.sprintf()" for pattern: ' + pattern);
  }

  return output;
};

if (__DEV__) {
  window.alert = (function(native_alert) {
    var recent_alerts = [];
    var in_alert = false;
    return function(msg) {
      if (in_alert) {
        JX.log(
          'alert(...): '+
          'discarded reentrant alert.');
        return;
      }
      in_alert = true;
      recent_alerts.push(JX.now());

      if (recent_alerts.length > 3) {
        recent_alerts.splice(0, recent_alerts.length - 3);
      }

      if (recent_alerts.length >= 3 &&
          (recent_alerts[recent_alerts.length - 1] - recent_alerts[0]) < 5000) {
        if (window.confirm(msg + '\n\nLots of alert()s recently. Kill them?')) {
          window.alert = JX.bag;
        }
      } else {
        //  Note that we can't .apply() the IE6 version of this "function".
        native_alert(msg);
      }
      in_alert = false;
    };
  })(window.alert);
}

/**
 * Date.now is the fastest timestamp function, but isn't supported by every
 * browser. This gives the fastest version the environment can support.
 * The wrapper function makes the getTime call even slower, but benchmarking
 * shows it to be a marginal perf loss. Considering how small of a perf
 * difference this makes overall, it's not really a big deal. The primary
 * reason for this is to avoid hacky "just think of the byte savings" JS
 * like +new Date() that has an unclear outcome for the unexposed.
 *
 * @return Int A Unix timestamp of the current time on the local machine
 */
JX.now = (Date.now || function() { return new Date().getTime(); });
