<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

    return implode('<br />', $file_links);
  }
}

