<?php

final class PhabricatorRepositoryCommitData extends PhabricatorRepositoryDAO {

  protected $commitID;
  protected $authorName    = '';
  protected $commitMessage = '';
  protected $commitDetails = array();
  private $commitRef;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'commitDetails' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'authorName' => 'text',
        'commitMessage' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'commitID' => array(
          'columns' => array('commitID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getSummary() {
    $message = $this->getCommitMessage();
    return self::summarizeCommitMessage($message);
  }

  public static function summarizeCommitMessage($message) {
    $max_bytes = id(new PhabricatorRepositoryCommit())
      ->getColumnMaximumByteLength('summary');

    $summary = phutil_split_lines($message, $retain_endings = false);
    $summary = head($summary);
    $summary = id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes($max_bytes)
      ->setMaximumGlyphs(80)
      ->truncateString($summary);

    return $summary;
  }

  public function getCommitDetail($key, $default = null) {
    return idx($this->commitDetails, $key, $default);
  }

  public function setCommitDetail($key, $value) {
    $this->commitDetails[$key] = $value;
    return $this;
  }

  public function toDictionary() {
    return array(
      'commitID' => $this->commitID,
      'authorName' => $this->authorName,
      'commitMessage' => $this->commitMessage,
      'commitDetails' => json_encode($this->commitDetails),
    );
  }

  public static function newFromDictionary(array $dict) {
    return id(new PhabricatorRepositoryCommitData())
      ->loadFromArray($dict);
  }

  public function newPublisherHoldReasons() {
    $holds = $this->getCommitDetail('holdReasons');

    // Look for the legacy "autocloseReason" if we don't have a modern list
    // of hold reasons.
    if (!$holds) {
      $old_hold = $this->getCommitDetail('autocloseReason');
      if ($old_hold) {
        $holds = array($old_hold);
      }
    }

    if (!$holds) {
      $holds = array();
    }

    foreach ($holds as $key => $reason) {
      $holds[$key] = PhabricatorRepositoryPublisherHoldReason::newForHoldKey(
        $reason);
    }

    return array_values($holds);
  }

  public function getAuthorString() {
    $ref = $this->getCommitRef();

    $author = $ref->getAuthor();
    if ($author !== null && strlen($author)) {
      return $author;
    }

    $author = phutil_string_cast($this->authorName);
    if (strlen($author)) {
      return $author;
    }

    return null;
  }

  public function getAuthorDisplayName() {
    return $this->getCommitRef()->getAuthorName();
  }

  public function getAuthorEmail() {
    return $this->getCommitRef()->getAuthorEmail();
  }

  public function getAuthorEpoch() {
    $epoch = $this->getCommitRef()->getAuthorEpoch();

    if ($epoch) {
      return (int)$epoch;
    }

    return null;
  }

  public function getCommitterString() {
    $ref = $this->getCommitRef();

    $committer = $ref->getCommitter();
    if ($committer !== null && strlen($committer)) {
      return $committer;
    }

    return $this->getCommitDetailString('committer');
  }

  public function getCommitterDisplayName() {
    return $this->getCommitRef()->getCommitterName();
  }

  public function getCommitterEmail() {
    return $this->getCommitRef()->getCommitterEmail();
  }

  private function getCommitDetailString($key) {
    $string = $this->getCommitDetail($key);
    $string = phutil_string_cast($string);

    if (strlen($string)) {
      return $string;
    }

    return null;
  }

  public function setCommitRef(DiffusionCommitRef $ref) {
    $this->setCommitDetail('ref', $ref->newDictionary());
    $this->commitRef = null;

    return $this;
  }

  public function getCommitRef() {
    if ($this->commitRef === null) {
      $map = $this->getCommitDetail('ref', array());

      if (!is_array($map)) {
        $map = array();
      }

      $map = $map + array(
        'authorName' => $this->getCommitDetailString('authorName'),
        'authorEmail' => $this->getCommitDetailString('authorEmail'),
        'authorEpoch' => $this->getCommitDetailString('authorEpoch'),
        'committerName' => $this->getCommitDetailString('committerName'),
        'committerEmail' => $this->getCommitDetailString('committerEmail'),
        'message' => $this->getCommitMessage(),
      );

      $ref = DiffusionCommitRef::newFromDictionary($map);
      $this->commitRef = $ref;
    }

    return $this->commitRef;
  }

}
