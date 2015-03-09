<?php

final class PhabricatorFactHomeController extends PhabricatorFactController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $uri = new PhutilURI('/fact/chart/');
      $uri->setQueryParam('y1', $request->getStr('y1'));
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $types = array(
      '+N:*',
      '+N:DREV',
      'updated',
    );

    $engines = PhabricatorFactEngine::loadAllEngines();
    $specs = PhabricatorFactSpec::newSpecsForFactTypes($engines, $types);

    $facts = id(new PhabricatorFactAggregate())->loadAllWhere(
      'factType IN (%Ls)',
      $types);

    $rows = array();
    foreach ($facts as $fact) {
      $spec = $specs[$fact->getFactType()];

      $name = $spec->getName();
      $value = $spec->formatValueForDisplay($user, $fact->getValueX());

      $rows[] = array($name, $value);
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Fact'),
        pht('Value'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'n',
      ));

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Facts'));
    $panel->appendChild($table);

    $chart_form = $this->buildChartForm();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Home'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $chart_form,
        $panel,
      ),
      array(
        'title' => pht('Facts'),
      ));
  }

  private function buildChartForm() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $table = new PhabricatorFactRaw();
    $conn_r = $table->establishConnection('r');
    $table_name = $table->getTableName();

    $facts = queryfx_all(
      $conn_r,
      'SELECT DISTINCT factType from %T',
      $table_name);

    $specs = PhabricatorFactSpec::newSpecsForFactTypes(
      PhabricatorFactEngine::loadAllEngines(),
      ipull($facts, 'factType'));

    $options = array();
    foreach ($specs as $spec) {
      if ($spec->getUnit() == PhabricatorFactSpec::UNIT_COUNT) {
        $options[$spec->getType()] = $spec->getName();
      }
    }

    if (!$options) {
      return id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NODATA)
        ->setTitle(pht('No Chartable Facts'))
        ->appendChild(phutil_tag(
          'p',
          array(),
          pht('There are no facts that can be plotted yet.')));
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Y-Axis')
          ->setName('y1')
          ->setOptions($options))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Plot Chart')));

    $panel = new PHUIObjectBoxView();
    $panel->setForm($form);
    $panel->setHeaderText(pht('Plot Chart'));

    return $panel;
  }

}
