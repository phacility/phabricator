/** @provides javelin-lib-dev */

/**
 * Javelin utility functions.
 *
 * @provides javelin-util
 *
 * @javelin-installs JX.$A
 * @javelin-installs JX.$AX
 * @javelin-installs JX.copy
 * @javelin-installs JX.bind
 * @javelin-installs JX.bag
 * @javelin-installs JX.keys
 * @javelin-installs JX.defer
 * @javelin-installs JX.go
 * @javelin-installs JX.log
 *
 * @javelin
 */


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
JX.$A = function(mysterious_arraylike_object) {
  // NOTE: This avoids the Array.slice() trick because some bizarre COM object
  // I dug up somewhere was freaking out when I tried to do it and it made me
  // very upset, so do not replace this with Array.slice() cleverness.
  var r = [];
  for (var ii = 0; ii < mysterious_arraylike_object.length; ii++) {
    r.push(mysterious_arraylike_object[ii]);
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
 * Note that this function uses an "instanceof Array" check so you may need to
 * convert array-like objects (such as ##arguments## and Array instances from
 * iframes) into real arrays with @{JX.$A()}.
 *
 * @param  wild    Scalar or Array.
 * @return Array   If the argument was a scalar, an Array with the argument as
 *                 its only element. Otherwise, the original Array.
 *
 */
JX.$AX = function(maybe_scalar) {
  return (maybe_scalar instanceof Array) ? maybe_scalar : [maybe_scalar];
};


/**
 * Copy properties from one object to another. Note: does not copy the
 * ##toString## property or anything else which isn't enumerable or is somehow
 * magic or just doesn't work. But it's usually what you want. If properties
 * already exist, they are overwritten.
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
 * @param  obj Destination object, which properties should be copied to.
 * @param  obj Source object, which properties should be copied from.
 * @return obj Destination object.
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
 * later invoked; @{JX.Stratcom.listen()} does not know about the context
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
 * @{JX.bind()} also performs partial function application, which allows you
 * to bind one or more arguments to a function. For instance, if we have a
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
      throw new Error(
        'JX.bind(context, <yuck>, ...): '+
        'Attempting to bind something that is not a function.');
    }
  }

  var bound = JX.$A(arguments).slice(2);
  return function() {
    return func.apply(context || window, bound.concat(JX.$A(arguments)));
  }
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
JX.keys = function(obj) {
  var r = [];
  for (var k in obj) {
    r.push(k);
  }
  return r;
};


/**
 * Defer a function for later execution, similar to ##setTimeout()##. Returns
 * an object with a ##stop()## method, which cancels the deferred call.
 *
 *   var ref = JX.defer(yell, 3000); // Yell in 3 seconds.
 *   // ...
 *   ref.stop(); // Cancel the yell.
 *
 * @param function Function to invoke after the timeout.
 * @param int?     Timeout, in milliseconds. If this value is omitted, the
 *                 function will be invoked once control returns to the browser
 *                 event loop, as with ##setTimeout(func, 0)##.
 * @return obj     An object with a ##stop()## method, which cancels function
 *                 execution.
 */
JX.defer = function(func, timeout) {
  var t = setTimeout(func, timeout || 0);
  return {stop : function() { clearTimeout(t); }}
};


/**
 * Redirect the browser to another page by changing the window location.
 *
 * @param  string    Optional URI to redirect the browser to. If no URI is
 *                   provided, the current page will be reloaded.
 * @return void
 */
JX.go = function(uri) {

  // Foil static analysis, etc. Strictly speaking, JX.go() doesn't really need
  // to be in javelin-utils so we could do this properly at some point.
  JX['Stratcom'] && JX['Stratcom'].invoke('go', null, {uri:  uri});

  (uri && (window.location = uri)) || window.location.reload(true);
};


if (__DEV__) {
  if (!window.console || !window.console.log) {
    if (window.opera && window.opera.postError) {
      window.console = {log: function(m) { window.opera.postError(m); }};
    } else {
      window.console = {log: function(m) { }};
    }
  }

  /**
   * Print a message to the browser debugging console (like Firebug). This
   * method exists only in ##__DEV__##.
   *
   * @param  string Message to print to the browser debugging console.
   * @return void
   */
  JX.log = function(message) {
    window.console.log(message);
  }

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
      recent_alerts.push(new Date().getTime());

      if (recent_alerts.length > 3) {
        recent_alerts.splice(0, recent_alerts.length - 3);
      }

      if (recent_alerts.length >= 3 &&
          (recent_alerts[recent_alerts.length - 1] - recent_alerts[0]) < 5000) {
        if (confirm(msg + "\n\nLots of alert()s recently. Kill them?")) {
          window.alert = JX.bag;
        }
      } else {
        //  Note that we can't .apply() the IE6 version of this "function".
        native_alert(msg);
      }
      in_alert = false;
    }
  })(window.alert);

}
/**
 * @requires javelin-util
 * @provides javelin-install
 * @javelin-installs JX.install
 * @javelin
 */

/**
 * Install a class into the Javelin ("JX") namespace. The first argument is the
 * name of the class you want to install, and the second is a map of these
 * attributes (all of which are optional):
 *
 *   - ##construct## //(function)// Class constructor. If you don't provide one,
 *       one will be created for you (but it will be very boring).
 *   - ##extend## //(string)// The name of another JX-namespaced class to extend
 *       via prototypal inheritance.
 *   - ##members## //(map)// A map of instance methods and properties.
 *   - ##statics## //(map)// A map of static methods and properties.
 *   - ##initialize## //(function)// A function which will be run once, after
 *       this class has been installed.
 *   - ##properties## //(map)// A map of properties that should have instance
 *       getters and setters automatically generated for them. The key is the
 *       property name and the value is its default value. For instance, if you
 *       provide the property "size", the installed class will have the methods
 *       "getSize()" and "setSize()". It will **NOT** have a property ".size"
 *       and no guarantees are made about where install is actually chosing to
 *       store the data. The motivation here is to let you cheaply define a
 *       stable interface and refine it later as necessary.
 *   - ##events## //(list)// List of event types this class is capable of
 *       emitting.
 *
 * For example:
 *
 *   JX.install('Dog', {
 *     construct : function(name) {
 *       this.setName(name);
 *     },
 *     members : {
 *       bark : function() {
 *         // ...
 *       }
 *     },
 *     properites : {
 *       name : null,
 *     }
 *   });
 *
 * This creates a new ##Dog## class in the ##JX## namespace:
 *
 *   var d = new JX.Dog();
 *   d.bark();
 *
 * Javelin classes are normal Javascript functions and generally behave in
 * the expected way. Some properties and methods are automatically added to
 * all classes:
 *
 *   - ##instance.__id__## Globally unique identifier attached to each instance.
 *   - ##instance.__super__## Reference to the parent class constructor, if one
 *      exists. Allows use of ##this.__super__.apply(this, ...)## to call the
 *      superclass's constructor.
 *   - ##instance.__parent__## Reference to the parent class prototype, if one
 *      exists. Allows use of ##this.__parent__.someMethod.apply(this, ...)##
 *      to call the superclass's methods.
 *   - ##prototype.__class__## Reference to the class constructor.
 *   - ##constructor.__path__## List of path tokens used emit events. It is
 *       probably never useful to access this directly.
 *   - ##constructor.__readable__## //DEV ONLY!// Readable class name. You could
 *       plausibly use this when constructing error messages.
 *   - ##constructor.__events__## //DEV ONLY!// List of events supported by
 *       this class.
 *   - ##constructor.listen()## Listen to all instances of this class. See
 *       @{JX.Base}.
 *   - ##instance.listen()## Listen to one instance of this class. See
 *       @{JX.Base}.
 *   - ##instance.invoke()## Invoke an event from an instance. See @{JX.Base}.
 *
 *
 * @param  string  Name of the class to install. It will appear in the JX
 *                 "namespace" (e.g., JX.Pancake).
 * @param  map     Map of properties, see method documentation.
 * @return void
 *
 * @author epriestley
 */
JX.install = function(new_name, new_junk) {

  if (typeof JX.install._nextObjectID == 'undefined') {
    JX.install._nextObjectID = 0;
  }

  // If we've already installed this, something is up.
  if (new_name in JX) {
    if (__DEV__) {
      throw new Error(
        'JX.install("' + new_name + '", ...): ' +
        'trying to reinstall something that has already been installed.');
    }
    return;
  }

  // Since we may end up loading things out of order (e.g., Dog extends Animal
  // but we load Dog first) we need to keep a list of things that we've been
  // asked to install but haven't yet been able to install around.
  if (!JX.install._queue) {
    JX.install._queue = [];
  }
  JX.install._queue.push([new_name, new_junk]);
  do {
    var junk;
    var name = null;
    for (var ii = 0; ii < JX.install._queue.length; ++ii) {
      junk = JX.install._queue[ii][1];
      if (junk.extend && !JX[junk.extend]) {
        // We need to extend something that we haven't been able to install
        // yet, so just keep this in queue.
        continue;
      }

      // Install time! First, get this out of the queue.
      name = JX.install._queue[ii][0];
      JX.install._queue.splice(ii, 1);
      --ii;

      if (__DEV__) {
        var valid = {
          construct : 1,
          statics : 1,
          members : 1,
          extend : 1,
          initialize: 1,
          properties : 1,
          events : 1,
          canCallAsFunction : 1
        };
        for (var k in junk) {
          if (!(k in valid)) {
            throw new Error(
              'JX.install("' + name + '", {"' + k + '": ...}): ' +
              'trying to install unknown property `' + k + '`.');
          }
        }
        if (junk.constructor !== {}.constructor) {
          throw new Error(
            'JX.install("' + name + '", {"constructor": ...}): ' +
            'property `constructor` should be called `construct`.');
        }
      }

      // First, build the constructor. If construct is just a function, this
      // won't change its behavior (unless you have provided a really awesome
      // function, in which case it will correctly punish you for your attempt
      // at creativity).
      JX[name] = (function(name, junk) {
        var result = function() {
          this.__id__ = '__obj__' + (++JX.install._nextObjectID);
          this.__super__ = JX[junk.extend] || JX.bag;
          this.__parent__ = JX[name].prototype;
          if (JX[name].__prototyping__) {
            return;
          }
          return (junk.construct || JX.bag).apply(this, arguments);
          // TODO: Allow mixins to initialize here?
          // TODO: Also, build mixins?
        };

        if (__DEV__) {
          if (!junk.canCallAsFunction) {
            var inner = result;
            result = function() {
              if (this === window || this === JX) {
                throw new Error("<" + JX[name].__readable__ + ">: " +
                                "Tried to construct an instance " +
                                "without the 'new' operator. Either use " +
                                "'new' or set 'canCallAsFunction' where you " +
                                "install the class.");
              }
              return inner.apply(this, arguments);
            };
          }
        }
        return result;
      })(name, junk);

      // Copy in all the static methods and properties.
      JX.copy(JX[name], junk.statics);

      if (__DEV__) {
        JX[name].__readable__ = 'JX.' + name;
      }

      JX[name].__prototyping__ = 0;

      var proto;
      if (junk.extend) {
        JX[junk.extend].__prototyping__++;
        proto = JX[name].prototype = new JX[junk.extend]();
        JX[junk.extend].__prototyping__--;
      } else {
        proto = JX[name].prototype = {};
      }

      proto.__class__ = JX[name];

      // Build getters and setters from the `prop' map.
      for (var k in (junk.properties || {})) {
        var base = k.charAt(0).toUpperCase()+k.substr(1);
        var prop = '__auto__' + k;
        proto[prop] = junk.properties[k];
        proto['set' + base] = (function(prop) {
          return function(v) {
            this[prop] = v;
            return this;
          }
        })(prop);

        proto['get' + base] = (function(prop) {
          return function() {
            return this[prop];
          }
        })(prop);
      }

      if (__DEV__) {

        // Check for aliasing in default values of members. If we don't do this,
        // you can run into a problem like this:
        //
        //  JX.install('List', { members : { stuff : [] }});
        //
        //  var i_love = new JX.List();
        //  var i_hate = new JX.List();
        //
        //  i_love.stuff.push('Psyduck');  // I love psyduck!
        //  JX.log(i_hate.stuff);          // Show stuff I hate.
        //
        // This logs ["Psyduck"] because the push operation modifies
        // JX.List.prototype.stuff, which is what both i_love.stuff and
        // i_hate.stuff resolve to. To avoid this, set the default value to
        // null (or any other scalar) and do "this.stuff = [];" in the
        // constructor.

        for (var member_name in junk.members) {
          if (junk.extend && member_name[0] == '_') {
            throw new Error(
              'JX.install("' + name + '", ...): ' +
              'installed member "' + member_name + '" must not be named with ' +
              'a leading underscore because it is in a subclass. Variables ' +
              'are analyzed and crushed one file at a time, and crushed ' +
              'member variables in subclasses alias crushed member variables ' +
              'in superclasses. Remove the underscore, refactor the class so ' +
              'it does not extend anything, or fix the minifier to be ' +
              'capable of safely crushing subclasses.');
          }
          var member_value = junk.members[member_name];
          if (typeof member_value == 'object' && member_value !== null) {
            throw new Error(
              'JX.install("' + name + '", ...): ' +
              'installed member "' + member_name + '" is not a scalar or ' +
              'function. Prototypal inheritance in Javascript aliases object ' +
              'references across instances so all instances are initialized ' +
              'to point at the exact same object. This is almost certainly ' +
              'not what you intended. Make this member static to share it ' +
              'across instances, or initialize it in the constructor to ' +
              'prevent reference aliasing and give each instance its own ' +
              'copy of the value.');
          }
        }
      }


      // This execution order intentionally allows you to override methods
      // generated from the "properties" initializer.
      JX.copy(proto, junk.members);


      // Build this ridiculous event model thing. Basically, this defines
      // two instance methods, invoke() and listen(), and one static method,
      // listen(). If you listen to an instance you get events for that
      // instance; if you listen to a class you get events for all instances
      // of that class (including instances of classes which extend it).
      //
      // This is rigged up through Stratcom. Each class has a path component
      // like "class:Dog", and each object has a path component like
      // "obj:23". When you invoke on an object, it emits an event with
      // a path that includes its class, all parent classes, and its object
      // ID.
      //
      // Calling listen() on an instance listens for just the object ID.
      // Calling listen() on a class listens for that class's name. This
      // has the effect of working properly, but installing them is pretty
      // messy.
      if (junk.events && junk.events.length) {

        var parent = JX[junk.extend] || {};

        // If we're in dev, we build up a list of valid events (for this
        // class or some parent class) and then check them whenever we try
        // to listen or invoke.
        if (__DEV__) {
          var valid_events = parent.__events__ || {};
          for (var ii = 0; ii < junk.events.length; ++ii) {
            valid_events[junk.events[ii]] = true;
          }
          JX[name].__events__ = valid_events;
        }

        // Build the class name chain.
        JX[name].__name__ = 'class:' + name;
        var ancestry = parent.__path__ || [];
        JX[name].__path__ = ancestry.concat([JX[name].__name__]);

        proto.invoke = function(type) {
          if (__DEV__) {
            if (!(type in this.__class__.__events__)) {
              throw new Error(
                this.__class__.__readable__ + '.invoke("' + type + '", ...): ' +
                'invalid event type. Valid event types are: ' +
                JX.keys(this.__class__.__events__).join(', ') + '.');
            }
          }
          // Here and below, this nonstandard access notation is used to mask
          // these callsites from the static analyzer. JX.Stratcom is always
          // available by the time we hit these execution points.
          return JX['Stratcom'].invoke(
            'obj:' + type,
            this.__class__.__path__.concat([this.__id__]),
            {args : JX.$A(arguments).slice(1)});
        };

        proto.listen = function(type, callback) {
          if (__DEV__) {
            if (!(type in this.__class__.__events__)) {
              throw new Error(
                this.__class__.__readable__ + '.listen("' + type + '", ...): ' +
                'invalid event type. Valid event types are: ' +
                JX.keys(this.__class__.__events__).join(', ') + '.');
            }
          }
          return JX['Stratcom'].listen(
            'obj:' + type,
            this.__id__,
            JX.bind(this, function(e) {
              return callback.apply(this, e.getData().args);
            }));
        };

        JX[name].listen = function(type, callback) {
          if (__DEV__) {
            if (!(type in this.__events__)) {
              throw new Error(
                this.__readable__ + '.listen("' + type + '", ...): ' +
                'invalid event type. Valid event types are: ' +
                JX.keys(this.__events__).join(', ') + '.');
            }
          }
          return JX['Stratcom'].listen(
            'obj:' + type,
            this.__name__,
            JX.bind(this, function(e) {
              return callback.apply(this, e.getData().args);
            }));
        };
      } else if (__DEV__) {
        var error_message =
          'class does not define any events. Pass an "events" property to ' +
          'JX.install() to define events.';
        JX[name].listen = JX[name].listen || function() {
          throw new Error(
            this.__readable__ + '.listen(...): ' +
            error_message);
        };
        JX[name].invoke = JX[name].invoke || function() {
          throw new Error(
            this.__readable__ + '.invoke(...): ' +
            error_message);
        };
        proto.listen = proto.listen || function() {
          throw new Error(
            this.__class__.__readable__ + '.listen(...): ' +
            error_message);
        };
        proto.invoke = proto.invoke || function() {
          throw new Error(
            this.__class__.__readable__ + '.invoke(...): ' +
            error_message);
        };
      }

      // Finally, run the init function if it was provided.
      (junk.initialize || JX.bag)();
    }

    // In effect, this exits the loop as soon as we didn't make any progress
    // installing things, which means we've installed everything we have the
    // dependencies for.
  } while (name);
}
/**
 * @requires javelin-install
 * @provides javelin-event
 * @javelin
 */

/**
 * A generic event, routed by @{JX.Stratcom}. All events within Javelin are
 * represented by a {@JX.Event}, regardless of whether they originate from
 * a native DOM event (like a mouse click) or are custom application events.
 *
 * Events have a propagation model similar to native Javascript events, in that
 * they can be stopped with stop() (which stops them from continuing to
 * propagate to other handlers) or prevented with prevent() (which prevents them
 * from taking their default action, like following a link). You can do both at
 * once with kill().
 *
 * @author epriestley
 * @task stop Stopping Event Behaviors
 * @task info Getting Event Information
 */
JX.install('Event', {
  members : {

    /**
     * Stop an event from continuing to propagate. No other handler will
     * receive this event, but its default behavior will still occur. See
     * ""Using Events"" for more information on the distinction between
     * 'stopping' and 'preventing' an event. See also prevent() (which prevents
     * an event but does not stop it) and kill() (which stops and prevents an
     * event).
     *
     * @return this
     * @task stop
     */
    stop : function() {
      var r = this.getRawEvent();
      if (r) {
        r.cancelBubble = true;
        r.stopPropagation && r.stopPropagation();
      }
      this.setStopped(true);
      return this;
    },


    /**
     * Prevent an event's default action. This depends on the event type, but
     * the common default actions are following links, submitting forms,
     * and typing text. Event prevention is generally used when you have a link
     * or form which work properly without Javascript but have a specialized
     * Javascript behavior. When you intercept the event and make the behavior
     * occur, you prevent it to keep the browser from following the link.
     *
     * Preventing an event does not stop it from propagating, so other handlers
     * will still receive it. See ""Using Events"" for more information on the
     * distinction between 'stopping' and 'preventing' an event. See also
     * stop() (which stops an event but does not prevent it) and kill()
     * (which stops and prevents an event).
     *
     * @return this
     * @task stop
     */
    prevent : function() {
      var r = this.getRawEvent();
      if (r) {
        r.returnValue = false;
        r.preventDefault && r.preventDefault();
      }
      this.setPrevented(true);
      return this;
    },


    /**
     * Stop and prevent an event, which stops it from propagating and prevents
     * its defualt behavior. This is a convenience function, see stop() and
     * prevent() for information on what it means to stop or prevent an event.
     *
     * @return this
     * @task stop
     */
    kill : function() {
      this.prevent();
      this.stop();
      return this;
    },


    /**
     * Get the special key (like tab or return), if any,  associated with this
     * event. Browsers report special keys differently;  this method allows you
     * to identify a keypress in a browser-agnostic way. Note that this detects
     * only some special keys: delete, tab, return escape, left, up, right,
     * down.
     *
     * For example, if you want to react to the escape key being pressed, you
     * could install a listener like this:
     *
     *  JX.Stratcom.listen('keydown', 'example', function(e) {
     *    if (e.getSpecialKey() == 'esc') {
     *      JX.log("You pressed 'Escape'! Well done! Bravo!");
     *    }
     *  });
     *
     *
     * @return string|null ##null## if there is no associated special key,
     *                     or one of the strings 'delete', 'tab', 'return',
     *                     'esc', 'left', 'up', 'right', or 'down'.
     * @task info
     */
    getSpecialKey : function() {
      var r = this.getRawEvent();
      if (!r || r.shiftKey) {
        return null;
      }

      var c = r.keyCode;
      do {
        c = JX.Event._keymap[c] || null;
      } while (c && JX.Event._keymap[c])

      return c;
    },

    /**
     * Get the node corresponding to the specified key in this event's node map.
     * This is a simple helper method that makes the API for accessing nodes
     * less ugly.
     *
     *  JX.Stratcom.listen('click', 'tag:a', function(e) {
     *    var a = e.getNode('nearest:a');
     *    // do something with the link that was clicked
     *  });
     *
     * @param  string     sigil or stratcom node key
     * @return node|null  Node mapped to the specified key, or null if it the
     *                    key does not exist. The available keys include:
     *                    - 'tag:'+tag - first node of each type
     *                    - 'id:'+id - all nodes with an id
     *                    - sigil - first node of each sigil
     * @task info
     */
    getNode: function(key) {
      return this.getNodes()[key] || null;
    }

  },

  statics : {
    _keymap : {
      8     : 'delete',
      9     : 'tab',
      13    : 'return',
      27    : 'esc',
      37    : 'left',
      38    : 'up',
      39    : 'right',
      40    : 'down',
      63232 : 38,
      63233 : 40,
      62234 : 37,
      62235 : 39
    }
  },

  properties : {

    /**
     * Native Javascript event which generated this @{JX.Event}. Not every
     * event is generated by a native event, so there may be ##null## in
     * this field.
     *
     * @type Event|null
     * @task info
     */
    rawEvent : null,

    /**
     * String describing the event type, like 'click' or 'mousedown'. This
     * may also be an application or object event.
     *
     * @type string
     * @task info
     */
    type : null,

    /**
     * If available, the DOM node where this event occurred. For example, if
     * this event is a click on a button, the target will be the button which
     * was clicked. Application events will not have a target, so this property
     * will return the value ##null##.
     *
     * @type DOMNode|null
     * @task info
     */
    target : null,

    /**
     * Metadata attached to nodes associated with this event.
     *
     * For native events, the DOM is walked from the event target to the root
     * element. Each sigil which is encountered while walking up the tree is
     * added to the map as a key. If the node has associated metainformation,
     * it is set as the value; otherwise, the value is null.
     *
     * @type dict<string, *>
     * @task info
     */
    data : null,

    /**
     * Sigil path this event was activated from. TODO: explain this
     *
     * @type list<string>
     * @task info
     */
    path : [],

    /**
     * True if propagation of the event has been stopped. See stop().
     *
     * @type bool
     * @task stop
     */
    stopped : false,

    /**
     * True if default behavior of the event has been prevented. See prevent().
     *
     * @type bool
     * @task stop
     */
    prevented : false,

    /**
     * @task info
     */
    nodes : {}
  },

  /**
   * @{JX.Event} installs a toString() method in ##__DEV__## which allows you to
   * log or print events and get a reasonable representation of them:
   *
   *  Event<'click', ['path', 'stuff'], [object HTMLDivElement]>
   */
  initialize : function() {
    if (__DEV__) {
      JX.Event.prototype.toString = function() {
        var path = '['+this.getPath().join(', ')+']';
        return 'Event<'+this.getType()+', '+path+', '+this.getTarget()+'>';
      }
    }
  }
});
/**
 *  @requires javelin-install javelin-event javelin-util javelin-magical-init
 *  @provides javelin-stratcom
 *  @javelin
 */

/**
 * Javelin strategic command, the master event delegation core. This class is
 * a sort of hybrid between Arbiter and traditional event delegation, and
 * serves to route event information to handlers in a general way.
 *
 * Each Javelin :JX.Event has a 'type', which may be a normal Javascript type
 * (for instance, a click or a keypress) or an application-defined type. It
 * also has a "path", based on the path in the DOM from the root node to the
 * event target. Note that, while the type is required, the path may be empty
 * (it often will be for application-defined events which do not originate
 * from the DOM).
 *
 * The path is determined by walking down the tree to the event target and
 * looking for nodes that have been tagged with metadata. These names are used
 * to build the event path, and unnamed nodes are ignored. Each named node may
 * also have data attached to it.
 *
 * Listeners specify one or more event types they are interested in handling,
 * and, optionally, one or more paths. A listener will only receive events
 * which occurred on paths it is listening to. See listen() for more details.
 *
 * @author epriestley
 *
 * @task invoke   Invoking Events
 * @task listen   Listening to Events
 * @task handle   Responding to Events
 * @task sigil    Managing Sigils
 * @task internal Internals
 */
JX.install('Stratcom', {
  statics : {
    ready : false,
    _targets : {},
    _handlers : [],
    _need : {},
    _matchName : /\bFN_([^ ]+)/,
    _matchData : /\bFD_([^ ]+)_([^ ]+)/,
    _auto : '*',
    _data : {},
    _execContext : [],
    _typeMap : {focusin: 'focus', focusout: 'blur'},

    /**
     * Node metadata is stored in a series of blocks to prevent collisions
     * between indexes that are generated on the server side (and potentially
     * concurrently). Block 0 is for metadata on the initial page load, block 1
     * is for metadata added at runtime with JX.Stratcom.siglize(), and blocks
     * 2 and up are for metadata generated from other sources (e.g. JX.Request).
     * Use allocateMetadataBlock() to reserve a block, and mergeData() to fill
     * a block with data.
     *
     * When a JX.Request is sent, a block is allocated for it and any metadata
     * it returns is filled into that block.
     */
    _dataBlock : 2,

    /**
     * Within each datablock, data is identified by a unique index. The data
     * pointer on a node looks like this:
     *
     *  FD_1_2
     *
     * ...where 1 is the block, and 2 is the index within that block. Normally,
     * blocks are filled on the server side, so index allocation takes place
     * there. However, when data is provided with JX.Stratcom.sigilize(), we
     * need to allocate indexes on the client.
     */
    _dataIndex : 0,

    /**
     * Dispatch a simple event that does not have a corresponding native event
     * object. It is unusual to call this directly. Generally, you will instead
     * dispatch events from an object using the invoke() method present on all
     * objects. See @{JX.Base.invoke()} for documentation.
     *
     * @param  string       Event type.
     * @param  list?        Optionally, a path to attach to the event. This is
     *                      rarely meaingful for simple events.
     * @param  object?      Optionally, arbitrary data to send with the event.
     * @return @{JX.Event}  The event object which was dispatched to listeners.
     *                      The main use of this is to test whether any
     *                      listeners prevented the event.
     * @task invoke
     */
    invoke : function(type, path, data) {
      var proxy = new JX.Event()
        .setType(type)
        .setData(data || {})
        .setPath(path || []);

      return this._dispatchProxy(proxy);
    },


    /**
     * Listen for events on given paths. Specify one or more event types, and
     * zero or more paths to filter on. If you don't specify a path, you will
     * receive all events of the given type:
     *
     *   // Listen to all clicks.
     *   JX.Stratcom.listen('click', null, handler);
     *
     * This will notify you of all clicks anywhere in the document (unless
     * they are intercepted and killed by a higher priority handler before they
     * get to you).
     *
     * Often, you may be interested in only clicks on certain elements. You
     * can specify the paths you're interested in to filter out events which
     * you do not want to be notified of.
     *
     *   //  Listen to all clicks inside elements annotated "news-feed".
     *   JX.Stratcom.listen('click', 'news-feed', handler);
     *
     * By adding more elements to the path, you can create a finer-tuned
     * filter:
     *
     *   //  Listen to only "like" clicks inside "news-feed".
     *   JX.Stratcom.listen('click', ['news-feed', 'like'], handler);
     *
     *
     * TODO: Further explain these shenanigans.
     *
     * @param  string|list<string>  Event type (or list of event names) to
     *                   listen for. For example, ##'click'## or
     *                   ##['keydown', 'keyup']##.
     *
     * @param  wild      Sigil paths to listen for this event on. See discussion
     *                   in method documentation.
     *
     * @param  function  Callback to invoke when this event is triggered. It
     *                   should have the signature ##f(:JX.Event e)##.
     *
     * @return object    A reference to the installed listener. You can later
     *                   remove the listener by calling this object's remove()
     *                   method.
     * @author epriestley
     * @task listen
     */
    listen : function(types, paths, func) {

      if (__DEV__) {
        if (arguments.length == 4) {
          throw new Error(
            'JX.Stratcom.listen(...): '+
            'requires exactly 3 arguments. Did you mean JX.DOM.listen?');
        }
        if (arguments.length != 3) {
          throw new Error(
            'JX.Stratcom.listen(...): '+
            'requires exactly 3 arguments.');
        }
        if (typeof func != 'function') {
          throw new Error(
            'JX.Stratcom.listen(...): '+
            'callback is not a function.');
        }
      }

      var ids = [];

      types = JX.$AX(types);

      if (!paths) {
        paths = this._auto;
      }
      if (!(paths instanceof Array)) {
        paths = [[paths]];
      } else if (!(paths[0] instanceof Array)) {
        paths = [paths];
      }

      //  To listen to multiple event types on multiple paths, we just install
      //  the same listener a whole bunch of times: if we install for two
      //  event types on three paths, we'll end up with six references to the
      //  listener.
      //
      //  TODO: we'll call your listener twice if you install on two paths where
      //  one path is a subset of another. The solution is "don't do that", but
      //  it would be nice to verify that the caller isn't doing so, in __DEV__.
      for (var ii = 0; ii < types.length; ++ii) {
        var type = types[ii];
        if (('onpagehide' in window) && type == 'unload') {
          // If we use "unload", we break the bfcache ("Back-Forward Cache") in
          // Safari and Firefox. The BFCache makes using the back/forward
          // buttons really fast since the pages can come out of magical
          // fairyland instead of over the network, so use "pagehide" as a proxy
          // for "unload" in these browsers.
          type = 'pagehide';
        }
        if (!(type in this._targets)) {
          this._targets[type] = {};
        }
        var type_target = this._targets[type];
        for (var jj = 0; jj < paths.length; ++jj) {
          var path = paths[jj];
          var id = this._handlers.length;
          this._handlers.push(func);
          this._need[id] = path.length;
          ids.push(id);
          for (var kk = 0; kk < path.length; ++kk) {
            if (__DEV__) {
              if (path[kk] == 'tag:#document') {
                throw new Error(
                  'JX.Stratcom.listen(..., "tag:#document", ...): ' +
                  'listen for document events as "tag:window", not ' +
                  '"tag:#document", in order to get consistent behavior ' +
                  'across browsers.');
              }
            }
            if (!type_target[path[kk]]) {
              type_target[path[kk]] = [];
            }
            type_target[path[kk]].push(id);
          }
        }
      }

      return {
        remove : function() {
          for (var ii = 0; ii < ids.length; ii++) {
            delete JX.Stratcom._handlers[ids[ii]];
          }
        }
      };
    },


    /**
     * Dispatch a native Javascript event through the Stratcom control flow.
     * Generally, this is automatically called for you by the master dipatcher
     * installed by ##init.js##. When you want to dispatch an application event,
     * you should instead call invoke().
     *
     * @param  Event       Native event for dispatch.
     * @return :JX.Event   Dispatched :JX.Event.
     * @task internal
     */
    dispatch : function(event) {
      // TODO: simplify this :P
      var target;
      try {
        target = event.srcElement || event.target;
        if (target === window || (!target || target.nodeName == '#document')) {
          target = {nodeName: 'window'};
        }
      } catch (x) {
        target = null;
      }

      var path = [];
      var nodes = {};
      var push = function(key, node) {
        // we explicitly only store the first occurrence of each key
        if (!(key in nodes)) {
          nodes[key] = node;
          path.push(key);
        }
      };

      var cursor = target;
      while (cursor) {
        push('tag:' + cursor.nodeName.toLowerCase(), cursor);

        var id = cursor.id;
        if (id) {
          push('id:' + id, cursor);
        }

        var source = cursor.className || '';
        // className is an SVGAnimatedString for SVG elements, use baseVal
        var token = ((source.baseVal || source).match(this._matchName) || [])[1];
        if (token) {
          push(token, cursor);
        }

        cursor = cursor.parentNode;
      }

      var etype = event.type;
      if (etype in this._typeMap) {
        etype = this._typeMap[etype];
      }

      var data = {};
      for (var key in nodes) {
        data[key] = this.getData(nodes[key]);
      }

      var proxy = new JX.Event()
        .setRawEvent(event)
        .setType(etype)
        .setTarget(target)
        .setData(data)
        .setNodes(nodes)
        .setPath(path.reverse());

//      JX.log('~> '+proxy.toString());

      return this._dispatchProxy(proxy);
    },


    /**
     * Dispatch a previously constructed proxy :JX.Event.
     *
     * @param  :JX.Event Event to dispatch.
     * @return :JX.Event Returns the event argument.
     * @task internal
     */
    _dispatchProxy : function(proxy) {

      var scope = this._targets[proxy.getType()];

      if (!scope) {
        return proxy;
      }

      var path = proxy.getPath();
      var len = path.length;
      var hits = {};
      var matches;

      for (var root = -1; root < len; ++root) {
        if (root == -1) {
          matches = scope[this._auto];
        } else {
          matches = scope[path[root]];
        }
        if (!matches) {
          continue;
        }
        for (var ii = 0; ii < matches.length; ++ii) {
          hits[matches[ii]] = (hits[matches[ii]] || 0) + 1;
        }
      }

      var exec = [];

      for (var k in hits) {
        if (hits[k] == this._need[k]) {
          var handler = this._handlers[k];
          if (handler) {
            exec.push(handler);
          }
        }
      }

      this._execContext.push({
        handlers: exec,
        event: proxy,
        cursor: 0
      });

      this.pass();

      this._execContext.pop();

      return proxy;
    },

    /**
     * Pass on an event, allowing other handlers to process it. The use case
     * here is generally something like:
     *
     *   if (JX.Stratcom.pass()) {
     *     // something else handled the event
     *     return;
     *   }
     *   // handle the event
     *   event.prevent();
     *
     * This allows you to install event handlers that operate at a lower
     * effective priority, and provide a default behavior which is overridable
     * by listeners.
     *
     * @return bool  True if the event was stopped or prevented by another
     *               handler.
     * @task handle
     */
    pass : function() {
      var context = this._execContext[this._execContext.length - 1];
      while (context.cursor < context.handlers.length) {
        var cursor = context.cursor;
        ++context.cursor;
        (context.handlers[cursor] || JX.bag)(context.event);
        if (context.event.getStopped()) {
          break;
        }
      }
      return context.event.getStopped() || context.event.getPrevented();
    },


    /**
     * Retrieve the event (if any) which is currently being dispatched.
     *
     * @return :JX.Event|null   Event which is currently being dispatched, or
     *                          null if there is no active dispatch.
     * @task handle
     */
    context : function() {
      var len = this._execContext.length;
      if (!len) {
        return null;
      }
      return this._execContext[len - 1].event;
    },


    /**
     * Merge metadata. You must call this (even if you have no metadata) to
     * start the Stratcom queue.
     *
     * @param  int          The datablock to merge data into.
     * @param  dict          Dictionary of metadata.
     * @return void
     * @task internal
     */
    mergeData : function(block, data) {
      this._data[block] = data;
      if (block == 0) {
        JX.Stratcom.ready = true;
        JX.__rawEventQueue({type: 'start-queue'});
      }
    },


    /**
     * Attach a sigil (and, optionally, metadata) to a node. Note that you can
     * not overwrite, remove or replace a sigil.
     *
     * @param   Node    Node without any sigil.
     * @param   string  Sigil to name the node with.
     * @param   object? Optional metadata object to attach to the node.
     * @return  void
     * @task sigil
     */
    sigilize : function(node, sigil, data) {
      if (__DEV__) {
        if (node.className.match(this._matchName)) {
          throw new Error(
            'JX.Stratcom.sigilize(<node>, ' + sigil + ', ...): ' +
            'node already has a sigil, sigils may not be overwritten.');
        }
        if (typeof data != 'undefined' &&
            (data === null || typeof data != 'object')) {
          throw new Error(
            'JX.Stratcom.sigilize(..., ..., <nonobject>): ' +
            'data to attach to node is not an object. You must use ' +
            'objects, not primitives, for metadata.');
        }
      }

      if (data) {
        JX.Stratcom._setData(node, data);
      }

      node.className = 'FN_' + sigil + ' ' + node.className;
    },


    /**
     * Determine if a node has a specific sigil.
     *
     * @param  Node    Node to test.
     * @param  string  Sigil to check for.
     * @return bool    True if the node has the sigil.
     *
     * @task sigil
     */
    hasSigil : function(node, sigil) {
      if (!node.className) {
        // Some nodes don't have a className, notably 'document'. We hit
        // 'document' when following .parentNode chains, e.g. in
        // JX.DOM.nearest(), so exit early if we don't have a className to avoid
        // fataling on 'node.className.match' being undefined.
        return false;
      }
      return (node.className.match(this._matchName) || [])[1] == sigil;
    },


    /**
     * Retrieve a node's metadata.
     *
     * @param  Node    Node from which to retrieve data.
     * @return object  Data attached to the node, or an empty dictionary if
     *                 the node has no data attached. In this case, the empty
     *                 dictionary is set as the node's metadata -- i.e.,
     *                 subsequent calls to getData() will retrieve the same
     *                 object.
     *
     * @task sigil
     */
    getData : function(node) {
      if (__DEV__) {
        if (!node) {
          throw new Error(
            'JX.Stratcom.getData(<empty>): ' +
            'you must provide a node to get associated data from.');
        }
      }

      var matches = (node.className || '').match(this._matchData);
      if (matches) {
        var block = this._data[matches[1]];
        var index = matches[2];
        if (block && (index in block)) {
          return block[index];
        }
      }

      return JX.Stratcom._setData(node, {});
    },

    /**

     * @task internal
     */
     allocateMetadataBlock : function() {
       return this._dataBlock++;
    },

    /**
     * Attach metadata to a node. This data can later be retrieved through
     * @{JX.Stratcom.getData()}, or @{JX.Event.getData()}.
     *
     * @param   Node    Node which data should be attached to.
     * @param   object  Data to attach.
     * @return  object  Attached data.
     *
     * @task internal
     */
    _setData : function(node, data) {
      if (!this._data[1]) { // data block 1 is reserved for javascript
        this._data[1] = {};
      }
      this._data[1][this._dataIndex] = data;
      node.className = 'FD_1_' + (this._dataIndex++) + ' ' + node.className;
      return data;
    }
  }
});
/**
 * @provides javelin-behavior
 *
 * @javelin-installs JX.behavior
 * @javelin-installs JX.initBehaviors
 *
 * @javelin
 */

JX.behavior = function(name, control_function) {
  if (__DEV__) {
    if (name in JX.behavior._behaviors) {
      throw new Error(
        'JX.behavior("'+name+'", ...): '+
        'behavior is already registered.');
    }
    if (!control_function) {
      throw new Error(
        'JX.behavior("'+name+'", <nothing>): '+
        'initialization function is required.');
    }
    if (typeof control_function != 'function') {
      throw new Error(
        'JX.behavior("'+name+'", <garbage>): '+
        'initialization function is not a function.');
    }
  }
  JX.behavior._behaviors[name] = control_function;
};


JX.initBehaviors = function(map) {
  for (var name in map) {
    if (__DEV__) {
      if (!(name in JX.behavior._behaviors)) {
        throw new Error(
          'JX.initBehavior("'+name+'", ...): '+
          'behavior is not registered.');
      }
    }
    var configs = map[name];
    if (!configs.length) {
      if (name in JX.behavior._initialized) {
        continue;
      } else {
        configs = [null];
      }
    }
    for (var ii = 0; ii < configs.length; ii++) {
      JX.behavior._behaviors[name](configs[ii]);
    }
    JX.behavior._initialized[name] = true;
  }
};

!function(JX) {
  JX.behavior._behaviors = {};
  JX.behavior._initialized = {};
}(JX);
/**
 * @requires javelin-install
 *           javelin-stratcom
 *           javelin-util
 *           javelin-behavior
 * @provides javelin-request
 * @javelin
 */

/**
 * Make basic AJAX XMLHTTPRequests.
 */
JX.install('Request', {
  construct : function(uri, handler) {
    this.setURI(uri);
    if (handler) {
      this.listen('done', handler);
    }
  },

  events : ['send', 'done', 'error', 'finally'],

  members : {

    _xhrkey : null,
    _transport : null,
    _finished : false,
    _block : null,

    send : function() {
      var xport = null;

      try {
        try {
          xport = new XMLHttpRequest();
        } catch (x) {
          xport = new ActiveXObject("Msxml2.XMLHTTP");
        }
      } catch (x) {
        xport = new ActiveXObject("Microsoft.XMLHTTP");
      }

      this._transport = xport;
      this._xhrkey = JX.Request._xhr.length;
      JX.Request._xhr.push(this);

      xport.onreadystatechange = JX.bind(this, this._onreadystatechange);

      var data = this.getData() || {};
      data.__ajax__ = true;

      this._block = JX.Stratcom.allocateMetadataBlock();
      data.__metablock__ = this._block;

      var q = (this.getDataSerializer() ||
               JX.Request.defaultDataSerializer)(data);
      var uri = this.getURI();
      var method = this.getMethod().toUpperCase();

      if (method == 'GET') {
        uri += ((uri.indexOf('?') === -1) ? '?' : '&') + q;
      }

      this.invoke('send', this);

      if (this.getTimeout()) {
        this._timer = JX.defer(
          JX.bind(
            this,
            this._fail,
            JX.Request.ERROR_TIMEOUT),
          this.getTimeout());
      }

      xport.open(method, uri, true);

      if (__DEV__) {
        if (this.getFile()) {
          if (method != 'POST') {
            throw new Error(
              'JX.Request.send(): ' +
              'attempting to send a file over GET. You must use POST.');
          }
          if (this.getData()) {
            throw new Error(
              'JX.Request.send(): ' +
              'attempting to send data and a file. You can not send both ' +
              'at once.');
          }
        }
      }

      if (method == 'POST') {
        if (this.getFile()) {
          xport.send(this.getFile());
        } else {
          xport.setRequestHeader(
            'Content-Type',
            'application/x-www-form-urlencoded');
          xport.send(q);
        }
      } else {
        xport.send(null);
      }
    },

    abort : function() {
      this._cleanup();
    },

    _onreadystatechange : function() {
      var xport = this._transport;
      try {
        if (this._finished) {
          return;
        }
        if (xport.readyState != 4) {
          return;
        }
        if (xport.status < 200 || xport.status >= 300) {
          this._fail();
          return;
        }

        if (__DEV__) {
          if (!xport.responseText.length) {
            throw new Error(
              'JX.Request("'+this.getURI()+'", ...): '+
              'server returned an empty response.');
          }
          if (xport.responseText.indexOf('for (;;);') != 0) {
            throw new Error(
              'JX.Request("'+this.getURI()+'", ...): '+
              'server returned an invalid response.');
          }
        }

        var text = xport.responseText.substring('for (;;);'.length);
        var response = eval('('+text+')');
      } catch (exception) {

        if (__DEV__) {
          JX.log(
            'JX.Request("'+this.getURI()+'", ...): '+
            'caught exception processing response: '+exception);
        }
        this._fail();
        return;
      }

      try {
        if (response.error) {
          this._fail(response.error);
        } else {
          JX.Stratcom.mergeData(
            this._block,
            response.javelin_metadata || {});
          this._done(response);
          JX.initBehaviors(response.javelin_behaviors || {});
        }
      } catch (exception) {
        //  In Firefox+Firebug, at least, something eats these. :/
        JX.defer(function() {
          throw exception;
        });
      }
    },

    _fail : function(error) {
      this._cleanup();

      this.invoke('error', error, this);
      this.invoke('finally');
    },

    _done : function(response) {
      this._cleanup();

      if (response.onload) {
        for (var ii = 0; ii < response.onload.length; ii++) {
          (new Function(response.onload[ii]))();
        }
      }

      this.invoke('done', this.getRaw() ? response : response.payload, this);
      this.invoke('finally');
    },

    _cleanup : function() {
      this._finished = true;
      delete JX.Request._xhr[this._xhrkey];
      this._timer && this._timer.stop();
      this._transport.abort();
    }

  },

  statics : {
    _xhr : [],
    shutdown : function() {
      for (var ii = 0; ii < JX.Request._xhr.length; ii++) {
        try {
          JX.Request._xhr[ii] && JX.Request._xhr[ii].abort();
        } catch (x) {
          // Ignore.
        }
      }
      JX.Request._xhr = [];
    },
    ERROR_TIMEOUT : -9000,
    defaultDataSerializer : function(data) {
      var uri = [];
      for (var k in data) {
        uri.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
      }
      return uri.join('&');
    }
  },

  properties : {
    URI : null,
    data : null,
    dataSerializer : null,
    /**
     * Configure which HTTP method to use for the request. Permissible values
     * are "POST" (default) or "GET".
     *
     * @param string HTTP method, one of "POST" or "GET".
     */
    method : 'POST',
    file : null,
    raw : false,

    /**
     * Configure a timeout, in milliseconds. If the request has not resolved
     * (either with success or with an error) within the provided timeframe,
     * it will automatically fail with error JX.Request.ERROR_TIMEOUT.
     *
     * @param int Timeout, in milliseconds (e.g. 3000 = 3 seconds).
     */
    timeout : null
  },

  initialize : function() {
    JX.Stratcom.listen('unload', 'tag:window', JX.Request.shutdown);
  }

});

/**
 * @requires javelin-install javelin-event
 * @provides javelin-vector
 * @javelin
 */

/**
 * Query and update positions and dimensions of nodes (and other things)
 * within a document. 'V' stands for 'Vector'. Each vector has two elements,
 * 'x' and 'y', which usually represent width/height (a "dimension vector") or
 * left/top (a "position vector").
 *
 * Vectors are used to manage the sizes and positions of elements, events,
 * the document, and the viewport (the visible section of the document, i.e.
 * how much of the page the user can actually see in their browser window).
 * Unlike most Javelin classes, @{JX.$V} exposes two bare properties, 'x' and
 * 'y'. You can read and manipulate these directly:
 *
 *   // Give the user information about elements when they click on them.
 *   JX.Stratcom.listen(
 *     'click',
 *     null,
 *     function(e) {
 *       var p = JX.$V(e);
 *       var d = JX.$V.getDim(e.getTarget());
 *
 *       alert('You clicked at <'+p.x+','+p.y'>; the element you clicked '+
 *             'is '+d.x+' pixels wide and '+d.y+' pixels high.');
 *     });
 *
 * You can also update positions and dimensions using vectors:
 *
 *   // When the user clicks on something, make it 10px wider and 10px taller.
 *   JX.Stratcom.listen(
 *     'click',
 *     null,
 *     function(e) {
 *       var t = e.getTarget();
 *       JX.$V(t).add(10, 10).setDim(t);
 *     });
 *
 * Additionally, vectors can be used to query document and viewport information:
 *
 *   var v = JX.$V.getViewport(); // Viewport (window) width and height.
 *   var d = JX.$V.getDocument(); // Document width and height.
 *   var visible_area = parseInt(100 * (v.x * v.y) / (d.x * d.y), 10);
 *   alert('You can currently see '+visible_area'+ percent of the document.');
 *
 * @author epriestley
 *
 * @task query  Querying Positions and Dimensions
 * @task update Changing Positions and Dimensions
 * @task manip  Manipulating Vectors
 *
 */
JX.install('$V', {

  /**
   * Construct a vector, either from explicit coordinates or from a node
   * or event. You can pass two Numbers to construct an explicit vector:
   *
   *   var v = JX.$V(35, 42);
   *
   * Otherwise, you can pass a @{JX.Event} or a Node to implicitly construct a
   * vector:
   *
   *   var u = JX.$V(some_event);
   *   var v = JX.$V(some_node);
   *
   * These are just like calling getPos() on the @{JX.Event} or Node.
   *
   * For convenience, @{JX.$V()} constructs a new vector even without the 'new'
   * keyword. That is, these are equivalent:
   *
   *   var q = new JX.$V(x, y);
   *   var r = JX.$V(x, y);
   *
   * Methods like getScroll(), getViewport() and getDocument() also create
   * new vectors.
   *
   * Once you have a vector, you can manipulate it with add():
   *
   *   var u = JX.$V(35, 42);
   *   var v = u.add(5, -12); // v = <40, 30>
   *
   * @param wild      'x' component of the vector, or a @{JX.Event}, or a Node.
   * @param Number?   If providing an 'x' component, the 'y' component of the
   *                  vector.
   * @return @{JX.$V} Specified vector.
   * @task query
   */
  construct : function(x, y) {
    if (this == JX || this == window) {
      return new JX.$V(x, y);
    }
    if (typeof y == 'undefined') {
      return JX.$V.getPos(x);
    }

    this.x = parseFloat(x);
    this.y = parseFloat(y);
  },
  canCallAsFunction : true,
  members : {
    x : null,
    y : null,

    /**
     * Move a node around by setting the position of a Node to the vector's
     * coordinates. For instance, if you want to move an element to the top left
     * corner of the document, you could do this (assuming it has 'position:
     * absolute'):
     *
     *   JX.$V(0, 0).setPos(node);
     *
     * @param Node Node to move.
     * @return this
     * @task update
     */
    setPos : function(node) {
      node.style.left = (this.x === null) ? '' : (parseInt(this.x, 10) + 'px');
      node.style.top  = (this.y === null) ? '' : (parseInt(this.y, 10) + 'px');
      return this;
    },

    /**
     * Change the size of a node by setting its dimensions to the vector's
     * coordinates. For instance, if you want to change an element to be 100px
     * by  100px:
     *
     *   JX.$V(100, 100).setDim(node);
     *
     * Or if you want to expand a node's dimensions by 50px:
     *
     *   JX.$V(node).add(50, 50).setDim(node);
     *
     * @param Node Node to resize.
     * @return this
     * @task update
     */
    setDim : function(node) {
      node.style.width =
        (this.x === null)
          ? ''
          : (parseInt(this.x, 10) + 'px');
      node.style.height =
        (this.y === null)
          ? ''
          : (parseInt(this.y, 10) + 'px');
      return this;
    },

    /**
     * Change a vector's x and y coordinates by adding numbers to them, or
     * adding the coordinates of another vector. For example:
     *
     *   var u = JX.$V(3, 4).add(100, 200); // u = <103, 204>
     *
     * You can also add another vector:
     *
     *   var q = JX.$V(777, 999);
     *   var r = JX.$V(1000, 2000);
     *   var s = q.add(r); // s = <1777, 2999>
     *
     * Note that this method returns a new vector. It does not modify the
     * 'this' vector.
     *
     * @param wild      Value to add to the vector's x component, or another
     *                  vector.
     * @param Number?   Value to add to the vector's y component.
     * @return @{JX.$V} New vector, with summed components.
     * @task manip
     */
    add : function(x, y) {
      if (x instanceof JX.$V) {
        return this.add(x.x, x.y);
      }
      return JX.$V(this.x + parseFloat(x), this.y + parseFloat(y));
    }
  },
  statics : {
    _viewport: null,

    /**
     * Determine where in a document an element is (or where an event, like
     * a click, occurred) by building a new vector containing the position of a
     * Node or @{JX.Event}. The 'x' component of the vector will correspond to
     * the pixel offset of the argument relative to the left edge of the
     * document, and the 'y' component will correspond to the pixel offset of
     * the argument relative to the top edge of the document. Note that all
     * vectors are generated in document coordinates, so the scroll position
     * does not affect them.
     *
     * See also getDim(), used to determine an element's dimensions.
     *
     * @param  Node|@{JX.Event}  Node or event to determine the position of.
     * @return @{JX.$V}          New vector with the argument's position.
     * @task query
     */
    getPos : function(node) {

      JX.Event && (node instanceof JX.Event) && (node = node.getRawEvent());

      if (('pageX' in node) || ('clientX' in node)) {
        var c = JX.$V._viewport;
        return JX.$V(
          node.pageX || (node.clientX + c.scrollLeft),
          node.pageY || (node.clientY + c.scrollTop));
      }

      var x = node.offsetLeft;
      var y = node.offsetTop;
      while (node.offsetParent && (node.offsetParent != document.body)) {
        node = node.offsetParent;
        x += node.offsetLeft;
        y += node.offsetTop;
      }

      return JX.$V(x, y);
    },

    /**
     * Determine the width and height of a node by building a new vector with
     * dimension information. The 'x' component of the vector will correspond
     * to the element's width in pixels, and the 'y' component will correspond
     * to its height in pixels.
     *
     * See also getPos(), used to determine an element's position.
     *
     * @param  Node      Node to determine the display size of.
     * @return @{JX.$V}  New vector with the node's dimensions.
     * @task query
     */
    getDim : function(node) {
      return JX.$V(node.offsetWidth, node.offsetHeight);
    },

    /**
     * Determine the current scroll position by building a new vector where
     * the 'x' component corresponds to how many pixels the user has scrolled
     * from the left edge of the document, and the 'y' component corresponds to
     * how many pixels the user has scrolled from the top edge of the document.
     *
     * See also getViewport(), used to determine the size of the viewport.
     *
     * @return @{JX.$V}  New vector with the document scroll position.
     * @task query
     */
    getScroll : function() {
      //  We can't use $V._viewport here because there's diversity between
      //  browsers with respect to where position/dimension and scroll position
      //  information is stored.
      var b = document.body;
      var e = document.documentElement;
      return JX.$V(b.scrollLeft || e.scrollLeft, b.scrollTop || e.scrollTop);
    },

    /**
     * Determine the size of the viewport (basically, the browser window) by
     * building a new vector where the 'x' component corresponds to the width
     * of the viewport in pixels and the 'y' component corresponds to the height
     * of the viewport in pixels.
     *
     * See also getScroll(), used to determine the position of the viewport, and
     * getDocument(), used to determine the size of the entire document.
     *
     * @return @{JX.$V}  New vector with the viewport dimensions.
     * @task query
     */
    getViewport : function() {
      var c = JX.$V._viewport;
      var w = window;

      return JX.$V(
        w.innerWidth || c.clientWidth || 0,
        w.innerHeight || c.clientHeight || 0
      );
    },

    /**
     * Determine the size of the document, including any area outside the
     * current viewport which the user would need to scroll in order to see, by
     * building a new vector where the 'x' component corresponds to the document
     * width in pixels and the 'y' component corresponds to the document height
     * in pixels.
     *
     * @return @{JX.$V} New vector with the document dimensions.
     * @task query
     */
    getDocument : function() {
      var c = JX.$V._viewport;
      return JX.$V(c.scrollWidth || 0, c.scrollHeight || 0);
    }
  },

  /**
   * On initialization, the browser-dependent viewport root is determined and
   * stored.
   *
   * In ##__DEV__##, @{JX.$V} installs a toString() method so vectors print in a
   * debuggable way:
   *
   *   <23, 92>
   *
   * @return void
   */
  initialize : function() {
    var c = ((c = document) && (c = c.documentElement)) ||
            ((c = document) && (c = c.body))
    JX.$V._viewport = c;

    if (__DEV__) {
      JX.$V.prototype.toString = function() {
        return '<'+this.x+', '+this.y+'>';
      }
    }

  }
});
/**
 * @requires javelin-install javelin-util javelin-vector javelin-stratcom
 * @provides javelin-dom
 *
 * @javelin-installs JX.$
 * @javelin-installs JX.$N
 *
 * @javelin
 */


/**
 * Select an element by its "id" attribute, like ##document.getElementById()##.
 * For example:
 *
 *   var node = JX.$('some_id');
 *
 * This will select the node with the specified "id" attribute:
 *
 *   LANG=HTML
 *   <div id="some_id">...</div>
 *
 * If the specified node does not exist, @{JX.$()} will throw ##JX.$.NotFound##.
 * For other ways to select nodes from the document, see @{JX.DOM.scry()} and
 * @{JX.DOM.find()}.
 *
 * @param  string  "id" attribute to select from the document.
 * @return Node    Node with the specified "id" attribute.
 */
JX.$ = function(id) {

  if (__DEV__) {
    if (!id) {
      throw new Error('Empty ID passed to JX.$()!');
    }
  }

  var node = document.getElementById(id);
  if (!node || (node.id != id)) {
    if (__DEV__) {
      if (node && (node.id != id)) {
        throw new Error(
          'JX.$("'+id+'"): '+
          'document.getElementById() returned an element without the '+
          'correct ID. This usually means that the element you are trying '+
          'to select is being masked by a form with the same value in its '+
          '"name" attribute.');
      }
    }
    throw JX.$.NotFound;
  }

  return node;
};

JX.$.NotFound = {};
if (__DEV__) {
  //  If we're in dev, upgrade this object into an Error so that it will
  //  print something useful if it escapes the stack after being thrown.
  JX.$.NotFound = new Error(
    'JX.$() or JX.DOM.find() call matched no nodes.');
}


/**
 * Upcast a string into an HTML object so it is treated as markup instead of
 * plain text. See @{JX.$N} for discussion of Javelin's security model. Every
 * time you call this function you potentially open up a security hole. Avoid
 * its use wherever possible.
 *
 * This class intentionally supports only a subset of HTML because many browsers
 * named "Internet Explorer" have awkward restrictions around what they'll
 * accept for conversion to document fragments. Alter your datasource to emit
 * valid HTML within this subset if you run into an unsupported edge case. All
 * the edge cases are crazy and you should always be reasonably able to emit
 * a cohesive tag instead of an unappendable fragment.
 *
 * @task build String into HTML
 * @task nodes HTML into Nodes
 */
JX.install('HTML', {

  /**
   * Build a new HTML object from a trustworthy string.
   *
   * @task build
   * @param string A string which you want to be treated as HTML, because you
   *               know it is from a trusted source and any data in it has been
   *               properly escaped.
   * @return JX.HTML HTML object, suitable for use with @{JX.$N}.
   */
  construct : function(str) {
    if (this == JX || this == window) {
      return new JX.HTML(str);
    }

    if (__DEV__) {
      var tags = ['legend', 'thead', 'tbody', 'tfoot', 'column', 'colgroup',
                  'caption', 'tr', 'th', 'td', 'option'];

      var evil_stuff = new RegExp('^\\s*<('+tags.join('|')+')\\b', 'i');
      var match = null;
      if (match = str.match(evil_stuff)) {
        throw new Error(
          'JX.HTML("<'+match[1]+'>..."): '+
          'call initializes an HTML object with an invalid partial fragment '+
          'and can not be converted into DOM nodes. The enclosing tag of an '+
          'HTML content string must be appendable to a document fragment. '+
          'For example, <table> is allowed but <tr> or <tfoot> are not.');
      }

      var really_evil = /<script\b/;
      if (str.match(really_evil)) {
        throw new Error(
          'JX.HTML("...<script>..."): '+
          'call initializes an HTML object with an embedded script tag! '+
          'Are you crazy?! Do NOT do this!!!');
      }

      var wont_work = /<object\b/;
      if (str.match(wont_work)) {
        throw new Error(
          'JX.HTML("...<object>..."): '+
          'call initializes an HTML object with an embedded <object> tag. IE '+
          'will not do the right thing with this.');
      }

      //  TODO(epriestley): May need to deny <option> more broadly, see
      //  http://support.microsoft.com/kb/829907 and the whole mess in the
      //  heavy stack. But I seem to have gotten away without cloning into the
      //  documentFragment below, so this may be a nonissue.
    }

    this._content = str;
  },
  canCallAsFunction : true,
  members : {
    _content : null,
    /**
     * Convert the raw HTML string into a DOM node tree.
     *
     * @task  nodes
     * @return DocumentFragment A document fragment which contains the nodes
     *                          corresponding to the HTML string you provided.
     */
    getFragment : function() {
      var wrapper = JX.$N('div');
      wrapper.innerHTML = this._content;
      var fragment = document.createDocumentFragment();
      while (wrapper.firstChild) {
        //  TODO(epriestley): Do we need to do a bunch of cloning junk here?
        //  See heavy stack. I'm disconnecting the nodes instead; this seems
        //  to work but maybe my test case just isn't extensive enough.
        fragment.appendChild(wrapper.removeChild(wrapper.firstChild));
      }
      return fragment;
    }
  }
});


/**
 * Create a new DOM node with attributes and content.
 *
 *   var link = JX.$N('a');
 *
 * This creates a new, empty anchor tag without any attributes. The equivalent
 * markup would be:
 *
 *   LANG=HTML
 *   <a />
 *
 * You can also specify attributes by passing a dictionary:
 *
 *   JX.$N('a', {name: 'anchor'});
 *
 * This is equivalent to:
 *
 *   LANG=HTML
 *   <a name="anchor" />
 *
 * Additionally, you can specify content:
 *
 *   JX.$N(
 *     'a',
 *     {href: 'http://www.javelinjs.com'},
 *     'Visit the Javelin Homepage');
 *
 * This is equivalent to:
 *
 *   LANG=HTML
 *   <a href="http://www.javelinjs.com">Visit the Javelin Homepage</a>
 *
 * If you only want to specify content, you can omit the attribute parameter.
 * That is, these calls are equivalent:
 *
 *   JX.$N('div', {}, 'Lorem ipsum...'); // No attributes.
 *   JX.$N('div', 'Lorem ipsum...')      // Same as above.
 *
 * Both are equivalent to:
 *
 *   LANG=HTML
 *   <div>Lorem ipsum...</div>
 *
 * Note that the content is treated as plain text, not HTML. This means it is
 * safe to use untrusted strings:
 *
 *   JX.$N('div', '<script src="evil.com" />');
 *
 * This is equivalent to:
 *
 *   LANG=HTML
 *   <div>&lt;script src="evil.com" /&gt;</div>
 *
 * That is, the content will be properly escaped and will not create a
 * vulnerability. If you want to set HTML content, you can use @{JX.HTML}:
 *
 *   JX.$N('div', JX.HTML(some_html));
 *
 * **This is potentially unsafe**, so make sure you understand what you're
 * doing. You should usually avoid passing HTML around in string form. See
 * @{JX.HTML} for discussion.
 *
 * You can create new nodes with a Javelin sigil (and, optionally, metadata) by
 * providing "sigil" and "metadata" keys in the attribute dictionary.
 *
 * @param string                  Tag name, like 'a' or 'div'.
 * @param dict|string|@{JX.HTML}? Property dictionary, or content if you don't
 *                                want to specify any properties.
 * @param string|@{JX.HTML}?      Content string (interpreted as plain text)
 *                                or @{JX.HTML} object (interpreted as HTML,
 *                                which may be dangerous).
 * @return Node                   New node with whatever attributes and
 *                                content were specified.
 */
JX.$N = function(tag, attr, content) {
  if (typeof content == 'undefined' &&
      (typeof attr != 'object' || attr instanceof JX.HTML)) {
    content = attr;
    attr = {};
  }

  if (__DEV__) {
    if (tag.toLowerCase() != tag) {
      throw new Error(
        '$N("'+tag+'", ...): '+
        'tag name must be in lower case; '+
        'use "'+tag.toLowerCase()+'", not "'+tag+'".');
    }
  }

  var node = document.createElement(tag);

  if (attr.style) {
    JX.copy(node.style, attr.style);
    delete attr.style;
  }

  if (attr.sigil) {
    JX.Stratcom.sigilize(node, attr.sigil, attr.meta);
    delete attr.sigil;
    delete attr.meta;
  }

  if (__DEV__) {
    if (('metadata' in attr) || ('data' in attr)) {
      throw new Error(
        '$N(' + tag + ', ...): ' +
        'use the key "meta" to specify metadata, not "data" or "metadata".');
    }
    if (attr.meta) {
      throw new Error(
        '$N(' + tag + ', ...): ' +
        'if you specify "meta" metadata, you must also specify a "sigil".');
    }
  }

  // prevent sigil from being wiped by blind copying the className
  if (attr.className) {
    JX.DOM.alterClass(node, attr.className, true);
    delete attr.className;
  }

  JX.copy(node, attr);
  if (content) {
    JX.DOM.setContent(node, content);
  }
  return node;
};


/**
 * Query and update the DOM. Everything here is static, this is essentially
 * a collection of common utility functions.
 *
 * @task stratcom Attaching Event Listeners
 * @task content Changing DOM Content
 * @task nodes Updating Nodes
 * @task test Testing DOM Properties
 * @task convenience Convenience Methods
 * @task query Finding Nodes in the DOM
 * @task view Changing View State
 */
JX.install('DOM', {
  statics : {
    _autoid : 0,
    _metrics : {},

    /**
     * @task content
     */
    setContent : function(node, content) {
      if (__DEV__) {
        if (!JX.DOM.isNode(node)) {
          throw new Error(
            'JX.DOM.setContent(<yuck>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      while (node.firstChild) {
        JX.DOM.remove(node.firstChild);
      }
      JX.DOM.appendContent(node, content);
    },


    /**
     * @task content
     */
    prependContent : function(node, content) {
      if (__DEV__) {
        if (!JX.DOM.isNode(node)) {
          throw new Error(
            'JX.DOM.prependContent(<junk>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      this._insertContent(node, content, this._mechanismPrepend);
    },


    /**
     * @task content
     */
    appendContent : function(node, content) {
      if (__DEV__) {
        if (!JX.DOM.isNode(node)) {
          throw new Error(
            'JX.DOM.appendContent(<bleh>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      this._insertContent(node, content, this._mechanismAppend);
    },


    /**
     * @task content
     */
    _mechanismPrepend : function(node, content) {
      node.insertBefore(content, node.firstChild);
    },


    /**
     * @task content
     */
    _mechanismAppend : function(node, content) {
      node.appendChild(content);
    },


    /**
     * @task content
     */
    _insertContent : function(parent, content, mechanism) {
      if (content === null || typeof content == 'undefined') {
        return;
      }
      if (content instanceof JX.HTML) {
        content = content.getFragment();
      }
      if (content instanceof Array) {
        for (var ii = 0; ii < content.length; ii++) {
          var child = (typeof content[ii] == 'string')
            ? document.createTextNode(content[ii])
            : content[ii];
          mechanism(parent, child);
        }
      } else if (content.nodeType) {
        mechanism(parent, content);
      } else {
        mechanism(parent, document.createTextNode(content));
      }
    },


    /**
     * @task nodes
     */
    remove : function(node) {
      node.parentNode && JX.DOM.replace(node, null);
      return node;
    },


    /**
     * @task nodes
     */
    replace : function(node, replacement) {
      if (__DEV__) {
        if (!node.parentNode) {
          throw new Error(
            'JX.DOM.replace(<node>, ...): '+
            'node has no parent node, so it can not be replaced.');
        }
      }

      var mechanism;
      if (node.nextSibling) {
        mechanism = JX.bind(node.nextSibling, function(parent, content) {
          parent.insertBefore(content, this);
        });
      } else {
        mechanism = this._mechanismAppend;
      }
      var parent = node.parentNode;
      node.parentNode.removeChild(node);
      this._insertContent(parent, replacement, mechanism);

      return node;
    },


    /**
     * Retrieve the nearest parent node matching the desired sigil.
     * @param  Node The child element to search from
     * @return  The matching parent or null if no parent could be found
     * @author jgabbard
     */
    nearest : function(node, sigil) {
      while (node && !JX.Stratcom.hasSigil(node, sigil)) {
        node = node.parentNode;
      }
      return node;
    },


    serialize : function(form) {
      var elements = form.getElementsByTagName('*');
      var data = {};
      for (var ii = 0; ii < elements.length; ++ii) {
        if (!elements[ii].name) {
          continue;
        }
        var type = elements[ii].type;
        var tag  = elements[ii].tagName;
        if ((type in {radio: 1, checkbox: 1} && elements[ii].checked) ||
             type in {text: 1, hidden: 1, password: 1} ||
              tag in {TEXTAREA: 1, SELECT: 1}) {
          data[elements[ii].name] = elements[ii].value;
        }
      }
      return data;
    },


    /**
     * Test if an object is a valid Node.
     *
     * @task test
     * @param wild Something which might be a Node.
     * @return bool True if the parameter is a DOM node.
     */
    isNode : function(node) {
      return !!(node && node.nodeName && (node !== window));
    },


    /**
     * Test if an object is a node of some specific (or one of several) types.
     * For example, this tests if the node is an ##<input />##, ##<select />##,
     * or ##<textarea />##.
     *
     *   JX.DOM.isType(node, ['input', 'select', 'textarea']);
     *
     * @task    test
     * @param   wild        Something which might be a Node.
     * @param   string|list One or more tags which you want to test for.
     * @return  bool        True if the object is a node, and it's a node of one
     *                      of the provided types.
     */
    isType : function(node, of_type) {
      node = ('' + (node.nodeName || '')).toUpperCase();
      of_type = JX.$AX(of_type);
      for (var ii = 0; ii < of_type.length; ++ii) {
        if (of_type[ii].toUpperCase() == node) {
          return true;
        }
      }
      return false;
    },

    /**
     * Listen for events occuring beneath a specific node in the DOM. This is
     * similar to @{JX.Stratcom.listen()}, but allows you to specify some node
     * which serves as a scope instead of the default scope (the whole document)
     * which you get if you install using @{JX.Stratcom.listen()} directly. For
     * example, to listen for clicks on nodes with the sigil 'menu-item' below
     * the root menu node:
     *
     *   var the_menu = getReferenceToTheMenuNodeSomehow();
     *   JX.DOM.listen(the_menu, 'click', 'menu-item', function(e) { ... });
     *
     * @task stratcom
     * @param Node        The node to listen for events underneath.
     * @param string|list One or more event types to listen for.
     * @param list?       A path to listen on.
     * @param function    Callback to invoke when a matching event occurs.
     * @return object     A reference to the installed listener. You can later
     *                    remove the listener by calling this object's remove()
     *                    method.
     */
    listen : function(node, type, path, callback) {
      return JX.Stratcom.listen(
        type,
        ['id:'+JX.DOM.uniqID(node)].concat(JX.$AX(path || [])),
        callback);
    },

    uniqID : function(node) {
      if (!node.id) {
        node.id = 'autoid_'+(++JX.DOM._autoid);
      }
      return node.id;
    },

    alterClass : function(node, className, add) {
      var has = ((' '+node.className+' ').indexOf(' '+className+' ') > -1);
      if (add && !has) {
        node.className += ' '+className;
      } else if (has && !add) {
        node.className = node.className.replace(
          new RegExp('(^|\\s)' + className + '(?:\\s|$)', 'g'), ' ');
      }
    },

    htmlize : function(str) {
      return (''+str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    },


    /**
     * Show one or more elements, by removing their "display" style. This
     * assumes you have hidden them with hide(), or explicitly set the style
     * to "display: none;".
     *
     * @task convenience
     * @param ... One or more nodes to remove "display" styles from.
     * @return void
     */
    show : function() {
      if (__DEV__) {
        for (var ii = 0; ii < arguments.length; ++ii) {
          if (!arguments[ii]) {
            throw new Error(
              'JX.DOM.show(...): ' +
              'one or more arguments were null or empty.');
          }
        }
      }

      for (var ii = 0; ii < arguments.length; ++ii) {
        arguments[ii].style.display = '';
      }
    },


    /**
     * Hide one or more elements, by setting "display: none;" on them. This is
     * a convenience method. See also show().
     *
     * @task convenience
     * @param ... One or more nodes to set "display: none" on.
     * @return void
     */
    hide : function() {
      if (__DEV__) {
        for (var ii = 0; ii < arguments.length; ++ii) {
          if (!arguments[ii]) {
            throw new Error(
              'JX.DOM.hide(...): ' +
              'one or more arguments were null or empty.');
          }
        }
      }

      for (var ii = 0; ii < arguments.length; ++ii) {
        arguments[ii].style.display = 'none';
      }
    },

    textMetrics : function(node, pseudoclass, x) {
      if (!this._metrics[pseudoclass]) {
        var n = JX.$N(
          'var',
          {className: pseudoclass});
        this._metrics[pseudoclass] = n;
      }
      var proxy = this._metrics[pseudoclass];
      document.body.appendChild(proxy);
        proxy.style.width = x ? (x+'px') : '';
        JX.DOM.setContent(
          proxy,
          JX.HTML(JX.DOM.htmlize(node.value).replace(/\n/g, '<br />')));
        var metrics = JX.$V.getDim(proxy);
      document.body.removeChild(proxy);
      return metrics;
    },


    /**
     * Search the document for DOM nodes by providing a root node to look
     * beneath, a tag name, and (optionally) a sigil. Nodes which match all
     * specified conditions are returned.
     *
     * @task query
     *
     * @param  Node    Root node to search beneath.
     * @param  string  Tag name, like 'a' or 'textarea'.
     * @param  string  Optionally, a sigil which nodes are required to have.
     *
     * @return list    List of matching nodes, which may be empty.
     */
    scry : function(root, tagname, sigil) {
      if (__DEV__) {
        if (!JX.DOM.isNode(root)) {
          throw new Error(
            'JX.DOM.scry(<yuck>, ...): '+
            'first argument must be a DOM node.');
        }
      }

      var nodes = root.getElementsByTagName(tagname);
      if (!sigil) {
        return JX.$A(nodes);
      }
      var result = [];
      for (var ii = 0; ii < nodes.length; ii++) {
        if (JX.Stratcom.hasSigil(nodes[ii], sigil)) {
          result.push(nodes[ii]);
        }
      }
      return result;
    },


    /**
     * Select a node uniquely identified by a root, tagname and sigil. This
     * is similar to JX.DOM.scry() but expects exactly one result. It will
     * throw JX.$.NotFound if it matches no results.
     *
     * @task query
     *
     * @param  Node    Root node to search beneath.
     * @param  string  Tag name, like 'a' or 'textarea'.
     * @param  string  Optionally, sigil which selected node must have.
     *
     * @return Node    Node uniquely identified by the criteria.
     */
    find : function(root, tagname, sigil) {
      if (__DEV__) {
        if (!JX.DOM.isNode(root)) {
          throw new Error(
            'JX.DOM.find(<glop>, "'+tagname+'", "'+sigil+'"): '+
            'first argument must be a DOM node.');
        }
      }

      var result = JX.DOM.scry(root, tagname, sigil);

      if (__DEV__) {
        if (result.length > 1) {
          throw new Error(
            'JX.DOM.find(<node>, "'+tagname+'", "'+sigil+'"): '+
            'matched more than one node.');
        }
      }

      if (!result.length) {
        throw JX.$.NotFound;
      }

      return result[0];
    },


    /**
     * Focus a node safely. This is just a convenience wrapper that allows you
     * to avoid IE's habit of throwing when nearly any focus operation is
     * invoked.
     *
     * @task convenience
     * @param Node Node to move cursor focus to, if possible.
     * @return void
     */
    focus : function(node) {
      try { node.focus(); } catch (lol_ie) {}
    },

    /**
     * Scroll to the position of an element in the document.
     * @task view
     * @param Node Node to move document scroll position to, if possible.
     * @return void
     */
     scrollTo : function(node) {
       window.scrollTo(0, JX.$V(node).y);
     }
  }
});

/**
 *  Simple JSON serializer.
 *
 *  @requires javelin-install javelin-util
 *  @provides javelin-json
 *  @javelin
 */

JX.install('JSON', {
  statics : {
    serialize : function(obj) {
      if (__DEV__) {
        try {
          return JX.JSON._val(obj);
        } catch (x) {
          JX.log(
            'JX.JSON.serialize(...): '+
            'caught exception while serializing object. ('+x+')');
        }
      } else {
        return JX.JSON._val(obj);
      }
    },
    _val : function(val) {
      var out = [];
      if (val === null) {
        return 'null';
      } else if (val.push && val.pop) {
        for (var ii = 0; ii < val.length; ii++) {
          if (typeof val[ii] != 'undefined') {
            out.push(JX.JSON._val(val[ii]));
          }
        }
        return '['+out.join(',')+']';
      } else if (val === true) {
        return 'true';
      } else if (val === false) {
        return 'false';
      } else if (typeof val == 'string') {
        return JX.JSON._esc(val);
      } else if (typeof val == 'number') {
        return val;
      } else {
        for (var k in val) {
          out.push(JX.JSON._esc(k)+':'+JX.JSON._val(val[k]));
        }
        return '{'+out.join(',')+'}';
      }
    },
    _esc : function(str) {
      return '"'+str.replace(/\\/g, '\\\\').replace(/"/g, '\\"')+'"';
    }
  }
});
