<?php

final class PhabricatorAuditComment extends PhabricatorAuditDAO
  implements PhabricatorMarkupInterface {

  const METADATA_ADDED_AUDITORS  = 'added-auditors';
  const METADATA_ADDED_CCS       = 'added-ccs';

  const MARKUP_FIELD_BODY        = 'markup:body';

  protected $phid;
  protected $actorPHID;
  protected $targetPHID;
  protected $action;
  protected $content = '';
  protected $metadata = array();

  private $proxyComment;

  public static function loadComments(
    PhabricatorUser $viewer,
    $commit_phid) {

    $comments = id(new PhabricatorAuditComment())->loadAllWhere(
      'targetPHID = %s',
      $commit_phid);

    if ($comments) {
      $table = new PhabricatorAuditTransactionComment();
      $conn_r = $table->establishConnection('r');

      $data = queryfx_all(
        $conn_r,
        'SELECT * FROM %T WHERE legacyCommentID IN (%Ld) AND pathID IS NULL',
        $table->getTableName(),
        mpull($comments, 'getID'));
      $texts = $table->loadAllFromArray($data);
      $texts = mpull($texts, null, 'getLegacyCommentID');

      foreach ($comments as $comment) {
        $text = idx($texts, $comment->getID());
        if ($text) {
          $comment->setProxyComment($text);
        }
      }
    }

    return $comments;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('ACMT');
  }


  public function getContent() {
    return $this->getProxyComment()->getContent();
  }

  public function setContent($content) {
    // NOTE: We no longer read this field, but there's no cost to continuing
    // to write it in case something goes horribly wrong, since it makes it
    // far easier to back out of this.
    $this->content = $content;
    $this->getProxyComment()->setContent($content);
    return $this;
  }

  private function getProxyComment() {
    if (!$this->proxyComment) {
      $this->proxyComment = new PhabricatorAuditTransactionComment();
    }
    return $this->proxyComment;
  }

  public function setProxyComment(PhabricatorAuditTransactionComment $proxy) {
    if ($this->proxyComment) {
      throw new Exception(pht('You can not overwrite a proxy comment.'));
    }
    $this->proxyComment = $proxy;
    return $this;
  }

  public function setTargetPHID($target_phid) {
    $this->getProxyComment()->setCommitPHID($target_phid);
    return parent::setTargetPHID($target_phid);
  }

  public function save() {
    $this->openTransaction();
      $result = parent::save();

      if (strlen($this->getContent())) {
        $content_source = PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_LEGACY,
          array());

        $xaction_phid = PhabricatorPHID::generateNewPHID(
          PhabricatorApplicationTransactionTransactionPHIDType::TYPECONST,
          PhabricatorRepositoryCommitPHIDType::TYPECONST);

        $proxy = $this->getProxyComment();
        $proxy
          ->setAuthorPHID($this->getActorPHID())
          ->setViewPolicy('public')
          ->setEditPolicy($this->getActorPHID())
          ->setContentSource($content_source)
          ->setCommentVersion(1)
          ->setLegacyCommentID($this->getID())
          ->setTransactionPHID($xaction_phid)
          ->save();
      }

    $this->saveTransaction();

    return $result;
  }


/* -(  PhabricatorMarkupInterface Implementation  )-------------------------- */


  public function getMarkupFieldKey($field) {
    return 'AC:'.$this->getID();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDiffusionMarkupEngine();
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
