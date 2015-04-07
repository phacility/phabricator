<?php

final class PhabricatorConfigCacheController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('cache/');

    $title = pht('Cache Status');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Cache Status'));

    $code_box = $this->renderCodeBox();
    $data_box = $this->renderDataBox();

    $nav->appendChild(
      array(
        $crumbs,
        $code_box,
        $data_box,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function renderCodeBox() {
    $cache = PhabricatorOpcodeCacheSpec::getActiveCacheSpec();

    $properties = id(new PHUIPropertyListView());

    $this->renderCommonProperties($properties, $cache);

    return id(new PHUIObjectBoxView())
      ->setFormErrors($this->renderIssues($cache->getIssues()))
      ->setHeaderText(pht('Opcode Cache'))
      ->addPropertyList($properties);
  }

  private function renderDataBox() {
    $cache = PhabricatorDataCacheSpec::getActiveCacheSpec();

    $properties = id(new PHUIPropertyListView());

    $this->renderCommonProperties($properties, $cache);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Data Cache'))
      ->addPropertyList($properties);
  }

  private function renderCommonProperties(
    PHUIPropertyListView $properties,
    PhabricatorCacheSpec $cache) {

    if ($cache->getName() !== null) {
      $name = $this->renderYes($cache->getName());
    } else {
      $name = $this->renderNo(pht('None'));
    }
    $properties->addProperty(pht('Cache'), $name);

    if ($cache->getIsEnabled()) {
      $enabled = $this->renderYes(pht('Enabled'));
    } else {
      $enabled = $this->renderNo(pht('Not Enabled'));
    }
    $properties->addProperty(pht('Enabled'), $enabled);

    $version = $cache->getVersion();
    if ($version) {
      $properties->addProperty(pht('Version'), $this->renderInfo($version));
    }
  }

  private function renderIssues(array $issues) {
    $result = array();
    foreach ($issues as $issue) {
      $title = $issue['title'];
      $body = $issue['body'];
      $result[] = array(
        phutil_tag('strong', array(), $title.':'),
        ' ',
        $body,
      );
    }
    return $result;
  }

  private function renderYes($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-check', 'green'),
      ' ',
      $info,
    );
  }

  private function renderNo($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-times-circle', 'red'),
      ' ',
      $info,
    );
  }

  private function renderInfo($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-info-circle', 'grey'),
      ' ',
      $info,
    );
  }

}
