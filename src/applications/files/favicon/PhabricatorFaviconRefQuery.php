<?php

final class PhabricatorFaviconRefQuery extends Phobject {

  private $refs;

  public function withRefs(array $refs) {
    assert_instances_of($refs, 'PhabricatorFaviconRef');
    $this->refs = $refs;
    return $this;
  }

  public function execute() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $refs = $this->refs;

    $config_digest = PhabricatorFaviconRef::newConfigurationDigest();

    $ref_map = array();
    foreach ($refs as $ref) {
      $ref_digest = $ref->newDigest();
      $ref_key = "favicon({$config_digest},{$ref_digest},8)";

      $ref
        ->setViewer($viewer)
        ->setCacheKey($ref_key);

      $ref_map[$ref_key] = $ref;
    }

    $cache = PhabricatorCaches::getImmutableCache();
    $ref_hits = $cache->getKeys(array_keys($ref_map));

    foreach ($ref_hits as $ref_key => $ref_uri) {
      $ref_map[$ref_key]->setURI($ref_uri);
      unset($ref_map[$ref_key]);
    }

    if ($ref_map) {
      $new_map = array();
      foreach ($ref_map as $ref_key => $ref) {
        $ref_uri = $ref->newURI();
        $ref->setURI($ref_uri);
        $new_map[$ref_key] = $ref_uri;
      }

      $cache->setKeys($new_map);
    }

    return $refs;
  }


}
