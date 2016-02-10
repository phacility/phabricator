<?php

final class DiffusionRefTableController extends DiffusionController {

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

    if (!$drequest->supportsBranches()) {
      return $this->newDialog()
        ->setTitle(pht('No Ref Support'))
        ->appendParagraph(
          pht(
            'The version control system this repository uses does not '.
            'support named references, so you can not resolve or list '.
            'repository refs in this repository.'))
        ->addCancelButton($repository->getURI());
    }

    $ref_name = $drequest->getBranch();

    $cache_query = id(new DiffusionCachedResolveRefsQuery())
      ->setRepository($repository);
    if ($ref_name !== null) {
      $cache_query->withRefs(array($ref_name));
    }
    $cache_refs = $cache_query->execute();

    $vcs_refs = DiffusionQuery::callConduitWithDiffusionRequest(
      $viewer,
      $drequest,
      'diffusion.resolverefs',
      array(
        'refs' => array($ref_name),
      ));

    $all = array();
    foreach ($cache_refs as $ref => $results) {
      foreach ($results as $result) {
        $id = $result['type'].'/'.$result['identifier'];
        $all[$ref][$id]['cache'] = $result;
      }
    }

    foreach ($vcs_refs as $ref => $results) {
      foreach ($results as $result) {
        $id = $result['type'].'/'.$result['identifier'];
        $all[$ref][$id]['vcs'] = $result;
      }
    }

    $rows = array();
    foreach ($all as $ref => $results) {
      foreach ($results as $info) {
        $cache = idx($info, 'cache', array());
        $vcs = idx($info, 'vcs', array());

        $type = idx($vcs, 'type');
        if (!$type) {
          $type = idx($cache, 'type');
        }

        $hash = idx($vcs, 'identifier');
        if ($hash !== null) {
          $hash = DiffusionView::linkCommit(
            $repository,
            $hash);
        }

        $cached_hash = idx($cache, 'identifier');
        if ($cached_hash !== null) {
          $cached_hash = DiffusionView::linkCommit(
            $repository,
            $cached_hash);
        }

        $closed = idx($vcs, 'closed', false);
        if (!$vcs) {
          $state = null;
        } else {
          $state = $closed ? pht('Closed') : pht('Open');
        }

        $cached_closed = idx($cache, 'closed', false);
        if (!$cache) {
          $cached_state = null;
        } else {
          $cached_state = $cached_closed ? pht('Closed') : pht('Open');
        }

        $alternate = idx($vcs, 'alternate');
        if ($alternate !== null) {
          $alternate = DiffusionView::linkCommit(
            $repository,
            $alternate);
        }

        $rows[] = array(
          $ref,
          $type,
          $hash,
          $cached_hash,
          $state,
          $cached_state,
          $alternate,
        );
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Ref'),
          pht('Type'),
          pht('Hash'),
          pht('Cached Hash'),
          pht('State'),
          pht('Cached State'),
          pht('Alternate'),
        ));

    $content = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Ref "%s"', $ref_name))
      ->setTable($table);

    $crumbs = $this->buildCrumbs(array());
    $crumbs->addTextCrumb(pht('Refs'));

    return $this->newPage()
      ->setTitle(
        array(
          $ref_name,
          pht('Ref'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

}
