<?php
$i = [
  'url' => str_replace('info.php', 'index.php', full_url($_SERVER)),
  'kcsapifilter' => [
    '/kcsapi/api_port/port',
    '/kcsapi/api_req_hensei/change',
    '/kcsapi/api_get_member/material',
    '/kcsapi/api_get_member/require_info',
    '/kcsapi/api_get_member/mapinfo',
    '/kcsapi/api_req_map/start',
    '/kcsapi/api_req_sortie/battleresult',
    '/kcsapi/api_get_member/ship_deck',
    '/kcsapi/api_req_map/next',
    '/kcsapi/api_get_member/questlist',
    '/kcsapi/api_get_member/deck',
    '/kcsapi/api_req_kousyou/createitem',
    '/kcsapi/api_req_kousyou/getship',
    '/kcsapi/api_get_member/kdock',
    '/kcsapi/api_req_kousyou/createship',
    '/kcsapi/api_req_kousyou/remodel_slotlist',
    '/kcsapi/api_req_kousyou/remodel_slotlist_detail',
    '/kcsapi/api_req_kousyou/remodel_slot',
    '/kcsapi/api_get_member/ship3'
  ]
];
exit(json_encode($i));

function url_origin($s) {
  $ssl = (!empty($s['HTTPS']) && $s['HTTPS'] == 'on');
  $sp = strtolower($s['SERVER_PROTOCOL']);
  $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');
  $port = $s['SERVER_PORT'];
  $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;
  $host = isset($s['HTTP_HOST']) ? $s['HTTP_HOST'] : null;
  $host = isset($host) ? $host : $s['SERVER_NAME'] . $port;
  return $protocol . '://' . $host;
}

function full_url($s) {
  return url_origin($s) . $s['REQUEST_URI'];
}
