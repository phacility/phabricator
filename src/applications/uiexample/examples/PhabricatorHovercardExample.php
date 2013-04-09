<?php

final class PhabricatorHovercardExample extends PhabricatorUIExample {

  public function getName() {
    return 'Hovercard';
  }

  public function getDescription() {
    return hsprintf('Use <tt>PhabricatorHovercardView</tt> to render '.
      'hovercards. Aren\'t I genius?');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $elements = array();

    $diff_handle = $this->createBasicDummyHandle(
      "D123",
      PhabricatorPHIDConstants::PHID_TYPE_DREV,
      "Introduce cooler Differential Revisions");

    $panel = $this->createPanel("Differential Hovercard");
    $panel->appendChild(id(new PhabricatorHovercardView())
      ->setObjectHandle($diff_handle)
      ->addField(pht('Author'), $user->getUsername())
      ->addField(pht('Updated'), phabricator_datetime(time(), $user))
      ->addAction(pht('Subscribe'), '/dev/random')
      ->setUser($user));
    $elements[] = $panel;

    $task_handle = $this->createBasicDummyHandle(
      "T123",
      PhabricatorPHIDConstants::PHID_TYPE_TASK,
      "Improve Mobile Experience for Phabricator");

    $tag = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE)
      ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK)
      ->setName('Abandoned (Really)');
    $panel = $this->createPanel("Maniphest Hovercard");
    $panel->appendChild(id(new PhabricatorHovercardView())
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
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      'George Washington');
    $user_handle->setImageURI(
      celerity_get_resource_uri('/rsrc/image/people/washington.png'));
    $panel = $this->createPanel("Whatevery Hovercard");
    $panel->appendChild(id(new PhabricatorHovercardView())
      ->setObjectHandle($user_handle)
      ->addField(pht('Status'), 'Available')
      ->addField(pht('Member since'), '30. February 1750')
      ->addAction(pht('Start a Conpherence'), '/dev/null')
      ->setUser($user));
    $elements[] = $panel;

    return phutil_implode_html("", $elements);
  }

  private function createPanel($header) {
    $panel = new AphrontPanelView();
    $panel->setNoBackground();
    $panel->setHeader($header);
    return $panel;
  }

}
