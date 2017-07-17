<?php
define('SYSTEM_coding', 'GBK');
include 'MFGProxy.php';

/**
 * 引入设置文件
 */
if (file_exists('mfg-auth.php')) {
  include 'mfg-auth.php';
} else {
  exit();
}

//储存提交的数据到全局
$post = parseBody()['data'];

//load plugins
$plugins = glob(realpath(__DIR__) . '/plugins/mfgp-*.php');
foreach($plugins as $plugin) {
  try{
    include $plugin;
  } catch (Exception $e) {
    return null;
  }
}

$mfg = new MFGProxy(
  ['id' => mfg_id, 'pass' => mfg_pass],
  ['id' => kan_id, 'nickname' => kan_nickname, 'memberId' => mfg_id]
);
$mfg->handle();

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
