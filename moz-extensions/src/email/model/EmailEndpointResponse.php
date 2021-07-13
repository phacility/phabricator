<?php


class EmailEndpointResponse {
  public EmailEndpointResponseData $data;
  public EmailEndpointResponseCursor $cursor;

  public function __construct(EmailEndpointResponseData $data, EmailEndpointResponseCursor $cursor) {
    $this->data = $data;
    $this->cursor = $cursor;
  }


}