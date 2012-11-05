<?php

final class PhabricatorBarePageExample extends PhabricatorUIExample {

  public function getName() {
    return 'Bare Page';
  }

  public function getDescription() {
    return 'This is a bare page.';
  }

  public function renderExample() {
    $view = new PhabricatorBarePageView();
    $view->appendChild(
      phutil_render_tag(
        'h1',
        array(),
        phutil_escape_html($this->getDescription())));

    $response = new AphrontWebpageResponse();
    $response->setContent($view->render());
    return $response;
  }
}
