<?php

final class DiffusionLastModifiedController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();

    $paths = $request->getStr('paths');

    try {
      $paths = phutil_json_decode($paths);
    } catch (PhutilJSONParserException $ex) {
      return new Aphront400Response();
    }

    $modified_map = $this->callConduitWithDiffusionRequest(
      'diffusion.lastmodifiedquery',
      array(
        'paths' => array_fill_keys($paths, $drequest->getCommit()),
      ));

    if ($modified_map) {
      $commit_map = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withRepository($drequest->getRepository())
        ->withIdentifiers(array_values($modified_map))
        ->needCommitData(true)
        ->execute();
      $commit_map = mpull($commit_map, null, 'getCommitIdentifier');
    } else {
      $commit_map = array();
    }

    $commits = array();
    foreach ($paths as $path) {
      $modified_at = idx($modified_map, $path);
      if ($modified_at) {
        $commit = idx($commit_map, $modified_at);
        if ($commit) {
          $commits[$path] = $commit;
        }
      }
    }

    $phids = array();
    foreach ($commits as $commit) {
      $data = $commit->getCommitData();
      $phids[] = $data->getCommitDetail('authorPHID');
      $phids[] = $data->getCommitDetail('committerPHID');
    }
    $phids = array_filter($phids);
    $handles = $this->loadViewerHandles($phids);

    $branch = $drequest->loadBranch();
    if ($branch && $commits) {
      $lint_query = id(new DiffusionLintCountQuery())
        ->withBranchIDs(array($branch->getID()))
        ->withPaths(array_keys($commits));

      if ($drequest->getLint()) {
        $lint_query->withCodes(array($drequest->getLint()));
      }

      $lint = $lint_query->execute();
    } else {
      $lint = array();
    }

    $output = array();
    foreach ($commits as $path => $commit) {
      $prequest = clone $drequest;
      $prequest->setPath($path);

      $output[$path] = $this->renderColumns(
        $prequest,
        $handles,
        $commit,
        idx($lint, $path));
    }

    return id(new AphrontAjaxResponse())->setContent($output);
  }

  private function renderColumns(
    DiffusionRequest $drequest,
    array $handles,
    PhabricatorRepositoryCommit $commit = null,
    $lint = null) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $viewer = $this->getRequest()->getUser();

    if ($commit) {
      $epoch = $commit->getEpoch();
      $modified = DiffusionView::linkCommit(
        $drequest->getRepository(),
        $commit->getCommitIdentifier());
      $date = $viewer->formatShortDateTime($epoch);
    } else {
      $modified = '';
      $date = '';
    }

    $data = $commit->getCommitData();
    if ($data) {
      $author_phid = $data->getCommitDetail('authorPHID');
      if ($author_phid && isset($handles[$author_phid])) {
        $author = $handles[$author_phid]->renderLink();
      } else {
        $author = DiffusionView::renderName($data->getAuthorName());
      }

      $committer = $data->getCommitDetail('committer');
      if ($committer) {
        $committer_phid = $data->getCommitDetail('committerPHID');
        if ($committer_phid && isset($handles[$committer_phid])) {
          $committer = $handles[$committer_phid]->renderLink();
        } else {
          $committer = DiffusionView::renderName($committer);
        }
        if ($author != $committer) {
          $author = hsprintf('%s/%s', $author, $committer);
        }
      }

      $details = AphrontTableView::renderSingleDisplayLine($data->getSummary());
    } else {
      $author = '';
      $details = '';
    }

    $return = array(
      'commit'    => $modified,
      'date'      => $date,
      'author'    => $author,
      'details'   => $details,
    );

    if ($lint !== null) {
      $return['lint'] = phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(array(
            'action' => 'lint',
            'lint' => null,
          )),
        ),
        number_format($lint));
    }

    // The client treats these results as markup, so make sure they have been
    // escaped correctly.
    foreach ($return as $key => $value) {
      $return[$key] = hsprintf('%s', $value);
    }

    return $return;
  }

}
