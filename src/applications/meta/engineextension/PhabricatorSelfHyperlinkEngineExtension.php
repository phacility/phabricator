<?php

final class PhabricatorSelfHyperlinkEngineExtension
  extends PhutilRemarkupHyperlinkEngineExtension {

  const LINKENGINEKEY = 'phabricator-self';

  public function processHyperlinks(array $hyperlinks) {
    $engine = $this->getEngine();
    $viewer = $engine->getConfig('viewer');

    // If we don't have a valid viewer, just bail out. We aren't going to be
    // able to do very much.
    if (!$viewer) {
      return;
    }

    // Find links which point to resources on the Phabricator install itself.
    // We're going to try to enhance these.
    $self_links = array();
    foreach ($hyperlinks as $link) {
      $uri = $link->getURI();
      if (PhabricatorEnv::isSelfURI($uri)) {
        $self_links[] = $link;
      }
    }

    // For links in the form "/X123", we can reasonably guess that they are
    // fairly likely to be object names. Try to look them up.
    $object_names = array();
    foreach ($self_links as $key => $link) {
      $uri = new PhutilURI($link->getURI());

      $matches = null;
      $path = $uri->getPath();
      if (!preg_match('(^/([^/]+)\z)', $path, $matches)) {
        continue;
      }

      $object_names[$key] = $matches[1];
    }

    if ($object_names) {
      $object_query = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withNames($object_names);

      $object_query->execute();

      $object_map = $object_query->getNamedResults();
    } else {
      $object_map = array();
    }

    if ($object_map) {
      $handles = $viewer->loadHandles(mpull($object_map, 'getPHID'));
    } else {
      $handles = array();
    }

    foreach ($object_names as $key => $object_name) {
      $object = idx($object_map, $object_name);
      if (!$object) {
        continue;
      }

      $phid = $object->getPHID();
      $handle = $handles[$phid];

      $link = $self_links[$key];
      $raw_uri = $link->getURI();
      $is_embed = $link->isEmbed();

      $tag = $handle->renderTag()
        ->setPHID($phid)
        ->setHref($raw_uri);

      if (!$is_embed) {
        $tag->setName($raw_uri);
      }

      $link->setResult($tag);

      unset($self_links[$key]);
    }
  }

}
