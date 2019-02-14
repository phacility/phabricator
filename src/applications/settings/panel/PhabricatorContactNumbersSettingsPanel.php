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
    return 'fa-hashtag';
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
    $numbers = msortv($numbers, 'getSortVector');

    $rows = array();
    $row_classes = array();
    foreach ($numbers as $number) {
      if ($number->getIsPrimary()) {
        $primary_display = pht('Primary');
        $row_classes[] = 'highlighted';
      } else {
        $primary_display = null;
        $row_classes[] = null;
      }

      $rows[] = array(
        $number->newIconView(),
        phutil_tag(
          'a',
          array(
            'href' => $number->getURI(),
          ),
          $number->getDisplayName()),
        $primary_display,
        phabricator_datetime($number->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht("You haven't added any contact numbers to your account."))
      ->setRowClasses($row_classes)
      ->setHeaders(
        array(
          null,
          pht('Number'),
          pht('Status'),
          pht('Created'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
          null,
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
