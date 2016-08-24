<?php

final class PhabricatorRepositoryCommitHint
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  protected $repositoryPHID;
  protected $oldCommitIdentifier;
  protected $newCommitIdentifier;
  protected $hintType;

  const HINT_NONE = 'none';
  const HINT_REWRITTEN = 'rewritten';
  const HINT_UNREADABLE = 'unreadable';

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'oldCommitIdentifier' => 'text40',
        'newCommitIdentifier' => 'text40?',
        'hintType' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_old' => array(
          'columns' => array('repositoryPHID', 'oldCommitIdentifier'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function getAllHintTypes() {
    return array(
      self::HINT_NONE,
      self::HINT_REWRITTEN,
      self::HINT_UNREADABLE,
    );
  }

  public static function updateHint($repository_phid, $old, $new, $type) {
    switch ($type) {
      case self::HINT_NONE:
        break;
      case self::HINT_REWRITTEN:
        if (!$new) {
          throw new Exception(
            pht(
              'When hinting a commit ("%s") as rewritten, you must provide '.
              'the commit it was rewritten into.',
              $old));
        }
        break;
      case self::HINT_UNREADABLE:
        if ($new) {
          throw new Exception(
            pht(
              'When hinting a commit ("%s") as unreadable, you must not '.
              'provide a new commit ("%s").',
              $old,
              $new));
        }
        break;
      default:
        $all_types = self::getAllHintTypes();
        throw new Exception(
          pht(
            'Hint type ("%s") for commit ("%s") is not valid. Valid hints '.
            'are: %s.',
            $type,
            $old,
            implode(', ', $all_types)));
    }

    $table = new self();
    $table_name = $table->getTableName();
    $conn = $table->establishConnection('w');

    if ($type == self::HINT_NONE) {
      queryfx(
        $conn,
        'DELETE FROM %T WHERE repositoryPHID = %s AND oldCommitIdentifier = %s',
        $table_name,
        $repository_phid,
        $old);
    } else {
      queryfx(
        $conn,
        'INSERT INTO %T
          (repositoryPHID, oldCommitIdentifier, newCommitIdentifier, hintType)
          VALUES (%s, %s, %ns, %s)
          ON DUPLICATE KEY UPDATE
            newCommitIdentifier = VALUES(newCommitIdentifier),
            hintType = VALUES(hintType)',
        $table_name,
        $repository_phid,
        $old,
        $new,
        $type);
    }
  }


  public function isUnreadable() {
    return ($this->getHintType() == self::HINT_UNREADABLE);
  }

  public function isRewritten() {
    return ($this->getHintType() == self::HINT_REWRITTEN);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::getMostOpenPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

}
