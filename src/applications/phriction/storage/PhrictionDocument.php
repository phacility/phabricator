<?php

/**
 * @group phriction
 */
final class PhrictionDocument extends PhrictionDAO
  implements PhabricatorPolicyInterface {

  protected $id;
  protected $phid;
  protected $slug;
  protected $depth;
  protected $contentID;
  protected $status;

  private $contentObject;
  private $project;

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
      // NOTE: The effect here is to escape non-latin characters, since modern
      // browsers deal with escaped UTF8 characters in a reasonable way (showing
      // the user a readable URI) but older programs may not.
      $slug = phutil_escape_uri($slug);
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

  public function getProject() {
    if ($this->project === null) {
      throw new Exception("Call attachProject() before getProject().");
    }
    return $this->project;
  }

  public function attachProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  public function hasProject() {
    return (bool)$this->project;
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

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    if ($this->hasProject()) {
      return $this->getProject()->getPolicy($capability);
    }
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    if ($this->hasProject()) {
      return $this->getProject()->hasAutomaticCapability($capability, $user);
    }
    return false;
  }
}
