<?php

final class PhabricatorSearchHandleController
  extends PhabricatorSearchBaseController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $phid = $request->getURIData('phid');

    $handles = $viewer->loadHandles(array($phid));
    $handle = $handles[$phid];

    $cancel_uri = $handle->getURI();
    if (!$cancel_uri) {
      $cancel_uri = '/';
    }

    $rows = array();

    $rows[] = array(
      pht('PHID'),
      $phid,
    );

    $rows[] = array(
      pht('PHID Type'),
      phid_get_type($phid),
    );

    $rows[] = array(
      pht('URI'),
      $handle->getURI(),
    );

    $icon = $handle->getIcon();
    if ($icon !== null) {
      $icon = id(new PHUIIconView())
        ->setIcon($handle->getIcon());
    }

    $rows[] = array(
      pht('Icon'),
      $icon,
    );

    $rows[] = array(
      pht('Object Name'),
      $handle->getObjectName(),
    );

    $rows[] = array(
      pht('Name'),
      $handle->getName(),
    );

    $rows[] = array(
      pht('Full Name'),
      $handle->getFullName(),
    );

    $rows[] = array(
      pht('Tag'),
      $handle->renderTag(),
    );

    $rows[] = array(
      pht('Link'),
      $handle->renderLink(),
    );

    $table = id(new AphrontTableView($rows))
      ->setColumnClasses(
        array(
          'header',
          'wide',
        ));

    return $this->newDialog()
      ->setTitle(pht('Handle: %s', $phid))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendChild($table)
      ->addCancelButton($cancel_uri, pht('Done'));
  }

}
