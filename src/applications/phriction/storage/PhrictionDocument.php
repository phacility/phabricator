<?php

/**
 * @group phriction
 */
final class PhrictionDocument extends PhrictionDAO {

  protected $id;
  protected $phid;
  protected $slug;
  protected $depth;
  protected $contentID;
  protected $status;

  private $contentObject;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_WIKI);
  }

  public static function getSlugURI($slug, $type = 'document') {
    static $types = array(
      'document'  => '/w/',
      'history'   => '/phriction/history/',
    );

    if (empty($types[$type])) {
      throw new Exception("Unknown URI type '{$type}'!");
    }

    $prefix = $types[$type];

    if ($slug == '/') {
      return $prefix;
    } else {
      return $prefix.$slug;
    }
  }

  public function setSlug($slug) {
    $this->slug   = PhabricatorSlug::normalize($slug);
    $this->depth  = PhabricatorSlug::getDepth($slug);
    return $this;
  }

  public function attachContent(PhrictionContent $content) {
    $this->contentObject = $content;
    return $this;
  }

  public function getContent() {
    if (!$this->contentObject) {
      throw new Exception("Attach content with attachContent() first.");
    }
    return $this->contentObject;
  }

  public static function isProjectSlug($slug) {
    $slug = PhabricatorSlug::normalize($slug);
    $prefix = 'projects/';
    if ($slug == $prefix) {
      // The 'projects/' document is not itself a project slug.
      return false;
    }
    return !strncmp($slug, $prefix, strlen($prefix));
  }

  public static function getProjectSlugIdentifier($slug) {
    if (!self::isProjectSlug($slug)) {
      throw new Exception("Slug '{$slug}' is not a project slug!");
    }

    $slug = PhabricatorSlug::normalize($slug);
    $parts = explode('/', $slug);
    return $parts[1].'/';
  }

}
