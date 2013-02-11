<?php

final class PhabricatorErrorExample extends PhabricatorUIExample {

  public function getName() {
    return 'Errors';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>AphrontErrorView</tt> to render errors, warnings and notices.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $sevs = array(
      AphrontErrorView::SEVERITY_ERROR    => 'Error',
      AphrontErrorView::SEVERITY_WARNING  => 'Warning',
      AphrontErrorView::SEVERITY_NOTICE   => 'Notice',
      AphrontErrorView::SEVERITY_NODATA   => 'No Data',
    );

    $views = array();
    foreach ($sevs as $sev => $title) {
      $view = new AphrontErrorView();
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
