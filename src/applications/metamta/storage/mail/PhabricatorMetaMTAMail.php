<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * See #394445 for an explanation of why this thing even exists.
 */
class PhabricatorMetaMTAMail extends PhabricatorMetaMTADAO {

  const STATUS_QUEUE = 'queued';
  const STATUS_SENT  = 'sent';
  const STATUS_FAIL  = 'fail';

  const MAX_RETRIES   = 250;
  const RETRY_DELAY   = 5;

  protected $parameters;
  protected $status;
  protected $message;
  protected $retryCount;
  protected $nextRetry;
  protected $relatedPHID;

  public function __construct() {

    $this->status     = self::STATUS_QUEUE;
    $this->retryCount = 0;
    $this->nextRetry  = time();
    $this->parameters = array();

    parent::__construct();
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'parameters'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  protected function setParam($param, $value) {
    $this->parameters[$param] = $value;
    return $this;
  }

  protected function getParam($param) {
    return idx($this->parameters, $param);
  }

  public function getSubject() {
    return $this->getParam('subject');
  }

  public function addTos(array $phids) {
    $this->setParam('to', $phids);
    return $this;
  }

  public function addCCs(array $phids) {
    $this->setParam('cc', $phids);
    return $this;
  }

  public function addHeader($name, $value) {
    $this->parameters['headers'][$name] = $value;
    return $this;
  }

  public function setFrom($from) {
    $this->setParam('from', $from);
    return $this;
  }

  public function setReplyTo($reply_to) {
    $this->setParam('reply-to', $reply_to);
    return $this;
  }

  public function setSubject($subject) {
    $this->setParam('subject', $subject);
    return $this;
  }

  public function setBody($body) {
    $this->setParam('body', $body);
    return $this;
  }

  public function setIsHTML($html) {
    $this->setParam('is-html', $html);
    return $this;
  }

  public function getSimulatedFailureCount() {
    return nonempty($this->getParam('simulated-failures'), 0);
  }

  public function setSimulatedFailureCount($count) {
    $this->setParam('simulated-failures', $count);
    return $this;
  }

  public function save() {
    $try_send = (PhabricatorEnv::getEnvConfig('metamta.send-immediately')) &&
                (!$this->getID());

    $ret = parent::save();

    if ($try_send) {
      $class_name = PhabricatorEnv::getEnvConfig('metamta.mail-adapter');
      PhutilSymbolLoader::loadClass($class_name);
      $mailer = newv($class_name, array());
      $this->sendNow($force_send = false, $mailer);
    }

    return $ret;
  }


  public function sendNow(
    $force_send = false,
    PhabricatorMailImplementationAdapter $mailer) {

    if (!$force_send) {
      if ($this->getStatus() != self::STATUS_QUEUE) {
        throw new Exception("Trying to send an already-sent mail!");
      }

      if (time() < $this->getNextRetry()) {
        throw new Exception("Trying to send an email before next retry!");
      }
    }

    try {
      $parameters = $this->parameters;
      $phids = array();
      foreach ($parameters as $key => $value) {
        switch ($key) {
          case 'from':
          case 'to':
          case 'cc':
            if (!is_array($value)) {
              $value = array($value);
            }
            foreach (array_filter($value) as $phid) {
              $phids[] = $phid;
            }
            break;
        }
      }

      $handles = id(new PhabricatorObjectHandleData($phids))
        ->loadHandles();
        
      $params = $this->parameters;
      $default = PhabricatorEnv::getEnvConfig('metamta.default-address');
      if (empty($params['from'])) {
        $mailer->setFrom($default);
      } else if (!PhabricatorEnv::getEnvConfig('metamta.can-send-as-user')) {
        $from = $params['from'];
        if (empty($params['reply-to'])) {
          $params['reply-to'] = $handles[$from]->getEmail();
        }
        $mailer->setFrom($default);
        unset($params['from']);
      }

      foreach ($params as $key => $value) {
        switch ($key) {
          case 'from':
            $mailer->setFrom($handles[$value]->getEmail());
            break;
          case 'reply-to':
            $mailer->addReplyTo($value);
            break;
          case 'to':
            $emails = array();
            foreach ($value as $phid) {
              $emails[] = $handles[$phid]->getEmail();
            }
            $mailer->addTos($emails);
            break;
          case 'cc':
            $emails = array();
            foreach ($value as $phid) {
              $emails[] = $handles[$phid]->getEmail();
            }
            $mailer->addCCs($emails);
            break;
          case 'headers':
            foreach ($value as $header_key => $header_value) {
              $mailer->addHeader($header_key, $header_value);
            }
            break;
          case 'body':
            $mailer->setBody($value);
            break;
          case 'subject':
            $mailer->setSubject($value);
            break;
          case 'is-html':
            if ($value) {
              $mailer->setIsHTML(true);
            }
            break;
          default:
            // Just discard.
        }
      }

      $mailer->addHeader('X-Mail-Transport-Agent', 'MetaMTA');

    } catch (Exception $ex) {
      $this->setStatus(self::STATUS_FAIL);
      $this->setMessage($ex->getMessage());
      $this->save();
      return;
    }

    if ($this->getRetryCount() < $this->getSimulatedFailureCount()) {
      $ok = false;
      $error = 'Simulated failure.';
    } else {
      try {
        $ok = $mailer->send();
        $error = null;
      } catch (Exception $ex) {
        $ok = false;
        $error = $ex->getMessage();
      }
    }

    if (!$ok) {
      $this->setMessage($error);
      if ($this->getRetryCount() > self::MAX_RETRIES) {
        $this->setStatus(self::STATUS_FAIL);
      } else {
        $this->setRetryCount($this->getRetryCount() + 1);
        $next_retry = time() + ($this->getRetryCount() * self::RETRY_DELAY);
        $this->setNextRetry($next_retry);
      }
    } else {
      $this->setStatus(self::STATUS_SENT);
    }

    $this->save();
  }

  public static function getReadableStatus($status_code) {
    static $readable = array(
      self::STATUS_QUEUE => "Queued for Delivery",
      self::STATUS_FAIL  => "Delivery Failed",
      self::STATUS_SENT  => "Sent",
    );
    $status_code = coalesce($status_code, '?');
    return idx($readable, $status_code, $status_code);
  }

}
