<?php

final class PhabricatorFileLinkListView extends AphrontView {
  private $files;

  public function setFiles(array $files) {
    assert_instances_of($files, 'PhabricatorFile');
    $this->files = $files;
    return $this;
  }
  private function getFiles() {
    return $this->files;
  }

  public function render() {
    $files = $this->getFiles();
    if (!$files) {
      return '';
    }

    require_celerity_resource('phabricator-remarkup-css');

    $file_links = array();
    foreach ($this->getFiles() as $file) {
      $view = id(new PhabricatorFileLinkView())
        ->setFilePHID($file->getPHID())
        ->setFileName($file->getName())
        ->setFileDownloadURI($file->getDownloadURI())
        ->setFileViewURI($file->getBestURI())
        ->setFileViewable($file->isViewableImage());
      $file_links[] = $view->render();
    }

    return phutil_implode_html(phutil_tag('br'), $file_links);
  }
}
