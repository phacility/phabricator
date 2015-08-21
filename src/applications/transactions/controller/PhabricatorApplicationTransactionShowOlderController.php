<?php

final class PhabricatorApplicationTransactionShowOlderController
  extends PhabricatorApplicationTransactionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $object = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($request->getURIData('phid')))
      ->setViewer($viewer)
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }
    if (!$object instanceof PhabricatorApplicationTransactionInterface) {
      return new Aphront404Response();
    }

    $template = $object->getApplicationTransactionTemplate();
    $queries = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationTransactionQuery')
      ->execute();

    $object_query = null;
    foreach ($queries as $query) {
      if ($query->getTemplateApplicationTransaction() == $template) {
        $object_query = $query;
        break;
      }
    }

    if (!$object_query) {
      return new Aphront404Response();
    }

    $timeline = $this->buildTransactionTimeline(
      $object,
      $query);

    $phui_timeline = $timeline->buildPHUITimelineView($with_hiding = false);
    $phui_timeline->setShouldAddSpacers(false);
    $events = $phui_timeline->buildEvents();

    return id(new AphrontAjaxResponse())
      ->setContent(array(
        'timeline' => hsprintf('%s', $events),
      ));
  }

}
