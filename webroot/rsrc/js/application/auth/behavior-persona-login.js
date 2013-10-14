/**
 * @provides javelin-behavior-persona-login
 * @requires javelin-behavior
 *           javelin-resource
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 */

JX.behavior('persona-login', function(config) {

  JX.Stratcom.listen(
    'submit',
    'persona-login-form',
    function(e) {
      e.kill();
      navigator.id.request();
    });

  var onloaded = function() {
    // Before installing watch(), log the user out, because we know they don't
    // have a valid session if they're hitting this page. If we don't do this,
    // Persona may immediately trigger a login event, which prevents the user
    // from selecting another authentication mechanism.
    navigator.id.logout();

    navigator.id.watch({
      loggedInUser: null,
      onlogin: onlogin,
      onlogout: JX.bag
    });
  };

  var onlogin = function(assertion) {
    new JX.Workflow(config.loginURI, {assertion: assertion})
      .start();
  };

  var persona_library = 'https://login.persona.org/include.js';
  JX.Resource.load(persona_library, onloaded);
});
