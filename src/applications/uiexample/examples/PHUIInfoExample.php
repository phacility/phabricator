<?php

final class PHUIInfoExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Info View');
  }

  public function getDescription() {
    return pht(
      'Use %s to render errors, warnings and notices.',
      phutil_tag('tt', array(), 'PHUIInfoView'));
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $sevs = array(
      PHUIInfoView::SEVERITY_ERROR    => pht('Error'),
      PHUIInfoView::SEVERITY_WARNING  => pht('Warning'),
      PHUIInfoView::SEVERITY_NODATA   => pht('No Data'),
      PHUIInfoView::SEVERITY_NOTICE   => pht('Notice'),
      PHUIInfoView::SEVERITY_SUCCESS  => pht('Success'),
    );

    $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Resolve Issue'))
        ->setHref('#');

    $views = array();
    // Only Title
    foreach ($sevs as $sev => $title) {
      $view = new PHUIInfoView();
      $view->setSeverity($sev);
      $view->setTitle($title);
      $views[] = $view;
    }
    $views[] = phutil_tag('br', array(), null);

    // Only Body
    foreach ($sevs as $sev => $title) {
      $view = new PHUIInfoView();
      $view->setSeverity($sev);
      $view->appendChild(pht('Several issues were encountered.'));
      $view->addButton($button);
      $views[] = $view;
    }
    $views[] = phutil_tag('br', array(), null);

    // Only Errors
    foreach ($sevs as $sev => $title) {
      $view = new PHUIInfoView();
      $view->setSeverity($sev);
      $view->setErrors(
        array(
          pht('Overcooked.'),
          pht('Too much salt.'),
          pht('Full of sand.'),
        ));
      $views[] = $view;
    }
    $views[] = phutil_tag('br', array(), null);

    // All
    foreach ($sevs as $sev => $title) {
      $view = new PHUIInfoView();
      $view->setSeverity($sev);
      $view->setTitle($title);
      $view->appendChild(pht('Several issues were encountered.'));
      $view->setErrors(
        array(
          pht('Overcooked.'),
          pht('Too much salt.'),
          pht('Full of sand.'),
        ));
      $views[] = $view;
    }

    return $views;
  }
}
