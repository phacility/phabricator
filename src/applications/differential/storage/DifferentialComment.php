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

  public function setRevision(DifferentialRevision $revision) {
    return $this->setRevisionID($revision->getID());
  }

  public function giveFacebookSomeArbitraryDiff(DifferentialDiff $diff) {
    $this->arbitraryDiffForFacebook = $diff;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();

    $metadata = $this->getMetadata();
    $added_reviewers = idx(
      $metadata,
      self::METADATA_ADDED_REVIEWERS);
    if ($added_reviewers) {
      foreach ($added_reviewers as $phid) {
        $phids[] = $phid;
      }
    }
    $added_ccs = idx(
      $metadata,
      self::METADATA_ADDED_CCS);
    if ($added_ccs) {
      foreach ($added_ccs as $phid) {
        $phids[] = $phid;
      }
    }

    return $phids;
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
    return 'DC:'.$this->getID();
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
    return (bool)$this->getID();
  }

}
