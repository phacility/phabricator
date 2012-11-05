<?php

final class PhabricatorFileProxyImage extends PhabricatorFileDAO {

  protected $uri;
  protected $filePHID;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  static public function getProxyImageURI($uri) {
    return '/file/proxy/?uri='.phutil_escape_uri($uri);
  }
}

