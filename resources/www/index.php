<?php

/**
 * This is the source for <http://www.phabricator.com/>.
 */

$path = $_REQUEST['__path__'];
$host = $_SERVER['HTTP_HOST'];
if ($host == 'secure.phabricator.com') {
  // If the user is requesting a secure.phabricator.com resource over HTTP,
  // redirect them to HTTPS.
  header('Location: https://secure.phabricator.com/'.$path);
}

?>
<!doctype html>
  <head>
    <title>Phabricator</title>
  </head>
  <body>
    <h1>Phabricator</h1>
    <ul>
      <li><a href="https://secure.phabricator.com/">Phabricator	Install for Phabricator Development</a></li>
      <li><a href="docs/libphutil/">Libphutil Docs</a></li>
      <li><a href="docs/arcanist/">Arcanist Docs</a></li>
      <li><a href="docs/phabricator/">Phabricator Docs</a></li>
      <li><a href="docs/javelin/">Javelin Docs</a></li>
    </ul>
  </body> 
</html>
