<?php

final class PhabricatorPDFPageObject
  extends PhabricatorPDFObject {

  private $pagesObject;
  private $contentsObject;
  private $resourcesObject;

  public function setPagesObject(PhabricatorPDFPagesObject $pages) {
    $this->pagesObject = $pages;
    return $this;
  }

  public function setContentsObject(PhabricatorPDFContentsObject $contents) {
    $this->contentsObject = $this->newChildObject($contents);
    return $this;
  }

  public function setResourcesObject(PhabricatorPDFResourcesObject $resources) {
    $this->resourcesObject = $this->newChildObject($resources);
    return $this;
  }

  protected function writeObject() {
    $this->writeLine('/Type /Page');

    $pages_object = $this->pagesObject;
    $contents_object = $this->contentsObject;
    $resources_object = $this->resourcesObject;

    if ($pages_object) {
      $pages_index = $pages_object->getObjectIndex();
      $this->writeLine('/Parent %d 0 R', $pages_index);
    }

    if ($contents_object) {
      $contents_index = $contents_object->getObjectIndex();
      $this->writeLine('/Contents %d 0 R', $contents_index);
    }

    if ($resources_object) {
      $resources_index = $resources_object->getObjectIndex();
      $this->writeLine('/Resources %d 0 R', $resources_index);
    }
  }

}
