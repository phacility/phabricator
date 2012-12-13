<?php

final class DifferentialRevisionIDFieldSpecification
  extends DifferentialFieldSpecification {

  private $id;

  protected function didSetRevision() {
    $this->id = $this->getRevision()->getID();
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function getCommitMessageKey() {
    return 'revisionID';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->id = $value;
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Differential Revision';
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->id) {
      return null;
    }
    return PhabricatorEnv::getProductionURI('/D'.$this->id);
  }

  public function parseValueFromCommitMessage($value) {
    $rev = trim(head(explode("\n", $value)));

    if (!strlen($rev)) {
      return null;
    }

    if (is_numeric($rev)) {
      // TODO: Eventually, remove support for bare revision numbers.
      return (int)$rev;
    }

    $rev = self::parseRevisionIDFromURI($rev);
    if ($rev !== null) {
      return $rev;
    }

    $example_uri = PhabricatorEnv::getProductionURI('/D123');
    throw new DifferentialFieldParseException(
      "Commit references invalid 'Differential Revision'. Expected a ".
      "Phabricator URI like '{$example_uri}', got '{$value}'.");
  }

  public static function parseRevisionIDFromURI($uri) {
    $path = id(new PhutilURI($uri))->getPath();

    $matches = null;
    if (preg_match('#^/D(\d+)$#', $path, $matches)) {
      $id = (int)$matches[1];
      // Make sure the URI is the same as our URI. Basically, we want to ignore
      // commits from other Phabricator installs.
      if ($uri == PhabricatorEnv::getProductionURI('/D'.$id)) {
        return $id;
      }
    }

    return null;
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'ID';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return 'D'.$revision->getID();
  }

  public function renderValueForMail($phase) {
    $body = array();
    $body[] = 'REVISION DETAIL';
    $body[] = '  '.PhabricatorEnv::getProductionURI('/D'.$this->id);

    if ($phase == DifferentialMailPhase::UPDATE) {
      $diffs = id(new DifferentialDiff())->loadAllWhere(
        'revisionID = %d ORDER BY id DESC LIMIT 2',
        $this->id);
      if (count($diffs) == 2) {
        list($new, $old) = array_values(mpull($diffs, 'getID'));
        $body[] = null;
        $body[] = 'CHANGE SINCE LAST DIFF';
        $body[] = '  '.PhabricatorEnv::getProductionURI(
          "/D{$this->id}?vs={$old}&id={$new}#toc");
      }
    }

    return implode("\n", $body);
  }

}
