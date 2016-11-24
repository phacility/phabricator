<?php

abstract class PhabricatorConfigStorageSchema extends Phobject {

  const ISSUE_MISSING = 'missing';
  const ISSUE_MISSINGKEY = 'missingkey';
  const ISSUE_SURPLUS = 'surplus';
  const ISSUE_SURPLUSKEY = 'surpluskey';
  const ISSUE_CHARSET = 'charset';
  const ISSUE_COLLATION = 'collation';
  const ISSUE_COLUMNTYPE = 'columntype';
  const ISSUE_NULLABLE = 'nullable';
  const ISSUE_KEYCOLUMNS = 'keycolumns';
  const ISSUE_UNIQUE = 'unique';
  const ISSUE_LONGKEY = 'longkey';
  const ISSUE_SUBWARN = 'subwarn';
  const ISSUE_SUBFAIL = 'subfail';
  const ISSUE_AUTOINCREMENT = 'autoincrement';
  const ISSUE_UNKNOWN = 'unknown';
  const ISSUE_ACCESSDENIED = 'accessdenied';
  const ISSUE_ENGINE = 'engine';

  const STATUS_OKAY = 'okay';
  const STATUS_WARN = 'warn';
  const STATUS_FAIL = 'fail';

  private $issues = array();
  private $name;

  abstract public function newEmptyClone();
  abstract protected function compareToSimilarSchema(
    PhabricatorConfigStorageSchema $expect);
  abstract protected function getSubschemata();

  public function compareTo(PhabricatorConfigStorageSchema $expect) {
    if (get_class($expect) != get_class($this)) {
      throw new Exception(pht('Classes must match to compare schemata!'));
    }

    if ($this->getName() != $expect->getName()) {
      throw new Exception(pht('Names must match to compare schemata!'));
    }

    return $this->compareToSimilarSchema($expect);
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIssues(array $issues) {
    $this->issues = array_fuse($issues);
    return $this;
  }

  public function getIssues() {
    $issues = $this->issues;

    foreach ($this->getSubschemata() as $sub) {
      switch ($sub->getStatus()) {
        case self::STATUS_WARN:
          $issues[self::ISSUE_SUBWARN] = self::ISSUE_SUBWARN;
          break;
        case self::STATUS_FAIL:
          $issues[self::ISSUE_SUBFAIL] = self::ISSUE_SUBFAIL;
          break;
      }
    }

    return $issues;
  }

  public function getLocalIssues() {
    return $this->issues;
  }

  public function hasIssue($issue) {
    return (bool)idx($this->getIssues(), $issue);
  }

  public function getAllIssues() {
    $issues = $this->getIssues();
    foreach ($this->getSubschemata() as $sub) {
      $issues += $sub->getAllIssues();
    }
    return $issues;
  }

  public function getStatus() {
    $status = self::STATUS_OKAY;
    foreach ($this->getAllIssues() as $issue) {
      $issue_status = self::getIssueStatus($issue);
      $status = self::getStrongestStatus($status, $issue_status);
    }
    return $status;
  }

  public static function getIssueName($issue) {
    switch ($issue) {
      case self::ISSUE_MISSING:
        return pht('Missing');
      case self::ISSUE_MISSINGKEY:
        return pht('Missing Key');
      case self::ISSUE_SURPLUS:
        return pht('Surplus');
      case self::ISSUE_SURPLUSKEY:
        return pht('Surplus Key');
      case self::ISSUE_CHARSET:
        return pht('Better Character Set Available');
      case self::ISSUE_COLLATION:
        return pht('Better Collation Available');
      case self::ISSUE_COLUMNTYPE:
        return pht('Wrong Column Type');
      case self::ISSUE_NULLABLE:
        return pht('Wrong Nullable Setting');
      case self::ISSUE_KEYCOLUMNS:
        return pht('Key on Wrong Columns');
      case self::ISSUE_UNIQUE:
        return pht('Key has Wrong Uniqueness');
      case self::ISSUE_LONGKEY:
        return pht('Key is Too Long');
      case self::ISSUE_SUBWARN:
        return pht('Subschemata Have Warnings');
      case self::ISSUE_SUBFAIL:
        return pht('Subschemata Have Failures');
      case self::ISSUE_AUTOINCREMENT:
        return pht('Column has Wrong Autoincrement');
      case self::ISSUE_UNKNOWN:
        return pht('Column Has No Specification');
      case self::ISSUE_ACCESSDENIED:
        return pht('Access Denied');
      case self::ISSUE_ENGINE:
        return pht('Better Table Engine Available');
      default:
        throw new Exception(pht('Unknown schema issue "%s"!', $issue));
    }
  }

  public static function getIssueDescription($issue) {
    switch ($issue) {
      case self::ISSUE_MISSING:
        return pht('This schema is expected to exist, but does not.');
      case self::ISSUE_MISSINGKEY:
        return pht('This key is expected to exist, but does not.');
      case self::ISSUE_SURPLUS:
        return pht('This schema is not expected to exist.');
      case self::ISSUE_SURPLUSKEY:
        return pht('This key is not expected to exist.');
      case self::ISSUE_CHARSET:
        return pht('This schema can use a better character set.');
      case self::ISSUE_COLLATION:
        return pht('This schema can use a better collation.');
      case self::ISSUE_COLUMNTYPE:
        return pht('This schema can use a better column type.');
      case self::ISSUE_NULLABLE:
        return pht('This schema has the wrong nullable setting.');
      case self::ISSUE_KEYCOLUMNS:
        return pht('This key is on the wrong columns.');
      case self::ISSUE_UNIQUE:
        return pht('This key has the wrong uniqueness setting.');
      case self::ISSUE_LONGKEY:
        return pht('This key is too long for utf8mb4.');
      case self::ISSUE_SUBWARN:
        return pht('Subschemata have setup warnings.');
      case self::ISSUE_SUBFAIL:
        return pht('Subschemata have setup failures.');
      case self::ISSUE_AUTOINCREMENT:
        return pht('This column has the wrong autoincrement setting.');
      case self::ISSUE_UNKNOWN:
        return pht('This column is missing a type specification.');
      case self::ISSUE_ENGINE:
        return pht('This table can use a better table engine.');
      default:
        throw new Exception(pht('Unknown schema issue "%s"!', $issue));
    }
  }

  public static function getIssueStatus($issue) {
    switch ($issue) {
      case self::ISSUE_MISSING:
      case self::ISSUE_ACCESSDENIED:
      case self::ISSUE_SURPLUS:
      case self::ISSUE_NULLABLE:
      case self::ISSUE_SUBFAIL:
      case self::ISSUE_UNKNOWN:
        return self::STATUS_FAIL;
      case self::ISSUE_SUBWARN:
      case self::ISSUE_COLUMNTYPE:
      case self::ISSUE_CHARSET:
      case self::ISSUE_COLLATION:
      case self::ISSUE_MISSINGKEY:
      case self::ISSUE_SURPLUSKEY:
      case self::ISSUE_UNIQUE:
      case self::ISSUE_KEYCOLUMNS:
      case self::ISSUE_LONGKEY:
      case self::ISSUE_AUTOINCREMENT:
      case self::ISSUE_ENGINE:
        return self::STATUS_WARN;
      default:
        throw new Exception(pht('Unknown schema issue "%s"!', $issue));
    }
  }

  public static function getStatusSeverity($status) {
    switch ($status) {
      case self::STATUS_FAIL:
        return 2;
      case self::STATUS_WARN:
        return 1;
      case self::STATUS_OKAY:
        return 0;
      default:
        throw new Exception(pht('Unknown schema status "%s"!', $status));
    }
  }

  public static function getStrongestStatus($u, $v) {
    $u_sev = self::getStatusSeverity($u);
    $v_sev = self::getStatusSeverity($v);

    if ($u_sev >= $v_sev) {
      return $u;
    } else {
      return $v;
    }
  }


}
