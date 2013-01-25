/**
 * @provides javelin-behavior-conpherence-init
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 */
JX.behavior('conpherence-init', function(config) {
  // select the current message
  var selectedConpherence = false;
  if (config.selected_conpherence_id) {
    var selected = JX.$(config.selected_conpherence_id);
    JX.Stratcom.invoke(
      'conpherence-initial-selected',
      null,
      { selected : selected }
    );
    selectedConpherence = true;
  }

});
