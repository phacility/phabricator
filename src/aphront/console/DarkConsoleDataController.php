<?php

/**
 * @group console
 */
final class DarkConsoleDataController extends PhabricatorController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $cache = new PhabricatorKeyValueDatabaseCache();
    $cache = new PhutilKeyValueCacheProfiler($cache);
    $cache->setProfiler(PhutilServiceProfiler::getInstance());

    $result = $cache->getKey('darkconsole:'.$this->key);
    if (!$result) {
      return new Aphront400Response();
    }

    $result = json_decode($result, true);

    if (!is_array($result)) {
      return new Aphront400Response();
    }

    if ($result['vers'] != DarkConsoleCore::STORAGE_VERSION) {
      return new Aphront400Response();
    }

    if ($result['user'] != $user->getPHID()) {
      return new Aphront400Response();
    }

    $output = array();
    $output['tabs'] = $result['tabs'];
    $output['panel'] = array();

    foreach ($result['data'] as $class => $data) {
      try {
        $obj = newv($class, array());
        $obj->setData($data);
        $obj->setRequest($request);

        $panel = $obj->renderPanel();

        if (!empty($_COOKIE['phsid'])) {
          $panel = str_replace(
            $_COOKIE['phsid'],
            '(session-key)',
            $panel);
        }

        $output['panel'][$class] = $panel;
      } catch (Exception $ex) {
        $output['panel'][$class] = 'error';
      }
    }

    return id(new AphrontAjaxResponse())->setContent($output);
  }

}
