<?php

final class DifferentialJIRAIssuesField
  extends DifferentialStoredCustomField {

  private $error;

  public function getFieldKey() {
    return 'phabricator:jira-issues';
  }

  public function getValueForStorage() {
    return json_encode($this->getValue());
  }

  public function setValueFromStorage($value) {
    $this->setValue(phutil_json_decode($value));
    return $this;
  }

  public function getFieldName() {
    return pht('JIRA Issues');
  }

  public function getFieldDescription() {
    return pht('Lists associated JIRA issues.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $xobjs = $this->loadDoorkeeperExternalObjects($this->getValue());
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

  private function buildDoorkeeperRefs($value) {
    $provider = PhabricatorAuthProviderOAuth1JIRA::getJIRAProvider();

    $refs = array();
    if ($value) {
      foreach ($value as $jira_key) {
        $refs[] = id(new DoorkeeperObjectRef())
          ->setApplicationType(DoorkeeperBridgeJIRA::APPTYPE_JIRA)
          ->setApplicationDomain($provider->getProviderDomain())
          ->setObjectType(DoorkeeperBridgeJIRA::OBJTYPE_ISSUE)
          ->setObjectID($jira_key);
      }
    }

    return $refs;
  }

  private function loadDoorkeeperExternalObjects($value) {
    $refs = $this->buildDoorkeeperRefs($value);
    if (!$refs) {
      return array();
    }

    $xobjs = id(new DoorkeeperExternalObjectQuery())
      ->setViewer($this->getViewer())
      ->withObjectKeys(mpull($refs, 'getObjectKey'))
      ->execute();

    return $xobjs;
  }

  public function shouldAppearInEditView() {
    return PhabricatorAuthProviderOAuth1JIRA::getJIRAProvider();
  }

  public function shouldAppearInApplicationTransactions() {
    return PhabricatorAuthProviderOAuth1JIRA::getJIRAProvider();
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $this->setValue($request->getStrList($this->getFieldKey()));
    return $this;
  }

  public function renderEditControl(array $handles) {
    return id(new AphrontFormTextControl())
      ->setLabel(pht('JIRA Issues'))
      ->setCaption(
        pht('Example: %s', phutil_tag('tt', array(), 'JIS-3, JIS-9')))
      ->setName($this->getFieldKey())
      ->setValue(implode(', ', nonempty($this->getValue(), array())))
      ->setError($this->error);
  }

  public function getOldValueForApplicationTransactions() {
    return nonempty($this->getValue(), array());
  }

  public function getNewValueForApplicationTransactions() {
    return nonempty($this->getValue(), array());
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $this->error = null;

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    $transaction = null;
    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();

      $refs = id(new DoorkeeperImportEngine())
        ->setViewer($this->getViewer())
        ->setRefs($this->buildDoorkeeperRefs($value))
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

        $error = new PhabricatorApplicationTransactionValidationError(
          $type,
          pht('Invalid'),
          pht(
            "Some JIRA issues could not be loaded. They may not exist, or ".
            "you may not have permission to view them: %s",
            $bad),
          $xaction);
        $errors[] = $error;
      }
    }

    return $errors;
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    if (!is_array($old)) {
      $old = array();
    }

    $new = $xaction->getNewValue();
    if (!is_array($new)) {
      $new = array();
    }

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    $author_phid = $xaction->getAuthorPHID();
    if ($add && $rem) {
      return pht(
        '%s updated JIRA issue(s): added %d %s; removed %d %s.',
        $xaction->renderHandleLink($author_phid),
        new PhutilNumber(count($add)),
        implode(', ', $add),
        new PhutilNumber(count($rem)),
        implode(', ', $rem));
    } else if ($add) {
      return pht(
        '%s added %d JIRA issue(s): %s.',
        $xaction->renderHandleLink($author_phid),
        new PhutilNumber(count($add)),
        implode(', ', $add));
    } else if ($rem) {
      return pht(
        '%s removed %d JIRA issue(s): %s.',
        $xaction->renderHandleLink($author_phid),
        new PhutilNumber(count($rem)),
        implode(', ', $rem));
    }

    return parent::getApplicationTransactionTitle($xaction);
  }

  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {

    // Update the CustomField storage.
    parent::applyApplicationTransactionExternalEffects($xaction);

    // Now, synchronize the Doorkeeper edges.
    $revision = $this->getObject();
    $revision_phid = $revision->getPHID();

    $edge_type = PhabricatorEdgeConfig::TYPE_PHOB_HAS_JIRAISSUE;
    $xobjs = $this->loadDoorkeeperExternalObjects($xaction->getNewValue());
    $edge_dsts = mpull($xobjs, 'getPHID');

    $edges = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision_phid,
      $edge_type);

    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($this->getViewer());

    foreach (array_diff($edges, $edge_dsts) as $rem_edge) {
      $editor->removeEdge($revision_phid, $edge_type, $rem_edge);
    }

    foreach (array_diff($edge_dsts, $edges) as $add_edge) {
      $editor->addEdge($revision_phid, $edge_type, $add_edge);
    }

    $editor->save();
  }

}
