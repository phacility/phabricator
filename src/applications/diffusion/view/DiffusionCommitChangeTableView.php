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

final class DiffusionCommitChangeTableView extends DiffusionView {

  private $pathChanges;
  private $ownersPaths = array();
  private $renderingReferences;

  public function setPathChanges(array $path_changes) {
    assert_instances_of($path_changes, 'DiffusionPathChange');
    $this->pathChanges = $path_changes;
    return $this;
  }

  public function setOwnersPaths(array $owners_paths) {
    assert_instances_of($owners_paths, 'PhabricatorOwnersPath');
    $this->ownersPaths = $owners_paths;
    return $this;
  }

  public function setRenderingReferences(array $value) {
    $this->renderingReferences = $value;
    return $this;
  }

  public function render() {
    $rows = array();
    $rowc = array();

    // TODO: Experiment with path stack rendering.

    // TODO: Copy Away and Move Away are rendered junkily still.

    foreach ($this->pathChanges as $id => $change) {
      $path = $change->getPath();
      $hash = substr(md5($path), 0, 8);
      if ($change->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
        $path .= '/';
      }

      if (isset($this->renderingReferences[$id])) {
        $path_column = javelin_render_tag(
          'a',
          array(
            'href' => '#'.$hash,
            'meta' => array(
              'id' => 'diff-'.$hash,
              'ref' => $this->renderingReferences[$id],
            ),
            'sigil' => 'differential-load',
          ),
          phutil_escape_html($path));
      } else {
        $path_column = phutil_escape_html($path);
      }

      $rows[] = array(
        $this->linkHistory($change->getPath()),
        $this->linkBrowse($change->getPath()),
        $this->linkChange(
          $change->getChangeType(),
          $change->getFileType(),
          $change->getPath()),
        $path_column,
      );

      $row_class = null;
      foreach ($this->ownersPaths as $owners_path) {
        $owners_path = $owners_path->getPath();
        if (strncmp('/'.$path, $owners_path, strlen($owners_path)) == 0) {
          $row_class = 'highlighted';
          break;
        }
      }
      $rowc[] = $row_class;
    }

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        'History',
        'Browse',
        'Change',
        'Path',
      ));
    $view->setColumnClasses(
      array(
        '',
        '',
        '',
        'wide',
      ));
    $view->setRowClasses($rowc);
    $view->setNoDataString('This change has not been fully parsed yet.');

    return $view->render();
  }

}
