<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class DiffusionBrowseTableView extends DiffusionView {

  private $paths;

  public function setPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  public function render() {
    $request = $this->getDiffusionRequest();
    $repository = $request->getRepository();

    $base_path = trim($request->getPath(), '/');
    if ($base_path) {
      $base_path = $base_path.'/';
    }

    $rows = array();
    foreach ($this->paths as $path) {

      if ($path->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
        $browse_text = $path->getPath().'/';
        $dir_slash = '/';

        $browse_link = '<strong>'.$this->linkBrowse(
          $base_path.$path->getPath().$dir_slash,
          array(
            'text' => $browse_text,
          )).'</strong>';
      } else {
        $browse_text = $path->getPath();
        $dir_slash = null;
        $browse_link = $this->linkBrowse(
          $base_path.$path->getPath().$dir_slash,
          array(
            'text' => $browse_text,
          ));
      }

      $commit = $path->getLastModifiedCommit();
      if ($commit) {
        $epoch = $commit->getEpoch();
        $modified = $this->linkCommit(
          $repository,
          $commit->getCommitIdentifier());
        $date = date('M j, Y', $epoch);
        $time = date('g:i A', $epoch);
      } else {
        $modified = '';
        $date = '';
        $time = '';
      }

      $data = $path->getLastCommitData();
      if ($data) {
        $author = phutil_escape_html($data->getAuthorName());
        $details = phutil_escape_html($data->getSummary());
      } else {
        $author = '';
        $details = '';
      }

      $rows[] = array(
        $this->linkHistory($base_path.$path->getPath().$dir_slash),
        $browse_link,
        $modified,
        $date,
        $time,
        $author,
        $details,
      );
    }

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        'History',
        'Path',
        'Modified',
        'Date',
        'Time',
        'Author',
        'Details',
      ));
    $view->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        'right',
        '',
        'wide',
      ));
    return $view->render();
  }

}
