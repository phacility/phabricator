<?php

final class DiffusionRefTableController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
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

        $identifier = idx($vcs, 'identifier');
        if ($identifier !== null) {
          $identifier = DiffusionView::linkCommit(
            $repository,
            $identifier);
        }

        $cache_identifier = idx($cache, 'identifier');
        if ($cache_identifier !== null) {
          $cache_identifier = DiffusionView::linkCommit(
            $repository,
            $cache_identifier);
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
          $identifier,
          $cache_identifier,
          $alternate,
        );
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Ref'),
          pht('Type'),
          pht('Identifier'),
          pht('Cached'),
          pht('Alternate'),
        ));

    $content = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Ref "%s"', $ref_name))
      ->appendChild($table);

    $crumbs = $this->buildCrumbs(array());
    $crumbs->addTextCrumb(pht('Refs'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => array(
          pht('Refs'),
          $repository->getMonogram(),
          $ref_name,
        ),
      ));
  }

}
