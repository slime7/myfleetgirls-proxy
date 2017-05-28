<?php
/**
 * 直接转发mfg客户端数据给mfg官方服务器。此文件单独使用。
 * 请求路径为: https://example.com/direct.php?path=
 */

$post = parseBody()['data'];
$response = mfgReq($_GET['path'], $post);
exit($response);

function parseBody() {
  $data = null;
  $files = null;
  if (isset($_POST) && !empty($_POST)) {
    $data = $_POST;
  }
  if (isset($_FILES) && !empty($_FILES)) {
    $files = $_FILES;
  }
  //In case of request with json body
  if ($data === null) {
    if (isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], 'application/json') !== false) {
      $input = file_get_contents('php://input');
      $data = json_decode($input, true);
    }
  }
  return [
    'data' => $data,
    'files' => $files
  ];
}

function mfgReq($path, $postData) {
  $url = 'https://myfleet.moe' . $path;
  $postDataQuery = http_build_query($postData);

  $mfgReq = curl_init();
  curl_setopt_array($mfgReq,
    [
      CURLOPT_URL => $url,
      CURLOPT_POST => true,
      //CURLOPT_HEADER => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
      CURLOPT_POSTFIELDS => $postDataQuery,
      CURLOPT_USERAGENT => 'Myfleetgirls-proxy'
    ]);
  if (substr($url, 0, 8) == 'https://') {
    curl_setopt($mfgReq, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($mfgReq, CURLOPT_SSL_VERIFYHOST, 0);
  }
  $response = curl_exec($mfgReq);
  if (is_resource($mfgReq)) {
    curl_close($mfgReq);
  }

  return $response;
}
