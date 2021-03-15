<?php

final class DifferentialRevisionAffectedPathsController
  extends DifferentialController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($id))
      ->setViewer($viewer)
      ->executeOne();
    if (!$revision) {
      return new Aphront404Response();
    }

    $table = new DifferentialAffectedPath();
    $conn = $table->establishConnection('r');

    $paths = queryfx_all(
      $conn,
      'SELECT * FROM %R WHERE revisionID = %d',
      $table,
      $revision->getID());

    $repository_ids = array();
    $path_ids = array();

    foreach ($paths as $path) {
      $repository_id = $path['repositoryID'];
      $path_id = $path['pathID'];

      $repository_ids[] = $repository_id;
      $path_ids[] = $path_id;
    }

    $repository_ids = array_fuse($repository_ids);

    if ($repository_ids) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withIDs($repository_ids)
        ->execute();
      $repositories = mpull($repositories, null, 'getID');
    } else {
      $repositories = array();
    }

    $handles = $viewer->loadHandles(mpull($repositories, 'getPHID'));

    $path_ids = array_fuse($path_ids);
    if ($path_ids) {
      $path_names = id(new DiffusionPathQuery())
        ->withPathIDs($path_ids)
        ->execute();
    } else {
      $path_names = array();
    }

    $rows = array();
    foreach ($paths as $path) {
      $repository_id = $path['repositoryID'];
      $path_id = $path['pathID'];

      $repository = idx($repositories, $repository_id);
      if ($repository) {
        $repository_phid = $repository->getPHID();
        $repository_link = $handles[$repository_phid]->renderLink();
      } else {
        $repository_link = null;
      }

      $path_name = idx($path_names, $path_id);
      if ($path_name !== null) {
        $path_view = $path_name['path'];
      } else {
        $path_view = null;
      }

      $rows[] = array(
        $repository_id,
        $repository_link,
        $path_id,
        $path_view,
      );
    }

    // Sort rows by path name.
    $rows = isort($rows, 3);

    $table_view = id(new AphrontTableView($rows))
      ->setNoDataString(pht('This revision has no indexed affected paths.'))
      ->setHeaders(
        array(
          pht('Repository ID'),
          pht('Repository'),
          pht('Path ID'),
          pht('Path'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          'wide',
        ));

    $box_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Affected Path Index'))
      ->setTable($table_view);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($revision->getMonogram(), $revision->getURI())
      ->addTextCrumb(pht('Affected Path Index'));

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setTitle(
        array(
          $revision->getMonogram(),
          pht('Affected Path Index'),
        ))
      ->appendChild($box_view);
  }

}
