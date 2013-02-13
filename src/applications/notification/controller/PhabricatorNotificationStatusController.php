<?php

final class PhabricatorNotificationStatusController
  extends PhabricatorNotificationController {

  public function processRequest() {

    $uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
    $uri = new PhutilURI($uri);

    $uri->setPath('/status/');

    $future = id(new HTTPSFuture($uri))
      ->setTimeout(3);

    try {
      list($body) = $future->resolvex();
      $body = json_decode($body, true);
      if (!is_array($body)) {
        throw new Exception("Expected JSON response from server!");
      }

      $status = $this->renderServerStatus($body);
    } catch (Exception $ex) {
      $status = new AphrontErrorView();
      $status->setTitle("Notification Server Issue");
      $status->appendChild(hsprintf(
        'Unable to determine server status. This probably means the server '.
        'is not in great shape. The specific issue encountered was:'.
        '<br />'.
        '<br />'.
        '<strong>%s</strong> %s',
        get_class($ex),
        phutil_escape_html_newlines($ex->getMessage())));
    }

    return $this->buildStandardPageResponse(
      $status,
      array(
        'title' => 'Aphlict Server Status',
      ));
  }

  private function renderServerStatus(array $status) {

    $rows = array();
    foreach ($status as $key => $value) {
      switch ($key) {
        case 'uptime':
          $value /= 1000;
          $value = phabricator_format_relative_time_detailed($value);
          break;
        case 'log':
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

    $panel = new AphrontPanelView();
    $panel->setHeader('Server Status');
    $panel->appendChild($table);

    return $panel;
  }
}
