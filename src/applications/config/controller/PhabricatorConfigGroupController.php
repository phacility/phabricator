<?php

final class PhabricatorConfigGroupController
  extends PhabricatorConfigController {

  private $groupKey;

  public function willProcessRequest(array $data) {
    $this->groupKey = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $options = idx($groups, $this->groupKey);
    if (!$options) {
      return new Aphront404Response();
    }

    $title = pht('%s Configuration', $options->getName());

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $list = $this->buildOptionList($options->getOptions());

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Config'))
          ->setHref($this->getApplicationURI()))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($options->getName())
          ->setHref($this->getApplicationURI()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $list,
      ),
      array(
        'title' => $title,
        'device' => true,
      )
    );
  }

  private function buildOptionList(array $options) {
    assert_instances_of($options, 'PhabricatorConfigOption');

    $list = new PhabricatorObjectItemListView();
    foreach ($options as $option) {
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($option->getKey())
        ->setHref('/config/edit/'.$option->getKey().'/')
        ->addAttribute(phutil_escape_html($option->getSummary()));
      $list->addItem($item);
    }

    return $list;
  }

}
