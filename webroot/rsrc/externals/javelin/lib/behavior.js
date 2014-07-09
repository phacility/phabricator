/**
 * @provides javelin-behavior
 * @requires javelin-magical-init
 *           javelin-util
 *
 * @javelin-installs JX.behavior
 * @javelin-installs JX.initBehaviors
 *
 * @javelin
 */

/**
 * Define a Javelin behavior, which holds glue code in a structured way. See
 * @{article:Concepts: Behaviors} for a detailed description of Javelin
 * behaviors.
 *
 * To define a behavior, provide a name and a function:
 *
 *   JX.behavior('win-a-hog', function(config, statics) {
 *     alert("YOU WON A HOG NAMED " + config.hogName + "!");
 *   });
 *
 * @param string    Behavior name.
 * @param function  Behavior callback/definition.
 * @return void
 */
JX.behavior = function(name, control_function) {
  if (__DEV__) {
    if (JX.behavior._behaviors.hasOwnProperty(name)) {
      JX.$E(
        'JX.behavior("' + name + '", ...): '+
        'behavior is already registered.');
    }
    if (!control_function) {
      JX.$E(
        'JX.behavior("' + name + '", <nothing>): '+
        'initialization function is required.');
    }
    if (typeof control_function != 'function') {
      JX.$E(
        'JX.behavior("' + name + '", <garbage>): ' +
        'initialization function is not a function.');
    }
    // IE does not enumerate over these properties
    var enumerables = {
      toString: true,
      hasOwnProperty: true,
      valueOf: true,
      isPrototypeOf: true,
      propertyIsEnumerable: true,
      toLocaleString: true,
      constructor: true
    };
    if (enumerables[name]) {
      JX.$E(
        'JX.behavior("' + name + '", <garbage>): ' +
        'do not use this property as a behavior.'
      );
    }
  }
  JX.behavior._behaviors[name] = control_function;
  JX.behavior._statics[name] = {};
};


/**
 * Execute previously defined Javelin behaviors, running the glue code they
 * contain to glue stuff together. See @{article:Concepts: Behaviors} for more
 * information on Javelin behaviors.
 *
 * Normally, you do not call this function yourself; instead, your server-side
 * library builds it for you.
 *
 * @param dict  Map of behaviors to invoke: keys are behavior names, and values
 *              are lists of configuration dictionaries. The behavior will be
 *              invoked once for each configuration dictionary.
 * @return void
 */
JX.initBehaviors = function(map) {
  var missing_behaviors = [];
  for (var name in map) {
    if (!(name in JX.behavior._behaviors)) {
      missing_behaviors.push(name);
      continue;
    }
    var configs = map[name];
    if (!configs.length) {
      if (JX.behavior._initialized.hasOwnProperty(name)) {
        continue;
      }
      configs = [null];
    }
    for (var ii = 0; ii < configs.length; ii++) {
      JX.behavior._behaviors[name](configs[ii], JX.behavior._statics[name]);
    }
    JX.behavior._initialized[name] = true;
  }
  if (missing_behaviors.length) {
    JX.$E(
      'JX.initBehavior(map): behavior(s) not registered: ' +
      missing_behaviors.join(', ')
    );
  }
};

JX.behavior._behaviors = {};
JX.behavior._statics = {};
JX.behavior._initialized = {};
JX.flushHoldingQueue('behavior', JX.behavior);
