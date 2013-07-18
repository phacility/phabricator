<?php

abstract class AphrontAbstractAttachedFileView extends AphrontView {

  private $file;

  final public function setFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  final protected function getFile() {
    return $this->file;
  }

  final protected function getName() {
    $file = $this->getFile();
    return phutil_tag(
      'a',
      array(
        'href'    => $file->getViewURI(),
        'target'  => '_blank',
      ),
      $file->getName());
  }

  final protected function getRemoveElement() {
    $file = $this->getFile();
    return javelin_tag(
      'a',
      array(
        'class' => 'button grey',
        'sigil' => 'aphront-attached-file-view-remove',
        // NOTE: Using 'ref' here instead of 'meta' because the file upload
        // endpoint doesn't receive request metadata and thus can't generate
        // a valid response with node metadata.
        'ref'   => $file->getPHID(),
      ),
      "\xE2\x9C\x96"); // "Heavy Multiplication X"
  }
}
