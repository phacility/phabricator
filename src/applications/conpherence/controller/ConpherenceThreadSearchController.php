<?php

final class ConpherenceThreadSearchController
  extends ConpherenceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $conpherence_id = $request->getURIData('id');
    $fulltext = $request->getStr('fulltext');

    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withIDs(array($conpherence_id))
      ->executeOne();

    if (!$conpherence) {
      return new Aphront404Response();
    }

    $engine = new ConpherenceThreadSearchEngine();
    $engine->setViewer($viewer);
    $saved = $engine->buildSavedQueryFromBuiltin('all')
      ->setParameter('phids', array($conpherence->getPHID()))
      ->setParameter('fulltext', $fulltext);

    $pager = $engine->newPagerForSavedQuery($saved);
    $pager->setPageSize(15);

    $query = $engine->buildQueryFromSavedQuery($saved);

    $results = $engine->executeQuery($query, $pager);
    $view = $engine->renderResults($results, $saved);

    return id(new AphrontAjaxResponse())
      ->setContent($view->getContent());
  }
}
