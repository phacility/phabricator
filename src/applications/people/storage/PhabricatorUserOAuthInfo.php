<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorUserOAuthInfo extends PhabricatorUserDAO {

  const TOKEN_STATUS_NONE     = 'none';
  const TOKEN_STATUS_GOOD     = 'good';
  const TOKEN_STATUS_FAIL     = 'fail';
  const TOKEN_STATUS_EXPIRED  = 'xpyr';

  protected $userID;
  protected $oauthProvider;
  protected $oauthUID;

  protected $accountURI;
  protected $accountName;

  protected $token;
  protected $tokenExpires;
  protected $tokenScope;
  protected $tokenStatus;

  public function getTokenStatus() {
    if (!$this->token) {
      return self::TOKEN_STATUS_NONE;
    }

    if ($this->tokenExpires && $this->tokenExpires <= time()) {
      return self::TOKEN_STATUS_EXPIRED;
    }

    return $this->tokenStatus;
  }

  public static function getReadableTokenStatus($status) {
    static $map = array(
      self::TOKEN_STATUS_NONE     => 'No Token',
      self::TOKEN_STATUS_GOOD     => 'Token Good',
      self::TOKEN_STATUS_FAIL     => 'Token Failed',
      self::TOKEN_STATUS_EXPIRED  => 'Token Expired',
    );
    return idx($map, $status, 'Unknown');
  }

}
