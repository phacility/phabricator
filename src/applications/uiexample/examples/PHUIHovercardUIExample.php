<?php

final class PHUIHovercardUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Hovercard');
  }

  public function getDescription() {
    return pht(
      'Use %s to render hovercards.',
      phutil_tag('tt', array(), 'PHUIHovercardView'));
  }

  public function getCategory() {
    return pht('Single Use');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $elements = array();

    $diff_handle = $this->createBasicDummyHandle(
      'D123',
      DifferentialRevisionPHIDType::TYPECONST,
      pht('Introduce cooler Differential Revisions'));

    $panel = $this->createPanel(pht('Differential Hovercard'));
    $panel->appendChild(id(new PHUIHovercardView())
      ->setObjectHandle($diff_handle)
      ->addField(pht('Author'), $user->getUsername())
      ->addField(pht('Updated'), phabricator_datetime(time(), $user))
      ->addAction(pht('Subscribe'), '/dev/random')
      ->setUser($user));
    $elements[] = $panel;

    $task_handle = $this->createBasicDummyHandle(
      'T123',
      ManiphestTaskPHIDType::TYPECONST,
      pht('Improve Mobile Experience for Phabricator'));

    $tag = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE)
      ->setName(pht('Closed, Resolved'));
    $panel = $this->createPanel(pht('Maniphest Hovercard'));
    $panel->appendChild(id(new PHUIHovercardView())
      ->setObjectHandle($task_handle)
      ->setUser($user)
      ->addField(pht('Assigned to'), $user->getUsername())
      ->addField(pht('Dependent Tasks'), 'T123, T124, T125')
      ->addAction(pht('Subscribe'), '/dev/random')
      ->addAction(pht('Create Subtask'), '/dev/urandom')
      ->addTag($tag));
    $elements[] = $panel;

    $user_handle = $this->createBasicDummyHandle(
      'gwashington',
      PhabricatorPeopleUserPHIDType::TYPECONST,
      'George Washington');
    $user_handle->setImageURI(
      celerity_get_resource_uri('/rsrc/image/people/washington.png'));
    $panel = $this->createPanel(pht('Whatevery Hovercard'));
    $panel->appendChild(id(new PHUIHovercardView())
      ->setObjectHandle($user_handle)
      ->addField(pht('Status'), pht('Available'))
      ->addField(pht('Member since'), '30. February 1750')
      ->addAction(pht('Send a Message'), '/dev/null')
      ->setUser($user));
    $elements[] = $panel;

    return phutil_implode_html('', $elements);
  }

  private function createPanel($header) {
    $panel = new PHUIBoxView();
    $panel->addClass('grouped');
    $panel->addClass('ml');
    return $panel;
  }

}
