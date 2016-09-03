<?php

final class PhabricatorConfigRequestExceptionHandlerModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'exception-handler';
  }

  public function getModuleName() {
    return pht('Exception Handlers');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $handlers = AphrontRequestExceptionHandler::getAllHandlers();

    $rows = array();
    foreach ($handlers as $key => $handler) {
      $rows[] = array(
        $handler->getRequestExceptionHandlerPriority(),
        $key,
        $handler->getRequestExceptionHandlerDescription(),
      );
    }

    return id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Priority'),
          pht('Class'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri',
          'wide',
        ));
  }

}
