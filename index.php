<?php
define('SYSTEM_coding', 'GBK');

if (file_exists('mfg-auth.php')) {
  include 'mfg-auth.php';
} else {
  exit();
}

$mfg = new MFGProxy(
  ['id' => mfg_id, 'pass' => mfg_pass],
  ['id' => kan_id, 'nickname' => kan_nickname, 'memberId' => mfg_id]
);
$mfg->handle();

class MFGProxy
{
  /*
   * mfg身份验证数据
   */
  private $mfgAuth = [];

  /*
   * KanColle身份信息
   */
  private $kanInfo = [];

  /*
   * mfg运营url
   */
  private $mfgURL = 'https://myfleet.moe';

  /*
   * 游戏返回的原始数据
   */
  private $svdata = [];

  /*
   * 游戏请求中的post数据
   */
  private $gamepost = [];

  /**
   * 游戏kcapi路径
   */
  private $apiPath = '';

  private $mfgReqData = [];
  private $mfgReqUrl = '';
  private $response = [];

  public function __construct($mfgAuth, $kanInfo, $url = null) {
    $this->mfgAuth = $mfgAuth;
    $this->kanInfo = $kanInfo;
    if (isset($url)) {
      $this->mfgURL = $url;
    }
  }

  public function handle() {
    $post = $this->parseBody()['data'];
    if (!auth($post['u'])) {
      exit();
    }

    $this->apiPath = $post['path'];
    $this->svdata = json_decode(substr($post['svdata'], 7), true);
    parse_str(str_replace('%5F', '_', $post['gamepost']), $this->gamepost);

    switch ($this->apiPath) {
      case '/kcsapi/api_port/port':
        $this->firstFleetStore();
        $this->parseShips();
        $this->parseMaterial(true);
        $this->parseDeckport(true);
        $this->parseBasic();
        $this->parseNdock();
        $this->clearfile();
        break;

      case '/kcsapi/api_req_hensei/change':
        $this->firstFleetChange();
        break;

      case '/kcsapi/api_get_member/material':
        $this->parseMaterial();
        break;

      case '/kcsapi/api_get_member/require_info':
        $this->parseItem();
        break;

      case '/kcsapi/api_get_member/mapinfo':
        $this->parseMapinfo();
        break;

      case '/kcsapi/api_req_map/start':
        $this->parseMapstart();
        break;

      case '/kcsapi/api_req_sortie/battleresult':
        $res = $this->parseBattleresult();
        if (!$res) {
          $this->response['/kcsapi/api_req_sortie/battleresult'] = 'read file error';
        }
        break;

      case '/kcsapi/api_get_member/ship_deck':
        $this->parseUpdateship();
        break;

      case '/kcsapi/api_req_map/next':
        $this->parseMaproute();
        break;

      case '/kcsapi/api_get_member/questlist':
        $this->parseQuestlist();
        break;

      case '/kcsapi/api_get_member/deck':
        $this->parseDeckport();
        break;

      case '/kcsapi/api_req_kousyou/createitem':
        $this->parseCreateitem();
        break;

      case '/kcsapi/api_req_kousyou/getship':
        $this->parseGetship();
        break;

      case '/kcsapi/api_get_member/kdock':
        $this->parseKdock();
        break;

      case '/kcsapi/api_req_kousyou/createship':
        $res = $this->parseCreateship();
        if (!$res) {
          $this->response['/kcsapi/api_req_kousyou/createship'] = 'create file error.';
        }
        break;

      case '/kcsapi/api_req_kousyou/remodel_slotlist':
        $this->parseRemodelslot();
        break;

      case '/kcsapi/api_req_kousyou/remodel_slotlist_detail':
        $this->parseRemodelslotdetail();
        break;

      case '/kcsapi/api_req_kousyou/remodel_slot':
        $this->parseRemodel();
        break;

      default:
        $this->response['result'] = 'not ready';
        break;
    }

    $this->json();
  }

  private function firstFleetStore() {
    $api_firstFleet = $this->svdata['api_data']['api_deck_port'][0]['api_ship'];
    $this->setData('firstfleet', $this->removeEmpty($api_firstFleet));
  }

  private function firstFleetChange() {
    $api_change = $this->gamepost;
    if ($api_change['api_id'] != 1) {
      return;
    }
    $fleetData = $this->getData('firstfleet');
    $isSwitch = array_search((int)$api_change['api_ship_id'], $fleetData);
    if ($isSwitch) {
      $fleetData[$isSwitch] = $fleetData[$api_change['api_ship_idx']];
    }
    if (-1 == $api_change['api_ship_id']) {
      array_splice($fleetData, (int)$api_change['api_ship_idx'], 1);
    } else {
      $fleetData[$api_change['api_ship_idx']] = (int)$api_change['api_ship_id'];
    }
    $this->setData('firstfleet', $fleetData);
  }

  private function parseItem() {
    $api_items = $this->svdata['api_data']['api_slot_item'];
    foreach ($api_items as $item) {
      $this->mfgReqData[] = [
        'id' => $item['api_id'],
        'slotitemId' => $item['api_slotitem_id'],
        'locked' => !!$item['api_locked'],
        'level' => $item['api_level']
      ];
    }
    $this->mfgReqUrl = '/post/v1/slotitem';
    return $this->mfgReq();
  }

  private function parseShips() {
    $api_ships = $this->svdata['api_data']['api_ship'];
    foreach ($api_ships as $ship) {
      $this->mfgReqData[] = $this->formatShip($ship);
    }
    $this->mfgReqUrl = '/post/v2/ship';
    return $this->mfgReq();
  }

  private function parseMaterial($isPort = false) {
    $api_material = $this->svdata['api_data'];
    if ($isPort) {
      $api_material = $api_material['api_material'];
    }
    $this->mfgReqData = [
      'fuel' => $api_material[0]['api_value'],
      'ammo' => $api_material[1]['api_value'],
      'steel' => $api_material[2]['api_value'],
      'bauxite' => $api_material[3]['api_value'],
      'instant' => $api_material[4]['api_value'],
      'bucket' => $api_material[5]['api_value'],
      'develop' => $api_material[6]['api_value'],
      'revamping' => $api_material[7]['api_value']
    ];
    $this->mfgReqUrl = '/post/v1/material';
    return $this->mfgReq();
  }

  private function parseDeckport($isPort = false) {
    $api_deckport = $this->svdata['api_data'];
    if ($isPort) {
      $api_deckport = $api_deckport['api_deck_port'];
    }
    $fleetTemp = function ($id, $name, $ships, $mission = null) {
      $ships = $this->removeEmpty($ships);
      $f = [
        'id' => $id,
        'name' => $name,
        'ships' => $ships
      ];
      if (isset($mission)) {
        $f['mission'] = $mission;
      }
      return $f;
    };
    $this->mfgReqData = [
      $fleetTemp(
        $api_deckport[0]['api_id'],
        $api_deckport[0]['api_name'],
        $api_deckport[0]['api_ship']
      ),
      $fleetTemp(
        $api_deckport[1]['api_id'],
        $api_deckport[1]['api_name'],
        $api_deckport[1]['api_ship'],
        [
          'page' => $api_deckport[1]['api_mission'][0],
          'number' => $api_deckport[1]['api_mission'][1],
          'completeTime' => $api_deckport[1]['api_mission'][2]
        ]),
      $fleetTemp(
        $api_deckport[2]['api_id'],
        $api_deckport[2]['api_name'],
        $api_deckport[2]['api_ship'],
        [
          'page' => $api_deckport[2]['api_mission'][0],
          'number' => $api_deckport[2]['api_mission'][1],
          'completeTime' => $api_deckport[2]['api_mission'][2]
        ]),
      $fleetTemp(
        $api_deckport[3]['api_id'],
        $api_deckport[3]['api_name'],
        $api_deckport[3]['api_ship'],
        [
          'page' => $api_deckport[3]['api_mission'][0],
          'number' => $api_deckport[3]['api_mission'][1],
          'completeTime' => $api_deckport[3]['api_mission'][2]
        ])
    ];
    $this->mfgReqUrl = '/post/v1/deckport';
    return $this->mfgReq();
  }

  private function parseBasic() {
    $api_basic = $this->svdata['api_data']['api_basic'];
    $this->mfgReqData = [
      'lv' => $api_basic['api_level'],
      'experience' => $api_basic['api_experience'],
      'rank' => $api_basic['api_rank'],
      'maxChara' => $api_basic['api_max_chara'],
      'fCoin' => $api_basic['api_fcoin'],
      'stWin' => $api_basic['api_st_win'],
      'stLose' => $api_basic['api_st_lose'],
      'msCount' => $api_basic['api_ms_count'],
      'msSuccess' => $api_basic['api_ms_success'],
      'ptWin' => $api_basic['api_pt_win'],
      'ptLose' => $api_basic['api_pt_lose'],
      'medals' => $api_basic['api_medals'],
      'comment' => $api_basic['api_comment'],
      'deckCount' => $api_basic['api_count_deck'],
      'kdockCount' => $api_basic['api_count_kdock'],
      'ndockCount' => $api_basic['api_count_ndock'],
      'largeDock' => !!$api_basic['api_large_dock']
    ];
    $this->mfgReqUrl = '/post/v1/basic';
    return $this->mfgReq();
  }

  private function parseNdock() {
    $api_ndock = $this->svdata['api_data']['api_ndock'];
    foreach ($api_ndock as $ndock) {
      $this->mfgReqData[] = [
        'id' => $ndock['api_id'],
        'shipId' => $ndock['api_ship_id'],
        'completeTime' => $ndock['api_complete_time']
      ];
    }
    $this->mfgReqUrl = '/post/v1/ndock';
    return $this->mfgReq();
  }

  private function parseMapinfo() {
    $api_mapinfo = $this->svdata['api_data']['api_map_info'];
    foreach ($api_mapinfo as $map) {
      $this->mfgReqData[] = [
        'id' => $map['api_id'],
        'cleared' => !!$map['api_cleared'],
        'exbossFlag' => !!$map['api_exboss_flag']
      ];
    }
    $this->mfgReqUrl = '/post/v1/mapinfo';
    return $this->mfgReq();
  }

  private function parseMapstart() {
    $api_mapstart = $this->svdata['api_data'];
    $this->mfgReqData = [
      'rashinFlag' => !!$api_mapstart['api_rashin_flg'],
      'rashinId' => $api_mapstart['api_rashin_id'],
      'mapAreaId' => $api_mapstart['api_maparea_id'],
      'mapInfoNo' => $api_mapstart['api_mapinfo_no'],
      'no' => $api_mapstart['api_no'],
      'eventId' => $api_mapstart['api_event_id'],
      'next' => $api_mapstart['api_next'],
      'bossCellNo' => $api_mapstart['api_bosscell_no'],
      'bossComp' => !!$api_mapstart['api_bosscomp']
    ];
    $this->setData('route', $this->mfgReqData);
    $this->mfgReqUrl = '/post/v1/map_start';
    return $this->mfgReq();
  }

  private function parseBattleresult() {
    $api_battleresult = $this->svdata['api_data'];
    $lastmap = $this->getData('route');
    if (!$lastmap) {
      return false;
    }
    $getShip = [];
    if (isset($api_battleresult['api_get_ship'])) {
      $getShip = [
        'id' => $api_battleresult['api_get_ship']['api_ship_id'],
        'stype' => $api_battleresult['api_get_ship']['api_ship_type'],
        'name' => $api_battleresult['api_get_ship']['api_ship_name']
      ];
    }
    $this->mfgReqData = [
      '_1' => [
        'enemies' => array_slice($api_battleresult['api_ship_id'], 1),
        'winRank' => $api_battleresult['api_win_rank'],
        'exp' => $api_battleresult['api_get_exp'],
        'mvp' => $api_battleresult['api_mvp'],
        'baseExp' => $api_battleresult['api_get_base_exp'],
        'shipExp' => array_slice($api_battleresult['api_get_ship_exp'], 1),
        'lostFlag' => array_slice($api_battleresult['api_lost_flag'], 1),
        'questName' => $api_battleresult['api_quest_name'],
        'questLevel' => $api_battleresult['api_quest_level'],
        'enemyDeck' => $api_battleresult['api_enemy_info']['api_deck_name'],
        'firstClear' => !!$api_battleresult['api_first_clear'],
        'getShip' => $getShip
      ],
      '_2' => $lastmap
    ];
    foreach ($this->mfgReqData['_1']['lostFlag'] as &$lostFlag) {
      $lostFlag = !!$lostFlag;
    }
    $this->mfgReqUrl = '/post/v1/battle_result';
    return $this->mfgReq();
  }

  private function parseUpdateship() {
    $api_shipdeck = $this->svdata['api_data']['api_ship_data'];
    $fleet = $this->removeEmpty($this->svdata['api_data']['api_deck_data'][0]['api_ship']);
    foreach ($api_shipdeck as $ship) {
      $this->mfgReqData[] = $this->formatShip($ship);
    }
    $this->setData('fleet', $fleet);
    $this->mfgReqUrl = '/post/v1/update_ship';
    return $this->mfgReq();
  }

  private function parseMaproute() {
    $api_route = $this->svdata['api_data'];
    $lastmap = $this->getData('route');
    if (!$lastmap) {
      return false;
    }
    $lastfleet = $this->getData('fleet');
    if (!$lastfleet) {
      return false;
    }
    if ($lastmap['mapAreaId'] !== $api_route['api_maparea_id'] || $lastmap['mapInfoNo'] !== $api_route['api_mapinfo_no']) {
      return false;
    }
    $nextmap = array_merge($lastmap, ['no' => $api_route['api_no'], 'next' => $api_route['api_next']]);
    $this->setData('route', $nextmap);
    $this->mfgReqData = [
      'areaId' => $api_route['api_maparea_id'],
      'infoNo' => $api_route['api_mapinfo_no'],
      'dep' => $lastmap['no'],
      'dest' => $api_route['api_no'],
      'fleet' => $lastfleet
    ];
    $this->mfgReqUrl = '/post/v1/map_route';
    return $this->mfgReq();
  }

  private function parseQuestlist() {
    $api_quests = $this->svdata['api_data']['api_list'];
    $questMaterial = function ($mat) {
      return [
        'fuel' => $mat[0],
        'ammo' => $mat[1],
        'steel' => $mat[2],
        'bauxite' => $mat[3]
      ];
    };
    foreach ($api_quests as $quest) {
      if (!$quest['api_no']) {
        continue;
      }
      $this->mfgReqData[] = [
        'no' => $quest['api_no'],
        'category' => $quest['api_category'],
        'typ' => $quest['api_type'],
        'state' => $quest['api_state'],
        'title' => $quest['api_title'],
        'detail' => $quest['api_detail'],
        'material' => $questMaterial($quest['api_get_material']),
        'bonus' => !!$quest['api_bonus_flag'],
        'progressFlag' => $quest['api_progress_flag']
      ];
    }
    $this->mfgReqUrl = '/post/v1/questlist';
    return $this->mfgReq();
  }

  private function parseCreateitem() {
    $api_createitem = $this->svdata['api_data'];
    $firstFleet = $this->getData('firstfleet');
    $this->mfgReqData = [
      'fuel' => (int)$this->gamepost['api_item1'],
      'ammo' => (int)$this->gamepost['api_item2'],
      'steel' => (int)$this->gamepost['api_item3'],
      'bauxite' => (int)$this->gamepost['api_item4'],
      'createFlag' => !!$api_createitem['api_create_flag'],
      'shizaiFlag' => !!$api_createitem['api_shizai_flag'],
      'flagship' => $firstFleet[0]
    ];
    if (!!$api_createitem['api_create_flag']) {
      $this->mfgReqData['id'] = $api_createitem['api_slot_item']['api_id'];
      $this->mfgReqData['slotitemId'] = $api_createitem['api_slot_item']['api_slotitem_id'];
    }
    $this->mfgReqUrl = '/post/v1/createitem';
    return $this->mfgReq();
  }

  private function parseGetship() {
    $api_getship = $this->svdata['api_data'];
    $this->mfgReqData = [
      'kDockId' => (int)$this->gamepost['api_kdock_id'],
      'shipId' => (int)$api_getship['api_ship_id']
    ];
    $this->mfgReqUrl = '/post/v1/delete_kdock';
    return $this->mfgReq();
  }

  private function parseKdock() {
    $kdockReq = $_kdock = [];
    $api_kdock = $this->svdata['api_data'];
    foreach ($api_kdock as $kdock) {
      $_kdock[] = [
        'id' => $kdock['api_id'],
        'shipId' => $kdock['api_created_ship_id'],
        'state' => $kdock['api_state'],
        'completeTime' => $kdock['api_complete_time'],
        'fuel' => $kdock['api_item1'],
        'ammo' => $kdock['api_item2'],
        'steel' => $kdock['api_item3'],
        'bauxite' => $kdock['api_item4']
      ];
      if ($kdock['api_state'] === 2) {
        $kdockReq[] = $_kdock[count($_kdock) - 1];
      }
    }

    if ($createshipData = $this->getData('createship')) {
      $this->mfgReqData = $createshipData;
      if ($this->mfgReqData['createShip']['highspeed']) {
        $this->mfgReqData['resultShip'] = $_kdock[$this->mfgReqData['createShip']['kDock'] - 1]['shipId'];
        $this->mfgReqUrl = '/post/v2/createship';
      } else {
        $this->mfgReqData['kDock'] = $_kdock[$this->mfgReqData['createShip']['kDock'] - 1];
        $this->mfgReqUrl = '/post/v1/createship';
      }
      $this->mfgReq();
      $this->clearfile();
    }

    $this->setData('kdock', $_kdock);
    $this->mfgReqData = $kdockReq;
    $this->mfgReqUrl = '/post/v1/kdock';
    return $this->mfgReq();
  }

  private function parseCreateship() {
    $firstFleet = $this->getData('firstfleet');
    if (!$firstFleet) {
      return false;
    }
    $createData = [
      'createShip' => [
        'fuel' => (int)$this->gamepost['api_item1'],
        'ammo' => (int)$this->gamepost['api_item2'],
        'steel' => (int)$this->gamepost['api_item3'],
        'bauxite' => (int)$this->gamepost['api_item4'],
        'develop' => (int)$this->gamepost['api_item5'],
        'kDock' => (int)$this->gamepost['api_kdock_id'],
        'highspeed' => !!(int)$this->gamepost['api_highspeed'],
        'largeFlag' => !!(int)$this->gamepost['api_large_flag'],
        'firstShip' => $firstFleet[0]
      ]
    ];
    return $this->setData('createship', $createData);
  }

  private function parseRemodelslot() {
    $firstFleet = $this->getData('firstfleet');
    $api_remodelslotlist = $this->svdata['api_data'];
    $this->mfgReqData = [
      'second' => $firstFleet[1],
      'list' => []
    ];
    foreach ($api_remodelslotlist as $remodelslot) {
      $this->mfgReqData['list'][] = [
        'id' => $remodelslot['api_id'],
        'slotId' => $remodelslot['api_slot_id'],
        'fuel' => $remodelslot['api_req_fuel'],
        'ammo' => $remodelslot['api_req_bull'],
        'steel' => $remodelslot['api_req_steel'],
        'bauxite' => $remodelslot['api_req_bauxite'],
        'develop' => $remodelslot['api_req_buildkit'],
        'revamping' => $remodelslot['api_req_remodelkit'],
        'reqSlotId' => $remodelslot['api_req_slot_id'],
        'slotNum' => $remodelslot['api_req_slot_num']
      ];
    }
    $this->mfgReqUrl = '/post/v1/remodel_slot';
    return $this->mfgReq();
  }

  private function parseRemodelslotdetail() {
    $firstFleet = $this->getData('firstfleet');
    $api_remodelslotdetail = $this->svdata['api_data'];
    $this->mfgReqData = [
      'develop' => $api_remodelslotdetail['api_req_buildkit'],
      'remodel' => $api_remodelslotdetail['api_req_remodelkit'],
      'certainDevelop' => $api_remodelslotdetail['api_certain_buildkit'],
      'certainRemodel' => $api_remodelslotdetail['api_certain_remodelkit'],
      'slotitemId' => $api_remodelslotdetail['api_req_slot_id'],
      'slotitemNum' => $api_remodelslotdetail['api_req_slot_num'],
      'changeFlag' => !!$api_remodelslotdetail['api_change_flag'],
      'origSlotId' => (int)$this->gamepost['api_slot_id'],
      'secondShipId' => $firstFleet[1]
    ];
    $this->mfgReqUrl = '/post/v1/master_remodel';
    return $this->mfgReq();
  }

  private function parseRemodel() {
    $api_remodel = $this->svdata['api_data'];
    $this->mfgReqData = [
      'flag' => !!$api_remodel['api_remodel_flag'],
      'beforeItemId' => $api_remodel['api_remodel_id'][0],
      'afterItemId' => $api_remodel['api_remodel_id'][1],
      'voiceId' => $api_remodel['api_voice_id'],
      'afterSlot' => [
        'id' => $api_remodel['api_after_slot']['api_id'],
        'slotitemId' => $api_remodel['api_after_slot']['api_slotitem_id'],
        'locked' => !!$api_remodel['api_after_slot']['api_locked'],
        'level' => $api_remodel['api_after_slot']['api_level']
      ],
      'useSlotIds' => $api_remodel['api_use_slot_id'],
      'certain' => !!(int)$this->gamepost['api_certain_flag'],
      'slotId' => (int)$this->gamepost['api_slot_id']
    ];
    $this->mfgReqUrl = '/post/v1/remodel';
    return $this->mfgReq();
  }

  private function mfgReq($reqUrl = null) {
    $url = $this->mfgURL . (isset($reqUrl) ? $reqUrl : $this->mfgReqUrl);
    $postData = [
      'auth' => (json_encode($this->kanInfo)),
      'auth2' => (json_encode($this->mfgAuth)),
      'data' => (json_encode($this->mfgReqData))
    ];
    $postDataQuery = http_build_query($postData);
    $this->mfgReqData = [];

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
    $this->response[$this->mfgReqUrl] = $response;
    if (is_resource($mfgReq)) {
      curl_close($mfgReq);
    }

    return true;
  }

  private function formatShip($shipApi) {
    $ship = [
      'id' => $shipApi['api_id'],
      'shipId' => $shipApi['api_ship_id'],
      'lv' => $shipApi['api_lv'],
      'exp' => $shipApi['api_exp'][0],
      'nowhp' => $shipApi['api_nowhp'],
      'maxhp' => $shipApi['api_maxhp'],
      'slot' => $shipApi['api_slot'],
      'kyouka' => $shipApi['api_kyouka'],
      'back' => $shipApi['api_backs'],
      'fuel' => $shipApi['api_fuel'],
      'bull' => $shipApi['api_bull'],
      'dockTime' => $shipApi['api_ndock_time'],
      'cond' => $shipApi['api_cond'],
      'karyoku' => $shipApi['api_karyoku'][0],
      'raisou' => $shipApi['api_raisou'][0],
      'taiku' => $shipApi['api_taiku'][0],
      'soukou' => $shipApi['api_soukou'][0],
      'kaihi' => $shipApi['api_kaihi'][0],
      'taisen' => $shipApi['api_taisen'][0],
      'sakuteki' => $shipApi['api_sakuteki'][0],
      'lucky' => $shipApi['api_lucky'][0],
      'locked' => !!$shipApi['api_locked']
    ];

    return $ship;
  }

  private function removeEmpty($array) {
    return array_filter($array,
      function ($v) {
        return $v !== -1;
      });
  }

  private function clearfile() {
    $savadataprefix = __DIR__ . '/savedata/' . $this->mfgAuth['id'] . '_' . $this->kanInfo['id'];
    $route = $savadataprefix . '_route';
    $fleet = $savadataprefix . '_fleet';
    $createship = $savadataprefix . '_createship';
    is_file($route) && unlink($route);
    is_file($fleet) && unlink($fleet);
    is_file($createship) && unlink($createship);
  }

  private function json() {
    header('Content-type:text/json');
    exit(json_encode($this->response, JSON_UNESCAPED_UNICODE));
  }

  private function parseBody() {
    $data = NULL;
    $files = NULL;

    if (isset($_POST) AND !empty($_POST)) {
      $data = $_POST;
    }

    if (isset($_FILES) AND !empty($_FILES)) {
      $files = $_FILES;
    }

    if ($data === NULL) {
      if (isset($_SERVER['CONTENT_TYPE']) AND strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = file_get_contents('php://input');

        $data = json_decode($input, true);
      } else {
        $stream = [];
        new stream($stream);

        $data = $stream['post'];
        $files = $stream['file'];
      }
    }

    return [
      'data' => $data,
      'files' => $files
    ];
  }

  private function setData($key, $data) {
    if (!is_dir(__DIR__ . '/savedata/')) {
      mkdir(__DIR__ . '/savedata/');
    }
    $path = __DIR__ . '/savedata/' . $this->mfgAuth['id'] . '_' . $this->kanInfo['id'] . '_' . $key;
    $_path = iconv('UTF-8', SYSTEM_coding, $path);
    return file_put_contents($_path, json_encode($data));
  }

  private function getData($key) {
    $path = __DIR__ . '/savedata/' . $this->mfgAuth['id'] . '_' . $this->kanInfo['id'] . '_' . $key;
    $_path = iconv('UTF-8', SYSTEM_coding, $path);
    if (!file_exists($_path)) {
      return false;
    }
    return json_decode(file_get_contents($_path), true);
  }
}
