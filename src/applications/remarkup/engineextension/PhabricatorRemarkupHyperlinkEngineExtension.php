<?php

abstract class PhabricatorRemarkupHyperlinkEngineExtension
  extends PhutilRemarkupHyperlinkEngineExtension {

  final protected function getSelfLinks(array $hyperlinks) {
    assert_instances_of($hyperlinks, 'PhutilRemarkupHyperlinkRef');

    $allowed_protocols = array(
      'http' => true,
      'https' => true,
    );

    $results = array();
    foreach ($hyperlinks as $link) {
      $uri = $link->getURI();

      if (!PhabricatorEnv::isSelfURI($uri)) {
        continue;
      }

      $protocol = id(new PhutilURI($uri))->getProtocol();
      if (!isset($allowed_protocols[$protocol])) {
        continue;
      }

      $results[] = $link;
    }

    return $results;
  }
}
