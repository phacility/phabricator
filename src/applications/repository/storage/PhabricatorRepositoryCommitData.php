<?php

final class PhabricatorRepositoryCommitData extends PhabricatorRepositoryDAO {

  /**
   * NOTE: We denormalize this into the commit table; make sure the sizes
   * match up.
   */
  const SUMMARY_MAX_LENGTH = 80;

  protected $commitID;
  protected $authorName    = '';
  protected $commitMessage = '';
  protected $commitDetails = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'commitDetails' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getSummary() {
    $message = $this->getCommitMessage();
    return self::summarizeCommitMessage($message);
  }

  public static function summarizeCommitMessage($message) {
    $summary = phutil_split_lines($message, $retain_endings = false);
    $summary = head($summary);
    $summary = phutil_utf8_shorten($summary, self::SUMMARY_MAX_LENGTH);

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

}
