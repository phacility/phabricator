<?php

final class DiffusionBlameController extends DiffusionController {

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
    $repository = $drequest->getRepository();

    $blame = $this->loadBlame();

    $identifiers = array_fuse($blame);
    if ($identifiers) {
      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withRepository($repository)
        ->withIdentifiers($identifiers)
        ->needIdentities(true)
        // See PHI1014. If identities haven't been built yet, we may need to
        // fall back to raw commit data.
        ->needCommitData(true)
        ->execute();
      $commits = mpull($commits, null, 'getCommitIdentifier');
    } else {
      $commits = array();
    }

    $commit_map = mpull($commits, 'getCommitIdentifier', 'getPHID');

    $revision_map = DiffusionCommitRevisionQuery::loadRevisionMapForCommits(
      $viewer,
      $commits);

    $base_href = (string)$drequest->generateURI(
      array(
        'action' => 'browse',
        'stable' => true,
      ));

    $skip_text = pht('Skip Past This Commit');
    $skip_icon = id(new PHUIIconView())
      ->setIcon('fa-backward');

    Javelin::initBehavior('phabricator-tooltips');

    $handle_phids = array();
    foreach ($commits as $commit) {
      $handle_phids[] = $commit->getAuthorDisplayPHID();
    }

    foreach ($revision_map as $revisions) {
      foreach ($revisions as $revision) {
        $handle_phids[] = $revision->getAuthorPHID();
      }
    }

    $handles = $viewer->loadHandles($handle_phids);

    $map = array();
    $epochs = array();
    foreach ($identifiers as $identifier) {
      $skip_href = $base_href.'?before='.$identifier;

      $skip_link = javelin_tag(
        'a',
        array(
          'href'  => $skip_href,
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip'     => $skip_text,
            'align'   => 'E',
            'size'    => 300,
          ),
        ),
        $skip_icon);

      // We may not have a commit object for a given identifier if the commit
      // has not imported yet.

      // At time of writing, this can also happen if a line was part of the
      // initial import: blame produces a "^abc123" identifier in Git, which
      // doesn't correspond to a real commit.

      $commit = idx($commits, $identifier);

      $revision = null;
      if ($commit) {
        $revisions = idx($revision_map, $commit->getPHID());

        // There may be multiple edges between this commit and revisions in the
        // database. If there are, just pick one arbitrarily.
        if ($revisions) {
          $revision = head($revisions);
        }
      }

      $author_phid = null;

      if ($commit) {
        $author_phid = $commit->getAuthorDisplayPHID();
      }

      if (!$author_phid) {
        // This means we couldn't identify an author for the commit or the
        // revision. We just render a blank for alignment.
        $author_style = null;
        $author_href = null;
        $author_sigil = null;
        $author_meta = null;
      } else {
        $author_src = $handles[$author_phid]->getImageURI();
        $author_style = 'background-image: url('.$author_src.');';
        $author_href = $handles[$author_phid]->getURI();
        $author_sigil = 'has-tooltip';
        $author_meta = array(
          'tip' => $handles[$author_phid]->getName(),
          'align' => 'E',
          'size'  => 'auto',
        );
      }

      $author_link = javelin_tag(
        $author_href ? 'a' : 'span',
        array(
          'class' => 'phabricator-source-blame-author',
          'style' => $author_style,
          'href' => $author_href,
          'sigil' => $author_sigil,
          'meta' => $author_meta,
        ));

      if ($commit) {
        $commit_link = javelin_tag(
          'a',
          array(
            'href' => $commit->getURI(),
            'sigil' => 'has-tooltip',
            'meta'  => array(
              'tip' => $this->renderCommitTooltip($commit, $handles),
              'align' => 'E',
              'size' => 600,
            ),
          ),
          $commit->getLocalName());
      } else {
        $commit_link = null;
      }

      $info = array(
        $author_link,
        $commit_link,
      );

      if ($revision) {
        $revision_link = javelin_tag(
          'a',
          array(
            'href' => $revision->getURI(),
            'sigil' => 'has-tooltip',
            'meta'  => array(
              'tip' => $this->renderRevisionTooltip($revision, $handles),
              'align' => 'E',
              'size' => 600,
            ),
          ),
          $revision->getMonogram());

        $info = array(
          $info,
          " \xC2\xB7 ",
          $revision_link,
        );
      }

      if ($commit) {
        $epoch = $commit->getEpoch();
      } else {
        $epoch = 0;
      }

      $epochs[] = $epoch;

      $data = array(
        'skip' => $skip_link,
        'info' => hsprintf('%s', $info),
        'epoch' => $epoch,
      );

      $map[$identifier] = $data;
    }

    $epoch_min = min($epochs);
    $epoch_max = max($epochs);

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'blame' => $blame,
        'map' => $map,
        'epoch' => array(
          'min' => $epoch_min,
          'max' => $epoch_max,
        ),
      ));
  }

  private function loadBlame() {
    $drequest = $this->getDiffusionRequest();

    $commit = $drequest->getCommit();
    $path = $drequest->getPath();

    $blame_timeout = 15;

    $blame = $this->callConduitWithDiffusionRequest(
      'diffusion.blame',
      array(
        'commit' => $commit,
        'paths' => array($path),
        'timeout' => $blame_timeout,
      ));

    return idx($blame, $path, array());
  }

  private function renderRevisionTooltip(
    DifferentialRevision $revision,
    $handles) {
    $viewer = $this->getViewer();

    $date = phabricator_date($revision->getDateModified(), $viewer);
    $monogram = $revision->getMonogram();
    $title = $revision->getTitle();
    $header = "{$monogram} {$title}";

    $author = $handles[$revision->getAuthorPHID()]->getName();

    return "{$header}\n{$date} \xC2\xB7 {$author}";
  }

  private function renderCommitTooltip(
    PhabricatorRepositoryCommit $commit,
    $handles) {

    $viewer = $this->getViewer();

    $date = phabricator_date($commit->getEpoch(), $viewer);
    $summary = trim($commit->getSummary());

    $author_phid = $commit->getAuthorPHID();
    if ($author_phid && isset($handles[$author_phid])) {
      $author_name = $handles[$author_phid]->getName();
    } else {
      $author_name = null;
    }

    if ($author_name) {
      return "{$summary}\n{$date} \xC2\xB7 {$author_name}";
    } else {
      return "{$summary}\n{$date}";
    }
  }

}
