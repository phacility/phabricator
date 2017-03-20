<?php

final class DifferentialReviewer
  extends DifferentialDAO {

  protected $revisionPHID;
  protected $reviewerPHID;
  protected $reviewerStatus;

  protected $lastActionDiffPHID;
  protected $lastCommentDiffPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'reviewerStatus' => 'text64',
        'lastActionDiffPHID' => 'phid?',
        'lastCommentDiffPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_revision' => array(
          'columns' => array('revisionPHID', 'reviewerPHID'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
