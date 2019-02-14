<?php

final class PhabricatorFactHomeController extends PhabricatorFactController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    if ($request->isFormPost()) {
      $uri = new PhutilURI('/fact/chart/');
      $uri->replaceQueryParam('y1', $request->getStr('y1'));
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $chart_form = $this->buildChartForm();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Home'));

    $title = pht('Facts');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $chart_form,
        ));
  }

  private function buildChartForm() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $specs = PhabricatorFact::getAllFacts();
    $options = mpull($specs, 'getName', 'getKey');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Y-Axis'))
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
