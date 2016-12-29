<?php

/*
 * myfleetgirls
 */
define('mfg_id', 12345678);
define('mfg_pass', 'password');
/*
 * kancolle
 */
define('kan_id', 141008751);
define('kan_nickname', 'Slime');

/*
 * proxy auth
 */
function auth($key = null) {
  /**
   * 返回true表示可通过
   * 可自行添加限制比如$key === 'key'
   */
  return true;
}
