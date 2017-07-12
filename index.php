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
