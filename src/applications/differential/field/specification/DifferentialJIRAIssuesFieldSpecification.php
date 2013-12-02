<?php

final class DifferentialJIRAIssuesFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;
  private $error;

  public function getStorageKey() {
    return 'phabricator:jira-issues';
  }

  public function getValueForStorage() {
    return json_encode($this->value);
  }

  public function setValueFromStorage($value) {
    if (!strlen($value)) {
      $this->value = array();
    } else {
      $this->value = json_decode($value, true);
    }
    return $this;
  }

  public function shouldAppearOnEdit() {
    return true;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStrList($this->getStorageKey());
    return $this;
  }

  public function renderEditControl() {
    return id(new AphrontFormTextControl())
      ->setLabel(pht('JIRA Issues'))
      ->setCaption(
        pht('Example: %s', phutil_tag('tt', array(), 'JIS-3, JIS-9')))
      ->setName($this->getStorageKey())
      ->setValue(implode(', ', nonempty($this->value, array())))
      ->setError($this->error);
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return pht('JIRA Issues:');
  }

  public function renderValueForRevisionView() {
    $xobjs = $this->loadDoorkeeperExternalObjects();
    if (!$xobjs) {
      return null;
    }

    $links = array();
    foreach ($xobjs as $xobj) {
      $links[] = id(new DoorkeeperTagView())
        ->setExternalObject($xobj);
    }

    return phutil_implode_html(phutil_tag('br'), $links);
  }

  public function shouldAppearOnConduitView() {
    return true;
  }

  public function getValueForConduit() {
    return $this->value;
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'jira.issues';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'JIRA Issues';
  }

  public function renderValueForCommitMessage($is_edit) {
    return implode(', ', $this->value);
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'JIRA',
      'JIRA Issues',
      'JIRA Issue',
    );
  }

  public function parseValueFromCommitMessage($value) {
    return preg_split('/[\s,]+/', $value, $limit = -1, PREG_SPLIT_NO_EMPTY);
  }

  public function validateField() {
    if ($this->value) {
      $refs = id(new DoorkeeperImportEngine())
        ->setViewer($this->getUser())
        ->setRefs($this->buildDoorkeeperRefs())
        ->execute();

      $bad = array();
      foreach ($refs as $ref) {
        if (!$ref->getIsVisible()) {
          $bad[] = $ref->getObjectID();
        }
      }

      if ($bad) {
        $bad = implode(', ', $bad);
        $this->error = pht('Invalid');
        throw new DifferentialFieldValidationException(
          pht(
            "Some JIRA issues could not be loaded. They may not exist, or ".
            "you may not have permission to view them: %s",
            $bad));
      }
    }
  }

  private function buildDoorkeeperRefs() {
    $provider = PhabricatorAuthProviderOAuth1JIRA::getJIRAProvider();

    $refs = array();
    if ($this->value) {
      foreach ($this->value as $jira_key) {
        $refs[] = id(new DoorkeeperObjectRef())
          ->setApplicationType(DoorkeeperBridgeJIRA::APPTYPE_JIRA)
          ->setApplicationDomain($provider->getProviderDomain())
          ->setObjectType(DoorkeeperBridgeJIRA::OBJTYPE_ISSUE)
          ->setObjectID($jira_key);
      }
    }

    return $refs;
  }

  private function loadDoorkeeperExternalObjects() {
    $refs = $this->buildDoorkeeperRefs();
    if (!$refs) {
      return array();
    }

    $xobjs = id(new DoorkeeperExternalObjectQuery())
      ->setViewer($this->getUser())
      ->withObjectKeys(mpull($refs, 'getObjectKey'))
      ->execute();

    return $xobjs;
  }

  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    $revision = $editor->getRevision();
    $revision_phid = $revision->getPHID();

    $edge_type = PhabricatorEdgeConfig::TYPE_PHOB_HAS_JIRAISSUE;
    $edge_dsts = mpull($this->loadDoorkeeperExternalObjects(), 'getPHID');

    $edges = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision_phid,
      $edge_type);

    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($this->getUser());

    foreach (array_diff($edges, $edge_dsts) as $rem_edge) {
      $editor->removeEdge($revision_phid, $edge_type, $rem_edge);
    }

    foreach (array_diff($edge_dsts, $edges) as $add_edge) {
      $editor->addEdge($revision_phid, $edge_type, $add_edge);
    }

    $editor->save();
  }

}
