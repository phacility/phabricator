<?php

final class PhabricatorNotificationStatusController
  extends PhabricatorNotificationController {

  public function processRequest() {
    try {
      $status = PhabricatorNotificationClient::getServerStatus();
      $status = $this->renderServerStatus($status);
    } catch (Exception $ex) {
      $status = new PHUIInfoView();
      $status->setTitle('Notification Server Issue');
      $status->appendChild(hsprintf(
        'Unable to determine server status. This probably means the server '.
        'is not in great shape. The specific issue encountered was:'.
        '<br />'.
        '<br />'.
        '<strong>%s</strong> %s',
        get_class($ex),
        phutil_escape_html_newlines($ex->getMessage())));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Status'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $status,
      ),
      array(
        'title' => pht('Notification Server Status'),
        'device' => false,
      ));
  }

  private function renderServerStatus(array $status) {

    $rows = array();
    foreach ($status as $key => $value) {
      switch ($key) {
        case 'uptime':
          $value /= 1000;
          $value = phutil_format_relative_time_detailed($value);
          break;
        case 'log':
        case 'instance':
          break;
        default:
          $value = number_format($value);
          break;
      }

      $rows[] = array($key, $value);
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $test_icon = id(new PHUIIconView())
      ->setIconFont('fa-exclamation-triangle');

    $test_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setWorkflow(true)
        ->setText(pht('Send Test Notification'))
        ->setHref($this->getApplicationURI('test/'))
        ->setIcon($test_icon);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Notification Server Status'))
      ->addActionLink($test_button);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);

    return $box;
  }
}
