<?php

final class DiffusionExternalController extends DiffusionController {

  public function willProcessRequest(array $data) {
    // Don't build a DiffusionRequest.
  }

  public function processRequest() {
    $request = $this->getRequest();

    $uri = $request->getStr('uri');
    $id  = $request->getStr('id');

    $repositories = id(new PhabricatorRepository())->loadAll();

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
        if ($repository->getPublicRemoteURI() == $uri) {
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
        $desc = phutil_escape_html($uri).', at ';
      }
      $desc .= phutil_escape_html($id);

      $content = id(new AphrontErrorView())
        ->setTitle('Unknown External')
        ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
        ->appendChild(
          "<p>This external ({$desc}) does not appear in any tracked ".
          "repository. It may exist in an untracked repository that ".
          "Diffusion does not know about.</p>");
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
          phutil_render_tag(
            'a',
            array(
              'href' => $href,
            ),
            phutil_escape_html(
              'r'.$repo->getCallsign().$commit->getCommitIdentifier())),
          phutil_escape_html($commit->loadCommitData()->getSummary()),
        );
      }

      $table = new AphrontTableView($rows);
      $table->setHeaders(
        array(
          'Commit',
          'Description',
        ));
      $table->setColumnClasses(
        array(
          'pri',
          'wide',
        ));

      $content = new AphrontPanelView();
      $content->setHeader('Multiple Matching Commits');
      $content->setCaption(
        'This external reference matches multiple known commits.');
      $content->appendChild($table);
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Unresolvable External',
      ));
  }

}
