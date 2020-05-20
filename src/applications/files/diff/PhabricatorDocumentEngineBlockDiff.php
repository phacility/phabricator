<?php

final class PhabricatorDocumentEngineBlockDiff
  extends Phobject {

  private $oldContent;
  private $newContent;
  private $oldClasses = array();
  private $newClasses = array();

  public function setOldContent($old_content) {
    $this->oldContent = $old_content;
    return $this;
  }

  public function getOldContent() {
    return $this->oldContent;
  }

  public function setNewContent($new_content) {
    $this->newContent = $new_content;
    return $this;
  }

  public function getNewContent() {
    return $this->newContent;
  }

  public function addOldClass($class) {
    $this->oldClasses[] = $class;
    return $this;
  }

  public function getOldClasses() {
    return $this->oldClasses;
  }

  public function addNewClass($class) {
    $this->newClasses[] = $class;
    return $this;
  }

  public function getNewClasses() {
    return $this->newClasses;
  }

}
