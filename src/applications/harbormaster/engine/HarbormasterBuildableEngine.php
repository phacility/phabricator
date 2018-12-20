<?php

abstract class HarbormasterBuildableEngine
  extends Phobject {

  private $viewer;
  private $actingAsPHID;
  private $contentSource;
  private $object;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setActingAsPHID($acting_as_phid) {
    $this->actingAsPHID = $acting_as_phid;
    return $this;
  }

  final public function getActingAsPHID() {
    return $this->actingAsPHID;
  }

  final public function setContentSource(
    PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  final public function getContentSource() {
    return $this->contentSource;
  }

  final public function setObject(HarbormasterBuildableInterface $object) {
    $this->object = $object;
    return $this;
  }

  final public function getObject() {
    return $this->object;
  }

  protected function getPublishableObject() {
    return $this->getObject();
  }

  public function publishBuildable(
    HarbormasterBuildable $old,
    HarbormasterBuildable $new) {
    return;
  }

  final public static function newForObject(
    HarbormasterBuildableInterface $object,
    PhabricatorUser $viewer) {
    return $object->newBuildableEngine()
      ->setViewer($viewer)
      ->setObject($object);
  }

  final protected function newEditor() {
    $publishable = $this->getPublishableObject();

    $viewer = $this->getViewer();

    $editor = $publishable->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $acting_as_phid = $this->getActingAsPHID();
    if ($acting_as_phid !== null) {
      $editor->setActingAsPHID($acting_as_phid);
    }

    $content_source = $this->getContentSource();
    if ($content_source !== null) {
      $editor->setContentSource($content_source);
    }

    return $editor;
  }

  final protected function newTransaction() {
    $publishable = $this->getPublishableObject();

    return $publishable->getApplicationTransactionTemplate();
  }

  final protected function applyTransactions(array $xactions) {
    $publishable = $this->getPublishableObject();
    $editor = $this->newEditor();

    $editor->applyTransactions($publishable, $xactions);
  }

  public function getAuthorIdentity() {
    return null;
  }

}
