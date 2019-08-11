<?php

final class PhabricatorPDFPagesObject
  extends PhabricatorPDFObject {

  private $pageObjects = array();

  public function addPageObject(PhabricatorPDFPageObject $page) {
    $page->setPagesObject($this);
    $this->pageObjects[] = $this->newChildObject($page);
    return $this;
  }

  public function getPageObjects() {
    return $this->pageObjects;
  }

  protected function writeObject() {
    $this->writeLine('/Type /Pages');

    $page_objects = $this->getPageObjects();

    $this->writeLine('/Count %d', count($page_objects));
    $this->writeLine('/MediaBox [%d %d %0.2f %0.2f]', 0, 0, 595.28, 841.89);

    if ($page_objects) {
      $kids = array();
      foreach ($page_objects as $page_object) {
        $kids[] = sprintf(
          '%d 0 R',
          $page_object->getObjectIndex());
      }

      $this->writeLine('/Kids [%s]', implode(' ', $kids));
    }
  }

}
