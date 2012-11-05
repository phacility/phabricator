<?php

final class DifferentialComment extends DifferentialDAO
  implements PhabricatorMarkupInterface {

  const METADATA_ADDED_REVIEWERS   = 'added-reviewers';
  const METADATA_REMOVED_REVIEWERS = 'removed-reviewers';
  const METADATA_ADDED_CCS         = 'added-ccs';
  const METADATA_DIFF_ID           = 'diff-id';

  const MARKUP_FIELD_BODY          = 'markup:body';

  protected $authorPHID;
  protected $revisionID;
  protected $action;
  protected $content;
  protected $cache;
  protected $metadata = array();
  protected $contentSource;

  private $arbitraryDiffForFacebook;

  public function giveFacebookSomeArbitraryDiff(DifferentialDiff $diff) {
    $this->arbitraryDiffForFacebook = $diff;
    return $this;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source->serialize();
    return $this;
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
  }


  public function getMarkupFieldKey($field) {
    if ($this->getID()) {
      return 'DC:'.$this->getID();
    }

    // The summary and test plan render as comments, but do not have IDs.
    // They are also mutable. Build keys using content hashes.
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    return 'DC:'.$hash;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDifferentialMarkupEngine(
      array(
        'differential.diff' => $this->arbitraryDiffForFacebook,
      ));
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    if ($this->getID()) {
      return true;
    }

    $action = $this->getAction();
    switch ($action) {
      case DifferentialAction::ACTION_SUMMARIZE:
      case DifferentialAction::ACTION_TESTPLAN:
        return true;
    }

    return false;
  }

}
