<?php

final class PhabricatorMarkupCache extends PhabricatorCacheDAO {

  protected $cacheKey;
  protected $cacheData;
  protected $metadata;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'cacheData' => self::SERIALIZATION_PHP,
        'metadata'  => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_BINARY => array(
        'cacheData' => true,
      ),
    ) + parent::getConfiguration();
  }

}
