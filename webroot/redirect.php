<?php

// This is a simple PHP file that allows you to link to files
// in Phabricator with a syntax like:
//
// http://phabricator.dev/redirect.php?url=master@TFSBETA:main.proj
//
// This is useful on wikis like Dokuwiki, where you want to present
// the link as a special link with the name as just "master@TFSBETA:main.proj",
// but then want to actually link to that file on that branch.

$url = $_GET["url"];
preg_match('/([a-z0-9-]*)@([A-Z]+):(.*)/', $url, $matches);
if ($matches[1] == "")
    header("Location: /diffusion/" . $matches[2] . "/browse/master/" . $matches[3]);
else
    header("Location: /diffusion/" . $matches[2] . "/browse/" . $matches[1] . "/" . $matches[3]);

?>
