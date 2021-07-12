/**
 * @provides bmo-auth-js
 */

(function() {
  // Display the admin login form if "?admin"
  if((new URLSearchParams(window.location.search)).has('admin')) {
    document.querySelector('form[action="/auth/login/password:self/"]').style.display = 'block';
  }
})();
