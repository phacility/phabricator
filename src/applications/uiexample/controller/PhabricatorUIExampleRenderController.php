<?php

final class PhabricatorUIExampleRenderController extends PhabricatorController {

  private $class;

  public function willProcessRequest(array $data) {
    $this->class = idx($data, 'class');
  }

  public function processRequest() {

    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorUIExample')
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();
    $classes = ipull($classes, 'name', 'name');

    foreach ($classes as $class => $ignored) {
      $classes[$class] = newv($class, array());
    }

    $classes = msort($classes, 'getName');

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI('view/')));

    foreach ($classes as $class => $obj) {
      $name = $obj->getName();
      $nav->addFilter($class, $name);
    }

    $selected = $nav->selectFilter($this->class, head_key($classes));

    $example = $classes[$selected];
    $example->setRequest($this->getRequest());

    $result = $example->renderExample();
    if ($result instanceof AphrontResponse) {
      // This allows examples to generate dialogs, etc., for demonstration.
      return $result;
    }

    require_celerity_resource('phabricator-ui-example-css');

    $nav->appendChild(hsprintf(
      '<div class="phabricator-ui-example-header">'.
        '<h1 class="phabricator-ui-example-name">%s (%s)</h1>'.
        '<p class="phabricator-ui-example-description">%s</p>'.
      '</div>',
      $example->getName(),
      get_class($example),
      $example->getDescription()));

    $nav->appendChild($result);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => 'UI Example',
        'device'  => true,
      ));
  }

}
