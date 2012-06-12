/**
 * @provides javelin-behavior-aphlict-listen
 * @requires javelin-behavior
 *           javelin-aphlict
 *           javelin-util
 *           javelin-stratcom
 *           javelin-behavior-aphlict-dropdown
 */

JX.behavior('aphlict-listen', function(config) {
  function onready() {
    var client = new JX.Aphlict(config.id, config.server, config.port)
      .setHandler(function(type, message) {
        if (message) {
          if (type == 'receive') {
            var request = new JX.Request('/notification/individual/',
              function(response) {
                if (response.pertinent) {
                  JX.Stratcom.invoke('notification-panel-update', null, {});
                }
              });
            request.addData({ "key": message.key });
            request.send();
          }
        }
      })
      .start();
  }


  // Wait for the element to load, and don't do anything if it never loads.
  // If we just go crazy and start making calls to it before it loads, its
  // interfaces won't be registered yet.
  JX.Stratcom.listen('aphlict-component-ready', null, onready);

  // Add Flash object to page
  JX.$("aphlictswf-container").innerHTML =
    '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000">'
    + '<param name="movie" value="/rsrc/swf/aphlict.swf" />'
    + '<param name="allowScriptAccess" value="always" />'
    + '<param name="wmode" value="opaque" />'
    + '<embed src="/rsrc/swf/aphlict.swf" wmode="opaque"'
      + 'width="0" height="0" id="aphlictswfobject">'
    + '</embed></object>'; //Evan sanctioned
});
