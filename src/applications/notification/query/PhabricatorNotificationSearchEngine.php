<?php

final class PhabricatorNotificationSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Notifications');
  }

  public function getApplicationClassName() {
    return 'PhabricatorNotificationsApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'unread',
      $this->readBoolFromRequest($request, 'unread'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorNotificationQuery())
      ->withUserPHIDs(array($this->requireViewer()->getPHID()));

    if ($saved->getParameter('unread')) {
      $query->withUnread(true);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $unread = $saved->getParameter('unread');

    $form->appendChild(
      id(new AphrontFormCheckboxControl())
        ->setLabel(pht('Unread'))
        ->addCheckbox(
          'unread',
          1,
          pht('Show only unread notifications.'),
          $unread));
  }

  protected function getURI($path) {
    return '/notification/'.$path;
  }

  protected function getBuiltinQueryNames() {

    $names = array(
      'all' => pht('All Notifications'),
      'unread' => pht('Unread Notifications'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'unread':
        return $query->setParameter('unread', true);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $notifications,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($notifications, 'PhabricatorFeedStory');

    $viewer = $this->requireViewer();

    $image = id(new PHUIIconView())
      ->setIcon('fa-eye-slash');

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->addSigil('workflow')
      ->setColor(PHUIButtonView::SIMPLE)
      ->setIcon($image)
      ->setText(pht('Mark All Read'));

    switch ($query->getQueryKey()) {
      case 'unread':
        $header = pht('Unread Notifications');
        $no_data = pht('You have no unread notifications.');
        break;
      default:
        $header = pht('Notifications');
        $no_data = pht('You have no notifications.');
        break;
    }

    $clear_uri = id(new PhutilURI('/notification/clear/'));
    if ($notifications) {
      $builder = id(new PhabricatorNotificationBuilder($notifications))
        ->setUser($viewer);

      $view = $builder->buildView();
      $clear_uri->setQueryParam(
        'chronoKey',
        head($notifications)->getChronologicalKey());
    } else {
      $view = phutil_tag_div(
        'phabricator-notification no-notifications',
        $no_data);
      $button->setDisabled(true);
    }
    $button->setHref((string)$clear_uri);

    $view = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_MEDIUM)
      ->addClass('phabricator-notification-list')
      ->appendChild($view);

    $result = new PhabricatorApplicationSearchResultView();
    $result->addAction($button);
    $result->setContent($view);

    return $result;
  }

  public function shouldUseOffsetPaging() {
    return true;
  }

}
