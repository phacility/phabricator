<?php

final class PhabricatorContactNumbersSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'contact';
  }

  public function getPanelName() {
    return pht('Contact Numbers');
  }

  public function getPanelMenuIcon() {
    return 'fa-mobile';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsAuthenticationPanelGroup::PANELGROUPKEY;
  }

  public function isMultiFactorEnrollmentPanel() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $this->getUser();
    $viewer = $request->getUser();

    $numbers = id(new PhabricatorAuthContactNumberQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($user->getPHID()))
      ->execute();

    $rows = array();
    foreach ($numbers as $number) {
      $rows[] = array(
        $number->newIconView(),
        phutil_tag(
          'a',
          array(
            'href' => $number->getURI(),
          ),
          $number->getDisplayName()),
        phabricator_datetime($number->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht("You haven't added any contact numbers to your account."))
      ->setHeaders(
        array(
          null,
          pht('Number'),
          pht('Created'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
          'right',
        ));

    $buttons = array();

    $buttons[] = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-plus')
      ->setText(pht('Add Contact Number'))
      ->setHref('/auth/contact/edit/')
      ->setColor(PHUIButtonView::GREY);

    return $this->newBox(pht('Contact Numbers'), $table, $buttons);
  }

}
