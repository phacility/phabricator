<?php

final class DifferentialRepositoryField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:repository';
  }

  public function getFieldName() {
    return pht('Repository');
  }

  public function getFieldDescription() {
    return pht('Associates a revision with a repository.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getRepositoryPHID();
  }

  protected function writeValueToRevision(
    DifferentialRevision $revision,
    $value) {
    $revision->setRepositoryPHID($value);
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $phids = $request->getArr($this->getFieldKey());
    $first = head($phids);
    $this->setValue(nonempty($first, null));
  }

  public function renderEditControl(array $handles) {
    if ($this->getValue()) {
      $value = array($this->getValue());
    } else {
      $value = array();
    }

    return id(new AphrontFormTokenizerControl())
      ->setUser($this->getViewer())
      ->setName($this->getFieldKey())
      ->setDatasource(new DiffusionRepositoryDatasource())
      ->setValue($value)
      ->setError($this->getFieldError())
      ->setLabel($this->getFieldName())
      ->setLimit(1);
  }

  public function getApplicationTransactionRequiredHandlePHIDs(
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $phids = array();
    if ($old) {
      $phids[] = $old;
    }
    if ($new) {
      $phids[] = $new;
    }

    return $phids;
  }

  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if ($old && $new) {
      return pht(
        '%s changed the repository for this revision from %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($old),
        $xaction->renderHandleLink($new));
    } else if ($new) {
      return pht(
        '%s set the repository for this revision to %s.',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($new));
    } else {
      return pht(
        '%s removed %s as the repository for this revision.',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($old));
    }
  }

  public function getApplicationTransactionTitleForFeed(
    PhabricatorApplicationTransaction $xaction) {

    $object_phid = $xaction->getObjectPHID();
    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if ($old && $new) {
      return pht(
        '%s updated the repository for %s from %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($object_phid),
        $xaction->renderHandleLink($old),
        $xaction->renderHandleLink($new));
    } else if ($new) {
      return pht(
        '%s set the repository for %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($object_phid),
        $xaction->renderHandleLink($new));
    } else {
      return pht(
        '%s removed the repository for %s. (Repository was %s.)',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($object_phid),
        $xaction->renderHandleLink($old));
    }
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    return null;
  }

  public function shouldAppearInDiffPropertyView() {
    return true;
  }

  public function renderDiffPropertyViewLabel(DifferentialDiff $diff) {
    return $this->getFieldName();
  }

  public function renderDiffPropertyViewValue(DifferentialDiff $diff) {
    if (!$diff->getRepositoryPHID()) {
      return null;
    }

    return $this->getViewer()->renderHandle($diff->getRepositoryPHID());
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    $repository = $this->getObject()->getRepository();
    if ($repository === null) {
      return;
    }

    $body->addTextSection(
      pht('REPOSITORY'),
      $repository->getMonogram().' '.$repository->getName());
  }

}
