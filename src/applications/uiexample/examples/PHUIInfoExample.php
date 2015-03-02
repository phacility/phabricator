<?php

final class PHUIInfoExample extends PhabricatorUIExample {

  public function getName() {
    return 'Info View';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>PHUIInfoView</tt> to render errors, warnings and notices.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $sevs = array(
      PHUIInfoView::SEVERITY_ERROR    => 'Error',
      PHUIInfoView::SEVERITY_WARNING  => 'Warning',
      PHUIInfoView::SEVERITY_NODATA   => 'No Data',
      PHUIInfoView::SEVERITY_NOTICE   => 'Notice',
      PHUIInfoView::SEVERITY_SUCCESS   => 'Success',
    );

    $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setText('Resolve Issue')
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
      $view->appendChild('Several issues were encountered.');
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
          'Overcooked.',
          'Too much salt.',
          'Full of sand.',
        ));
      $views[] = $view;
    }
    $views[] = phutil_tag('br', array(), null);

    // All
    foreach ($sevs as $sev => $title) {
      $view = new PHUIInfoView();
      $view->setSeverity($sev);
      $view->setTitle($title);
      $view->appendChild('Several issues were encountered.');
      $view->setErrors(
        array(
          'Overcooked.',
          'Too much salt.',
          'Full of sand.',
        ));
      $views[] = $view;
    }

    return $views;
  }
}
