<?php

/**
 * Parse the "X_FORWARDED_FOR" HTTP header to determine the original client
 * address.
 *
 * @param  int  Number of devices to trust.
 * @return void
 */
function preamble_trust_x_forwarded_for_header($layers = 1) {
  if (!is_int($layers) || ($layers < 1)) {
    echo
      'preamble_trust_x_forwarded_for_header(<layers>): '.
      '"layers" parameter must an integer larger than 0.'."\n";
    echo "\n";
    exit(1);
  }

  if (!isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return;
  }

  $forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'];
  if ($forwarded_for === null || !strlen($forwarded_for)) {
    return;
  }

  $address = preamble_get_x_forwarded_for_address($forwarded_for, $layers);

  $_SERVER['REMOTE_ADDR'] = $address;
}

function preamble_get_x_forwarded_for_address($raw_header, $layers) {
  // The raw header may be a list of IPs, like "1.2.3.4, 4.5.6.7", if the
  // request the load balancer received also had this header. In particular,
  // this happens routinely with requests received through a CDN, but can also
  // happen illegitimately if the client just makes up an "X-Forwarded-For"
  // header full of lies.

  // We can only trust the N elements at the end of the list which correspond
  // to network-adjacent devices we control. Usually, we're behind a single
  // load balancer and "N" is 1, so we want to take the last element in the
  // list.

  // In some cases, "N" may be more than 1, if the network is configured so
  // that that requests are routed through multiple layers of load balancers
  // and proxies. In this case, we want to take the Nth-to-last element of
  // the list.

  $addresses = explode(',', $raw_header);

  // If we have more than one trustworthy device on the network path, discard
  // corresponding elements from the list. For example, if we have 7 devices,
  // we want to discard the last 6 elements of the list.

  // The final device address does not appear in the list, since devices do
  // not append their own addresses to "X-Forwarded-For".

  $discard_addresses = ($layers - 1);

  // However, we don't want to throw away all of the addresses. Some requests
  // may originate from within the network, and may thus not have as many
  // addresses as we expect. If we have fewer addresses than trustworthy
  // devices, discard all but one address.

  $max_discard = (count($addresses) - 1);

  $discard_count = min($discard_addresses, $max_discard);
  if ($discard_count) {
    $addresses = array_slice($addresses, 0, -$discard_count);
  }

  $original_address = end($addresses);
  $original_address = trim($original_address);

  return $original_address;
}
