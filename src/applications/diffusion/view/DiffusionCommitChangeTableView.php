<?php

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
        $path_column = javelin_tag(
          'a',
          array(
            'href' => '#'.$hash,
            'meta' => array(
              'id' => 'diff-'.$hash,
              'ref' => $this->renderingReferences[$id],
            ),
            'sigil' => 'differential-load',
          ),
          $path);
      } else {
        $path_column = $path;
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
        $excluded = $owners_path->getExcluded();
        $owners_path = $owners_path->getPath();
        if (strncmp('/'.$path, $owners_path, strlen($owners_path)) == 0) {
          if ($excluded) {
            $row_class = null;
            break;
          }
          $row_class = 'highlighted';
        }
      }
      $rowc[] = $row_class;
    }

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        pht('History'),
        pht('Browse'),
        pht('Change'),
        pht('Path'),
      ));
    $view->setColumnClasses(
      array(
        '',
        '',
        '',
        'wide',
      ));
    $view->setRowClasses($rowc);
    $view->setNoDataString(pht('This change has not been fully parsed yet.'));

    return $view->render();
  }

}
