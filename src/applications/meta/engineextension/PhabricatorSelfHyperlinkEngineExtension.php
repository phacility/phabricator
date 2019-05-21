<?php

final class PhabricatorSelfHyperlinkEngineExtension
  extends PhabricatorRemarkupHyperlinkEngineExtension {

  const LINKENGINEKEY = 'phabricator-self';

  public function processHyperlinks(array $hyperlinks) {
    $engine = $this->getEngine();
    $viewer = $engine->getConfig('viewer');

    // If we don't have a valid viewer, just bail out. We aren't going to be
    // able to do very much.
    if (!$viewer) {
      return;
    }

    $self_links = $this->getSelfLinks($hyperlinks);

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
      $object_phids = mpull($object_map, 'getPHID');
    } else {
      $object_phids = array();
    }

    $handles = $viewer->loadHandles($object_phids);

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

    $key_mentioned = PhabricatorObjectRemarkupRule::KEY_MENTIONED_OBJECTS;
    $mentioned_phids = $engine->getTextMetadata($key_mentioned, array());
    foreach ($object_phids as $object_phid) {
      $mentioned_phids[$object_phid] = $object_phid;
    }
    $engine->setTextMetadata($key_mentioned, $mentioned_phids);
  }

}
