<?php

final class PHUIErrorExample extends PhabricatorUIExample {

  public function getName() {
    return 'Errors';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>PHUIErrorView</tt> to render errors, warnings and notices.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $sevs = array(
      PHUIErrorView::SEVERITY_ERROR    => 'Error',
      PHUIErrorView::SEVERITY_WARNING  => 'Warning',
      PHUIErrorView::SEVERITY_NOTICE   => 'Notice',
      PHUIErrorView::SEVERITY_NODATA   => 'No Data',
    );

    $views = array();
    // Only Title
    foreach ($sevs as $sev => $title) {
      $view = new PHUIErrorView();
      $view->setSeverity($sev);
      $view->setTitle($title);
      $views[] = $view;
    }
    // Only Body
    foreach ($sevs as $sev => $title) {
      $view = new PHUIErrorView();
      $view->setSeverity($sev);
      $view->appendChild('Several issues were encountered.');
      $views[] = $view;
    }
    // Only Errors
    foreach ($sevs as $sev => $title) {
      $view = new PHUIErrorView();
      $view->setSeverity($sev);
      $view->setErrors(
        array(
          'Overcooked.',
          'Too much salt.',
          'Full of sand.',
        ));
      $views[] = $view;
    }
    // All
    foreach ($sevs as $sev => $title) {
      $view = new PHUIErrorView();
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
