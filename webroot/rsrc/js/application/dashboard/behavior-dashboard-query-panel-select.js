/**
 * @provides javelin-behavior-dashboard-query-panel-select
 * @requires javelin-behavior
 *           javelin-dom
 */

/**
 * When editing a "Query" panel on dashboards, make the "Query" selector control
 * dynamically update in response to changes to the "Engine" selector control.
 */
JX.behavior('dashboard-query-panel-select', function(config) {

  var app_control = JX.$(config.applicationID);
  var query_control = JX.$(config.queryID);

  // If we have a currently-selected query, add it to the appropriate group
  // in the options list if it does not already exist.
  if (config.value.key !== null) {
    var app = app_control.value;
    if (!(app in config.options)) {
      config.options[app] = [];
    }

    var found = false;
    for (var ii = 0; ii < config.options[app].length; ii++) {
      if (config.options[app][ii].key == config.value.key) {
        found = true;
        break;
      }
    }

    if (!found) {
      config.options[app] = [config.value].concat(config.options[app]);
    }
  }

  // When the user changes the selected search engine, update the query
  // control to show available queries for that engine.
  function update() {
    var app = app_control.value;

    var old_value = query_control.value;
    var new_value = null;

    var options = config.options[app] || [];
    var nodes = [];
    for (var ii = 0; ii < options.length; ii++) {
      if (new_value === null) {
        new_value = options[ii].key;
      }
      if (options[ii].key == old_value) {
        new_value = options[ii].key;
      }
      nodes.push(JX.$N('option', {value: options[ii].key}, options[ii].name));
    }

    JX.DOM.setContent(query_control, nodes);
    query_control.value = new_value;
  }

  JX.DOM.listen(app_control, 'change', null, function() { update(); });
  update();

});
