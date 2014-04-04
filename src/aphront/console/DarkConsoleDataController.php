<?php

/**
 * @group console
 */
final class DarkConsoleDataController extends PhabricatorController {

  private $key;

  public function shouldRequireLogin() {
    return !PhabricatorEnv::getEnvConfig('darkconsole.always-on');
  }

  public function shouldRequireEnabledUser() {
    return !PhabricatorEnv::getEnvConfig('darkconsole.always-on');
  }

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

        // Because cookie names can now be prefixed, wipe out any cookie value
        // with the session cookie name anywhere in its name.
        $pattern = '('.preg_quote(PhabricatorCookies::COOKIE_SESSION).')';
        foreach ($_COOKIE as $cookie_name => $cookie_value) {
          if (preg_match($pattern, $cookie_name)) {
            $panel = PhutilSafeHTML::applyFunction(
              'str_replace',
              $cookie_value,
              '(session-key)',
              $panel);
          }
        }

        $output['panel'][$class] = $panel;
      } catch (Exception $ex) {
        $output['panel'][$class] = 'error';
      }
    }

    return id(new AphrontAjaxResponse())->setContent($output);
  }

}
