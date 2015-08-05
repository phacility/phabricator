<?php

final class DiffusionExternalController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function shouldLoadDiffusionRequest() {
    return false;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {

    $uri = $request->getStr('uri');
    $id  = $request->getStr('id');

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($request->getUser())
      ->execute();

    if ($uri) {
      $uri_path = id(new PhutilURI($uri))->getPath();
      $matches = array();

      // Try to figure out which tracked repository this external lives in by
      // comparing repository metadata. We look for an exact match, but accept
      // a partial match.

      foreach ($repositories as $key => $repository) {
        $remote_uri = new PhutilURI($repository->getRemoteURI());
        if ($remote_uri->getPath() == $uri_path) {
          $matches[$key] = 1;
        }
        if ($repository->getPublicCloneURI() == $uri) {
          $matches[$key] = 2;
        }
        if ($repository->getRemoteURI() == $uri) {
          $matches[$key] = 3;
        }
      }

      arsort($matches);
      $best_match = head_key($matches);

      if ($best_match) {
        $repository = $repositories[$best_match];
        $redirect = DiffusionRequest::generateDiffusionURI(
          array(
            'action'    => 'browse',
            'callsign'  => $repository->getCallsign(),
            'branch'    => $repository->getDefaultBranch(),
            'commit'    => $id,
          ));
        return id(new AphrontRedirectResponse())->setURI($redirect);
      }
    }

    // TODO: This is a rare query but does a table scan, add a key?

    $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
      'commitIdentifier = %s',
      $id);

    if (empty($commits)) {
      $desc = null;
      if ($uri) {
        $desc = $uri.', at ';
      }
      $desc .= $id;

      $content = id(new PHUIInfoView())
        ->setTitle(pht('Unknown External'))
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild(phutil_tag(
          'p',
          array(),
          pht(
            'This external (%s) does not appear in any tracked '.
            'repository. It may exist in an untracked repository that '.
            'Diffusion does not know about.',
            $desc)));
    } else if (count($commits) == 1) {
      $commit = head($commits);
      $repo = $repositories[$commit->getRepositoryID()];
      $redirect = DiffusionRequest::generateDiffusionURI(
        array(
          'action'    => 'browse',
          'callsign'  => $repo->getCallsign(),
          'branch'    => $repo->getDefaultBranch(),
          'commit'    => $commit->getCommitIdentifier(),
        ));
      return id(new AphrontRedirectResponse())->setURI($redirect);
    } else {

      $rows = array();
      foreach ($commits as $commit) {
        $repo = $repositories[$commit->getRepositoryID()];
        $href = DiffusionRequest::generateDiffusionURI(
          array(
            'action'    => 'browse',
            'callsign'  => $repo->getCallsign(),
            'branch'    => $repo->getDefaultBranch(),
            'commit'    => $commit->getCommitIdentifier(),
          ));
        $rows[] = array(
          phutil_tag(
            'a',
            array(
              'href' => $href,
            ),
              'r'.$repo->getCallsign().$commit->getCommitIdentifier()),
          $commit->loadCommitData()->getSummary(),
        );
      }

      $table = new AphrontTableView($rows);
      $table->setHeaders(
        array(
          pht('Commit'),
          pht('Description'),
        ));
      $table->setColumnClasses(
        array(
          'pri',
          'wide',
        ));

      $caption = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(
          pht('This external reference matches multiple known commits.'));

      $content = new PHUIObjectBoxView();
      $content->setHeaderText(pht('Multiple Matching Commits'));
      $content->setInfoView($caption);
      $content->setTable($table);
    }

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => pht('Unresolvable External'),
      ));
  }

}
