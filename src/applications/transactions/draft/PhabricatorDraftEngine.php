<?php

abstract class PhabricatorDraftEngine
  extends Phobject {

  private $viewer;
  private $object;
  private $hasVersionedDraft;
  private $versionedDraft;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  final public function getObject() {
    return $this->object;
  }

  final public function setVersionedDraft(
    PhabricatorVersionedDraft $draft = null) {
    $this->hasVersionedDraft = true;
    $this->versionedDraft = $draft;
    return $this;
  }

  final public function getVersionedDraft() {
    if (!$this->hasVersionedDraft) {
      $draft = PhabricatorVersionedDraft::loadDraft(
        $this->getObject()->getPHID(),
        $this->getViewer()->getPHID());
      $this->setVersionedDraft($draft);
    }

    return $this->versionedDraft;
  }

  protected function hasVersionedDraftContent() {
    $draft = $this->getVersionedDraft();
    if (!$draft) {
      return false;
    }

    if ($draft->getProperty('comment')) {
      return true;
    }

    if ($draft->getProperty('actions')) {
      return true;
    }

    return false;
  }

  protected function hasCustomDraftContent() {
    return false;
  }

  final protected function hasAnyDraftContent() {
    if ($this->hasVersionedDraftContent()) {
      return true;
    }

    if ($this->hasCustomDraftContent()) {
      return true;
    }

    return false;
  }

  final public function synchronize() {
    $object_phid = $this->getObject()->getPHID();
    $viewer_phid = $this->getViewer()->getPHID();

    $has_draft = $this->hasAnyDraftContent();

    $draft_type = PhabricatorObjectHasDraftEdgeType::EDGECONST;
    $editor = id(new PhabricatorEdgeEditor());

    if ($has_draft) {
      $editor->addEdge($object_phid, $draft_type, $viewer_phid);
    } else {
      $editor->removeEdge($object_phid, $draft_type, $viewer_phid);
    }

    $editor->save();
  }

}
