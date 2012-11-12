<?php

final class PhabricatorRepositoryCommitData extends PhabricatorRepositoryDAO {

  const SUMMARY_MAX_LENGTH = 100;

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
    $lines = explode("\n", $message);
    $summary = head($lines);

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

}
