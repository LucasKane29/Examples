<?php

class TGoodsAPI
{
  private $db, $settings, $auth;
  private $dom;
  private $protocol = "";
  private $site = "";

  public $response = array();

  public function TGoodsAPI($db, $settings, $auth, $protocol = "http://")
  {
    $this->db = $db;
    $this->settings = $settings;
    $this->auth = $auth;
    $this->protocol = $protocol;
    $this->site = $this->protocol.$_SERVER['SERVER_NAME'];

    $this->response['shop']['name'] = $this->settings->global['site_name_en'];
    $this->response['shop']['company'] = $this->settings->global['site_name_en'];
    $this->response['shop']['url'] = $this->site;
  }

  private function AddToResponse($params = array())
  {
    if(count($params) > 0)
    {
      foreach ($params as $param_name => $param_value)
      {
        $this->response['shop'][$param_name] = $param_value;
      }
    }
  }
  private function GetResponse()
  {
    $response = json_encode($this->response);
    return $response;
  }

  private function CheckAutorization($post, $session)
  {
    $check = false;

    if((isset($post['token']) && isset($session['token'])) || isset($post['key']))
    {
      if($post['key'] == $this->settings->global['super_key']){
        return array('check' => true, 'admin' => true, 'user' => 'admin');
      }
      if(isset($post['token']) && $post['token'] == $session['token'] && !isset($post['key']))
      {
        $check = true;
        $user = $session;
      }
      elseif(isset($post['token']) && $post['token'] != $session['token'] && !isset($post['key']))
      {
        $this->AddToResponse(array("response" => array("code" => 3, "message" => "Предоставленый уникальный ID не совпадает.")));
      }
      if(isset($post['key']) && !isset($post['token']))
      {
        $user = $this->auth->LoginByKey($post['key']);
        if(!empty($user['auth']))
        {
          $check = true;
        }
        else
        {
          $this->AddToResponse(array("response" => array("code" => 3, "message" => "Указан неверный уникальный ключ.")));
        }
      }
    }
    elseif(!isset($post['token']) && !isset($session['token']) && isset($post['key']))
    {
      $this->AddToResponse(array("response" => array("code" => 2, "message" => "Время Вашей сессии истекло либо не указан уникальный ключ")));
    }
    if(!$check)
    {
      return array('check' => $check);
    }
    else
    {
      return array('check' => $check, 'user' => $user);
    }
  }

  private function GetGoodsList($time, $type = "xml", $user_id, $check_user, $page = false)
  {
    $max_count = MAX_API_GOODS_COUNT;
    if($page !== false)
    {
      $limit = " LIMIT ".(((int)$page - 1) * $max_count).", ".$max_count;
    }
    if($page === false || $page == 0)
    {
      $limit = " LIMIT ".$max_count;
    }
    //$limit = "";
    if(isset($check_user['admin']))
    {
      $time = 0;
      $query = "SELECT `goods`.`id`, `goods`.`articul`, `goods`.`model`, `goods`.`updatetime`
                FROM `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> ''
                ORDER BY `goods`.`updatetime` DESC".$limit;
      $count = $this->db->QueryLine("SELECT COUNT(*) as `count`
                FROM `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> ''");
    }else
    {
      if($time !== false)
      {
        $query = "SELECT `goods`.`id`, `goods`.`articul`, `goods`.`model`, `goods`.`updatetime`
                FROM `TUsersSubscribes`, `TUsersSubscribeEntries`, `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> '' AND `goods`.`updatetime` > '".$time."' AND `TUsersSubscribes`.`user` = ".$user_id." AND `TUsersSubscribeEntries`.`status` = '1'
                AND `TUsersSubscribeEntries`.`subscribe` = `TUsersSubscribes`.`id`
                AND `TUsersSubscribeEntries`.`brand` = `goods`.`brand` AND `TUsersSubscribeEntries`.`rubric` = `goods`.`type1`
                ORDER BY `goods`.`updatetime` DESC".$limit;
        $count = $this->db->QueryLine("SELECT COUNT(*) as `count`
                FROM `TUsersSubscribes`, `TUsersSubscribeEntries`, `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> '' AND `goods`.`updatetime` > '".$time."' AND `TUsersSubscribes`.`user` = ".$user_id." AND `TUsersSubscribeEntries`.`status` = '1'
                AND `TUsersSubscribeEntries`.`subscribe` = `TUsersSubscribes`.`id`
                AND `TUsersSubscribeEntries`.`brand` = `goods`.`brand` AND `TUsersSubscribeEntries`.`rubric` = `goods`.`type1`
                ORDER BY `goods`.`updatetime` DESC");
      }
      else
      {
        $query = "SELECT `goods`.`id`, `goods`.`articul`, `goods`.`model`, `goods`.`updatetime`
                FROM `TUsersSubscribes`, `TUsersSubscribeEntries`, `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> '' AND `TUsersSubscribes`.`user` = ".$user_id." AND `TUsersSubscribeEntries`.`status` = '1'
                AND `TUsersSubscribeEntries`.`subscribe` = `TUsersSubscribes`.`id`
                AND `TUsersSubscribeEntries`.`brand` = `goods`.`brand` AND `TUsersSubscribeEntries`.`rubric` = `goods`.`type1`
                ORDER BY `goods`.`updatetime` DESC".$limit;
        $count = $this->db->QueryLine("SELECT COUNT(*) as `count`
                FROM `TUsersSubscribes`, `TUsersSubscribeEntries`, `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> '' AND `TUsersSubscribes`.`user` = ".$user_id." AND `TUsersSubscribeEntries`.`status` = '1'
                AND `TUsersSubscribeEntries`.`subscribe` = `TUsersSubscribes`.`id`
                AND `TUsersSubscribeEntries`.`brand` = `goods`.`brand` AND `TUsersSubscribeEntries`.`rubric` = `goods`.`type1`
                ORDER BY `goods`.`updatetime` DESC");
      }
    }
    $result = $this->db->Query($query);
    if(isset($check_user['admin']))
    {
      $check_logo_update['id'] = 0;
      $check_logo_update['added_time'] = 0;
    }else
    {
      $check_logo_update = $this->db->QueryLine('SELECT * FROM `TLogoModeration` WHERE `user` = '.$user_id.' AND `moderation_stage` = 1');
    }
    /*
     * Расчет времени последнего обновления товаров, чтобы вычислить количество товаров, необходимое обновить клиенту
     */
    $new_user = 0;
    $goods_statictic['updated'] = 0;
    $goods_statictic['to_update'] = 0;
    $goods_statictic['all'] = $count['count'];
    if(empty($check_user['user']['last_update']) || $check_user['user']['last_update'] == 0)
    {
      $new_user = 1;
    }
    else
    {
      $user_last_update = $check_user['user']['last_update'];
    }
    if(!empty($user_id))
    {
      @mkdir("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$user_id);
      switch($type)
      {
        case "xml" :
        {
          $file_path = "/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$user_id."/goods.xml";
          $fp = fopen($file_path, "w");
          $header = "";
          $header .= '<?xml version="1.0" encoding="utf-8"?>'."\r\n";
          $header .= '<yml_catalog date="'.date('Y-m-d H:m').'">'."\r\n";
          $header .= "\t".'<shop>'."\r\n";
          $header .= "\t\t".'<name>Integraloptica</name>'."\r\n";
          $header .= "\t\t".'<company>Integraloptica</company>'."\r\n";
          $header .= "\t\t".'<url>'.htmlentities($this->site).'</url>'."\r\n";
          $header .= "\t\t".'<goods>'."\r\n";
          fwrite($fp, $header);
          while($good = $this->db->Fetch($result))
          {
            /*
             * Расчет новых товаров для обновления
             */
            if(isset($check_logo_update['id']))
            {
              if($good['updatetime'] < $check_logo_update['added_time'])
              {
                $good['updatetime'] = $check_logo_update['added_time'];
              }
            }
            if((!$new_user && isset($user_last_update)) && $user_last_update < $good['updatetime'])
            {
              $goods_statictic['to_update']++;
            }
            $good_string = "";
            $good_string .= "\t\t\t".'<good>'."\r\n";
            $good_string .= "\t\t\t\t".'<id>'.$good['id'].'</id>'."\r\n";
            $good_string .= "\t\t\t\t".'<modify>'.$good['updatetime'].'</modify>'."\r\n";
            $good_string .= "\t\t\t\t".'<articul>'.htmlentities($good['articul']).'</articul>'."\r\n";
            $good_string .= "\t\t\t\t".'<model>'.htmlentities($good['model']).'</model>'."\r\n";
            $good_string .= "\t\t\t".'</good>'."\r\n";
            fwrite($fp, $good_string);
          }
          $footer = "";
          $footer .= "\t\t".'</goods>'."\r\n";
          $footer .= "\t".'</shop>'."\r\n";
          $footer .= '</yml_catalog>';
          fwrite($fp, $footer);
          fclose($fp);
        }break;
        case "json" :
        {
          $file_path = "/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$user_id."/goods.json";
          $goods_to_file = array();
          $i = 0;
          while($good = $this->db->Fetch($result))
          {
            $goods_to_file['goods'][$i]['id'] =  $good['id'];
            $goods_to_file['goods'][$i]['articul'] =  cleanArticul($good['articul']);
            $goods_to_file['goods'][$i]['model'] =  cleanArticul($good['model']);
            $goods_to_file['goods'][$i]['modify'] =  $good['updatetime'];
            $i++;
          }
          file_put_contents($file_path, json_encode($goods_to_file));
        }break;
        case "csv":
        {
          $file_path = "/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$user_id."/goods.csv";
          $fp = fopen($file_path, "w");
          fwrite($fp, '"ID";"ARTICUL";"MODIFY"'."\r\n");
          while($good = $this->db->Fetch($result))
          {
            fwrite($fp, '"'.$good['id'].'";"'.cleanArticul($good['articul']).'";"'.$good['updatetime'].'"'."\r\n");
          }
          fclose($fp);
        }break;
        default: $file_path = false;
      }
      if($new_user)
      {
        $goods_statictic['to_update'] = $goods_statictic['all'];
      }
      if(isset($check_user['admin']) && !$check_user['admin']){
        $insert_array = array();
        $insert_array['user'] = $check_user['user']['auth'];
        $insert_array['time'] = time();
        $insert_array['all_goods'] = $goods_statictic['all'];
        $insert_array['update_goods'] = $goods_statictic['to_update'];
        $this->db->Insert("TExportStatistic", $insert_array);
        unset($insert_array);
      }

      return $file_path;
    }
    return false;
  }

  private function GetGoodPropertyValue($property, $good)
  {
    $properties = array();

    $good_properties = $this->db->QueryArray($sql = " SELECT `TGoodsproperties`.*, `TGoodspropertiesvalue`.`value_ru` as `value`, `TGoodspropertiesvalues`.`value_ru` as `list_value`
                                                                    FROM `TGoodspropertiesgroups`
                                                                    LEFT JOIN `TGoodsproperties` ON `TGoodsproperties`.`group` = `TGoodspropertiesgroups`.`id`
                                                                    LEFT JOIN `TGoodspropertiesvalue` ON `TGoodspropertiesvalue`.`property` = `TGoodsproperties`.`id` AND `TGoodspropertiesvalue`.`parent` = ".$good['id']."
                                                                    LEFT JOIN `TGoodspropertiesvalues` ON `TGoodspropertiesvalues`.`id` = `TGoodspropertiesvalue`.`value_ru`
                                                                    WHERE `TGoodspropertiesgroups`.`type1` = ".$good['type1']." AND `TGoodspropertiesgroups`.`type2` = ".$good['type2']." AND `TGoodspropertiesgroups`.`type3` =".$good['type3']." AND `TGoodspropertiesgroups`.`name_en` = '".$property."'");
    if(count($good_properties) > 0)
    {
      foreach($good_properties as $property)
      {
        if($property['type'] == "мультисписок")
        {
          $values = array();
          if(!empty($property['value']) && $property['value'] != "NULL")
          {

            $values_id = " `id` IN (".$property['value'].")";
            if(strtolower($property['name_en']) == "in complect")
            {
              $values_id = $this->db->QueryArray($sql = "SELECT * FROM `TGoodsInComplect` WHERE ".$values_id);
            }
            else
            {
              $values_id = $this->db->QueryArray($sql = "SELECT * FROM `TGoodspropertiesvalues` WHERE ".$values_id." ORDER BY `value_ru`");
            }
            if(!empty($values_id))
            {
              foreach($values_id as $value)
              {
                $values[] = trim($value['value_ru']);
              }
              $value = implode(", ", $values);
            }
            else
            {
              $value = "";
            }
          }
          else
          {
            $value = "";
          }

        }
        elseif($property['type'] == "список")
        {

          if(!empty($property['list_value']) && $property['list_value'] != "NULL")
          {
            $value = $property['list_value'];
          }
          else
          {
            $value = "";
          }
        }
        else
        {
          if(!empty($property['value']) && $property['value'] != "NULL")
          {
            $value = $property['value'];
          }
          else
          {
            $value = "";
          }
        }
        $properties[str_replace(" ", "_", strtolower(trim($property['name_en'])))] = $value;
      }
    }
    else
    {
      $properties = "";
    }
    return $properties;
  }

  public function CheckImport($import_on = false)
  {
    if($this->settings->global['export_for_clients'] == "on" || $import_on === true)
    {
      $this->AddToResponse(array("response" => array("code" => 8, "message" => "Импорт включен.")));
    }
    else
    {
      $this->AddToResponse(array("response" => array("code" => 7, "message" => "В данный момент импорт закрыт. Приносим свои извинения и просим связаться с техподдержкой ".$this->settings->global['site_name_en'].".")));
    }
    return $this->GetResponse();
  }

  public function ClosedImport()
  {
    $this->AddToResponse(array("response" => array("code" => 7, "message" =>"В данный момент импорт закрыт. Приносим свои извинения и просим связаться с техподдержкой ".$this->settings->global['site_name_en'].".")));
    return $this->GetResponse();
  }

  public function Authorization($key = false, $login = false, $password = false)
  {
    if($key === false)
    {
      if($login !== false && $password !== false)
      {
        $this->auth->Login($login, $password);
        if($this->auth->logged)
        {
          $_SESSION['token'] = md5($login.time());
          $_SESSION['auth'] = $this->auth->user['id'];
          $user_statistic = $this->db->QueryLine("SELECT `time` FROM `TExportStatistic` WHERE `user` = ".$this->auth->user['id']." ORDER BY `time` DESC");
          $_SESSION['last_update'] = $user_statistic['time'];
          $this->AddToResponse(array("response" => array("code" => 1, "message" => "Вы успешно авторизировалась на сайте.", "token" => $_SESSION['token'])));
        }
        else
        {
          $this->AddToResponse(array("response" => array("code" => 0, "message" => "Логин или пароль указаны не верно. Не удалось авторизоваться на сайте.")));
        }
      }
      else
      {
        $this->AddToResponse(array("response" => array("code" => 0, "message" => "Логин или пароль указаны не верно. Не удалось авторизоваться на сайте.")));
      }
    }
    else
    {
      if($this->auth->CheckKey($key) || $key == $this->settings->global['super_key'])
      {
        $this->AddToResponse(array("response" => array("code" => 1, "message" => "Ваш ключ прошел успешную проверку. Используйте его для выполнения операций с API.")));
      }
      else
      {
        $this->AddToResponse(array("response" => array("code" => 0, "message" => "Данного ключа не существует.")));
      }

    }
    return $this->GetResponse();
  }

  public function GetUserInfo($post, $session)
  {
    $check_user = $this->CheckAutorization($post, $session);
    $user_info = array();
    if($check_user['check'] && isset($check_user['user']))
    {
      $subscribes = $this->db->QueryArray($sql = "SELECT `TUsersSubscribeEntries`.*, `TGoodsbrands`.`name` as `brand_name`, `TGoodstype1s`.`name_ru` as `rubric_name` FROM `TUsersSubscribes`
                                                  LEFT JOIN `TUsersSubscribeEntries` ON `TUsersSubscribes`.`id` = `TUsersSubscribeEntries`.`subscribe`
                                                  LEFT JOIN `TGoodsbrands` ON `TGoodsbrands`.`id` = `TUsersSubscribeEntries`.`brand`
                                                  LEFT JOIN `TGoodstype1s` ON `TGoodstype1s`.`id` = `TUsersSubscribeEntries`.`rubric`
                                                  WHERE `TUsersSubscribes`.`user` = ".$check_user['user']['auth']." AND `TUsersSubscribes`.`status` = '1' AND `TUsersSubscribeEntries`.`id` IS NOT NULL");
      $user = $this->db->QueryLine("SELECT `TLogin`.`login`, `TLogoModeration`.`added_time` 
                                    FROM `TLogin`
                                    LEFT JOIN `TLogoModeration` ON `TLogoModeration`.`user` = `TLogin`.`id` AND `TLogoModeration`.`moderation_stage` = 1
                                    WHERE `TLogin`.`id` = ".$check_user['user']['auth']);
      $user_subscribes = array();
      if(count($subscribes) != 0)
      {
        foreach($subscribes as $subscribe)
        {
          $user_subscribes[$subscribe['rubric']]['rubric_id'] = $subscribe['rubric'];
          $user_subscribes[$subscribe['rubric']]['rubric_name'] = $subscribe['rubric_name'];
          $user_subscribes[$subscribe['rubric']]['brands'][$subscribe['brand']]['id'] = $subscribe['brand'];
          $user_subscribes[$subscribe['rubric']]['brands'][$subscribe['brand']]['name'] = $subscribe['brand_name'];
          $user_subscribes[$subscribe['rubric']]['brands'][$subscribe['brand']]['unix_end_time'] = $subscribe['end_time'];
          $user_subscribes[$subscribe['rubric']]['brands'][$subscribe['brand']]['end_time'] = date('d.m.Y H:i:s', $subscribe['end_time']);
        }
      }
      if(isset($user['login']))
      {
        $user_info['login'] = $user['login'];
        $user_info['logo_saved_time'] = ($user['added_time'] == NULL ? 0 : $user['added_time']);
      }
      $user_info['subscribes'] = $user_subscribes;
      $this->AddToResponse(array("response" => array("code" => 1, "message" => "Информация успешно получена.")));
      $this->AddToResponse(array("user" => $user_info));
    }
    return $this->GetResponse();
  }

  public function GetCategories($post, $session)
  {
    $check_user = $this->CheckAutorization($post, $session);
    if($check_user['check'] && isset($check_user['user']))
    {
      $subscribes = $this->db->QueryArray($sql = "SELECT `TUsersSubscribeEntries`.*, `TGoodstype1s`.*
                                              FROM `TUsersSubscribes`
                                              LEFT JOIN `TUsersSubscribeEntries` ON `TUsersSubscribes`.`id` = `TUsersSubscribeEntries`.`subscribe`
                                              LEFT JOIN `TGoodstype1s` ON `TGoodstype1s`.`id` = `TUsersSubscribeEntries`.`rubric`
                                              WHERE `TUsersSubscribes`.`user` = ".$check_user['user']['auth']." AND `TUsersSubscribes`.`status` = '1' AND `TUsersSubscribeEntries`.`status` = '1'
                                              GROUP BY `TUsersSubscribeEntries`.`rubric`");
      if(count($subscribes) != 0)
      {
        $i = 0;
        $categories = array();
        foreach($subscribes as $subscribe)
        {
          $categories[$i]['id'] = $subscribe['rubric'];
          $categories[$i]['name'] = $subscribe['name_ru'];
          $i++;
        }
        $this->AddToResponse(array("response" => array("code" => 1, "message" => "Категории успешно получены.")));
        $this->AddToResponse(array("categories" => $categories));
      }
      else
      {
        $this->AddToResponse(array("response" => array("code" => 4, "message" => "У Вас нет активных подписок.")));
      }
    }
    return $this->GetResponse();
  }

  public function GetAllBrands()
  {
    $brands = $this->db->QueryArray("SELECT `id`, `name`, `image` FROM `TGoodsbrands`");
    if(count($brands) != 0)
    {
      $this->AddToResponse(array("response" => array("code" => 1, "message" => "Бренды успешно получены.")));
      $i = 0;
      $brands_to_response = array();
      foreach($brands as $brand_value)
      {
        $brands_to_response[$i]['id'] = $brand_value['id'];
        $brands_to_response[$i]['name'] = $brand_value['name'];
        if(!empty($brand_value['image']))
        {
          $brands_to_response[$i]['image'] = $this->site."/files/brands/".normalurl($brand_value['name'])."/".$brand_value['image'];
        }
        $i++;
      }
      $this->AddToResponse(array("brands" => $brands_to_response));
    }
    else
    {
      $this->AddToResponse(array("response" => array("code" => 4, "message" => "Ошибка выборки брендов. Обратитесь в техподдержку.")));
    }
    return $this->GetResponse();
  }

  public function GetBrands($post, $session)
  {
    $check_user = $this->CheckAutorization($post, $session);
    if($check_user['check'] && isset($check_user['user']))
    {
      if(!empty($post['categories']))
      {
        if($post['categories'] == "all")
        {
          $brands_values = $this->db->QueryArray("SELECT `id`, `name`, `image`  FROM `TGoodsbrands`");
          if(count($brands_values) != 0)
          {
            $i = 0;
            $brands = array();
            foreach($brands_values as $brand_value)
            {
              $brands[$i]['id'] = $brand_value['id'];
              $brands[$i]['name'] = $brand_value['name'];
              if(!empty($brand_value['image'])){
                $brands[$i]['image'] = $this->site."/files/brands/".normalurl($brand_value['name'])."/".$brand_value['image'];
              }
              $i++;
            }
            $this->AddToResponse(array("response" => array("code" => 1, "message" => "Бренды успешно получены.")));
            $this->AddToResponse(array("brands" => $brands));
          }
        }
        else{
          $subscribes = $this->db->QueryArray($sql = "SELECT `TUsersSubscribeEntries`.* FROM `TUsersSubscribes`
                                                  LEFT JOIN `TUsersSubscribeEntries` ON `TUsersSubscribes`.`id` = `TUsersSubscribeEntries`.`subscribe`
                                                  AND `TUsersSubscribeEntries`.`rubric` IN(".$this->db->Escape($post['categories']).") AND `TUsersSubscribeEntries`.`status` = '1'
                                                  WHERE `TUsersSubscribes`.`user` = ".$check_user['user']['auth']." AND `TUsersSubscribes`.`status` = '1' AND `TUsersSubscribeEntries`.`id` IS NOT NULL");
          foreach($subscribes as $subscribe)
          {
            if($subscribe['brand'] == 0)
            {
              $brands = "all";
              break;
            }
            else
            {
              $brands[] = $subscribe['brand'];
            }
          }
          if(!is_array($brands) && $brands == "all")
          {
            $brands_values = $this->db->QueryArray("SELECT `id`, `name`, `image`  FROM `TGoodsbrands`");
          }
          elseif(count($brands) > 0)
          {
            $brands_ids = implode(",", $brands);
            $brands_values = $this->db->QueryArray("SELECT `id`, `name`, `image` FROM `TGoodsbrands` WHERE `id` IN (".$brands_ids.")");
          }
          if(count($brands) != 0)
          {
            $i = 0;
            $brand_to_api = array();
            foreach($brands_values as $brand_value)
            {
              $brand_to_api[$i]['id'] = $brand_value['id'];
              $brand_to_api[$i]['name'] = $brand_value['name'];
              if(!empty($brand_value['image']))
              {
                $brand_to_api[$i]['image'] = $this->site."/files/brands/".normalurl($brand_value['name'])."/".$brand_value['image'];
              }
              $i++;
            }
            $this->AddToResponse(array("response" => array("code" => 1, "message" => "Бренды успешно получены.")));
            $this->AddToResponse(array("brands" => $brand_to_api));
          }
          else
          {
            $this->AddToResponse(array("response" => array("code" => 4, "message" => "У Вас нет активных подписок, либо Вы пытаетесь выбрать бренды категории, к которой не оформлена подписка.")));
          }
        }
      }
      else
      {
        $this->AddToResponse(array("response" => array("code" => 5, "message" => "Ошибка выборки брендов для данной категории. Убедитесь, что Вы правильно указали ID категории.")));
      }
    }
    return $this->GetResponse();
  }

  public function GetList($post, $session)
  {
    $check_user = $this->CheckAutorization($post, $session);
    $wait = 30 * 60;
    if($check_user['check'] && isset($check_user['user']))
    {
      $this->auth->Read($check_user['user']['auth']);
      if(!isset($post['type']))
      {
        $post['type'] = "xml";
      }
      if(isset($check_user['admin']))
      {
        $this->auth->user['id'] = $check_user['user'];
      }
      //$date = mktime(0,0,0, date("n", time()), date("j", time()), date("Y", time()));
      $date = false;
      if(isset($post['date']))
      {
        if(is_numeric($post['date']))
        {
          $date = mktime(0,0,0, date("n", $post['date']), date("j", $post['date']), date("Y", $post['date']));
        }
        else
        {
          preg_match("/\d{1,2}.\d{1,2}.\d{4}/", $post['date'], $matches);
          if(isset($matches[0]))
          {
            $time = strtotime($matches[0]);
            $date = mktime(0,0,0, date("n", $time), date("j", $time), date("Y", $time));
          }
        }
      }
      if(!is_file("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating"))
      {
        $fp = fopen("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating", "w+");
        fclose($fp);
        $filepath = $this->GetGoodsList($date, $post['type'], $this->auth->user['id'], $check_user, (isset($post['page']) && !empty($post['page']) ? (int)$post['page'] : false));
        if($filepath !== false && is_file($filepath))
        {
          @unlink("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating");
          $this->AddToResponse(array("response" => array("code" => 1, "message" => "Файл каталога успешно сгенерирован.")));
          $this->AddToResponse(array("file" => $this->site."/cache/".$this->auth->user['id']."/".basename($filepath)));
        }
        else
        {
          @unlink("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating");
          $this->AddToResponse(array("response" => array("code" => 2, "message" => "Во время генерации файла возникли ошибки.")));
        }
        @unlink("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating");
      }
      else
      {
        if(time() - filemtime("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating") >= $wait)
        {
          @unlink("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating");
        }
        $this->AddToResponse(array("response" => array("code" => 2, "message" => "Во время генерации файла возникли ошибки. Файл уже генерируется другим скриптом.")));
      }
    }
    else
    {
      @unlink("/".trim($_SERVER['DOCUMENT_ROOT'], "/")."/cache/".$this->auth->user['id']."/start_generating");
      $this->AddToResponse(array("response" => array("code" => 3, "message" => "Ваш ключ не прошел проверку.")));
    }

    return $this->GetResponse();
  }

  public function GetGood($post, $session)
  {
    $check_user = $this->CheckAutorization($post, $session);
    $admin = false;
    if($check_user['check'] && isset($check_user['user']))
    {
      if(isset($check_user['admin']))
      {
        $admin = true;
      }
      $this->auth->Read($check_user['user']['auth']);
      if(isset($post['good']) && !empty($post['good']))
      {
        $good = $this->db->QueryLine($sql = "SELECT * FROM `TGoods` WHERE `articul` = '".$this->db->Escape($post['good'])."'");
      }
      elseif(isset($post['id']) && !empty($post['id']) && preg_match ("/^([0-9]+)$/", $post['id']) != FALSE)
      {
        $good = $this->db->QueryLine($sql = "SELECT * FROM `TGoods` WHERE `id` = ".(int)$this->db->Escape($post['id']));
      }
      if(isset($good['id']))
      {
        if(isset($check_user['admin']))
        {
          $check_logo_update['id'] = 0;
          $check_logo_update['added_time'] = 0;
        }else
        {
          $check_logo_update = $this->db->QueryLine('SELECT * FROM `TLogoModeration` WHERE `user` = '.$this->auth->user['id'].' AND `moderation_stage` = 1');
        }

        /*
         * Расчет новых товаров для обновления
         */
        if(isset($check_logo_update['id']) && !$admin)
        {
          if($good['updatetime'] < $check_logo_update['added_time'])
          {
            $good['updatetime'] = $check_logo_update['added_time'];
          }
        }
        if(!$admin)
        {
          $check_subscribe = $this->db->QueryLine("SELECT `TUsersSubscribes`.`id` FROM `TUsersSubscribes`
                                               LEFT JOIN `TUsersSubscribeEntries` ON `TUsersSubscribeEntries`.`subscribe` = `TUsersSubscribes`.`id`
                                               WHERE `TUsersSubscribes`.`user` = ".$this->auth->user['id']."
                                               AND `TUsersSubscribeEntries`.`rubric` = ".$good['type1']."
                                               AND `TUsersSubscribeEntries`.`brand` = ".$good['brand']."
                                               AND `TUsersSubscribeEntries`.`status` = '1'");
        }
        if(isset($check_subscribe['id']) || $admin)
        {
          $query = "SELECT `goods`.*, `brands`.`name` as `brand`,`goods`.`brand` as `brand_id`, `good_styles`.`name_ru` as `good_style`, `rim_shapes`.`name_ru` as `rim_shapes`,
                           `rostest`.`name_ru` as `rostest`, `gost`.`name_ru` as `gost`, `facetype`.`name_ru` as `facetype`, `supplier`.`name_ru` as `supplier`,
                           `brand_factory`.`name_ru` as `brand_factory`, `country`.`name_ru` as `country`, `type`.`name_ru` as `type_name`
                    FROM `TGoods` as `goods`
                    LEFT JOIN `TGoodsbrands` AS `brands` ON `goods`.`brand` = `brands`.`id`
                    LEFT JOIN `TGoodsStyles` AS `good_styles` ON `goods`.`good_style` = `good_styles`.`id`
                    LEFT JOIN `TGoodsRimShapes` AS `rim_shapes` ON `goods`.`rim_shape` = `rim_shapes`.`id`
                    LEFT JOIN `TGoodsGost` AS `gost` ON `goods`.`gost` = `gost`.`id`
                    LEFT JOIN `TGoodsSuppliers` AS `supplier` ON `goods`.`supplier` = `supplier`.`id`
                    LEFT JOIN `TGoodsbrands_factory` AS `brand_factory` ON `goods`.`brand_factory` = `brand_factory`.`id`
                    LEFT JOIN `TGoodsRosTest` AS `rostest` ON `goods`.`rostest` = `rostest`.`id`
                    LEFT JOIN `TGoodsCountries` AS `country` ON `goods`.`country` = `country`.`id`
                    LEFT JOIN `TGoodsFaceTypes` AS `facetype` ON `goods`.`face_type` = `facetype`.`id`
                    LEFT JOIN `TGoodstype1s` AS `type` ON `goods`.`type1` = `type`.`id`
                    WHERE `goods`.`id` = ".$good['id'];
          $good = $this->db->QueryLine($query);
          $good_info_to_api = array();
          $good_info_to_api['name'] = $good['name_ru'];
          $good_info_to_api['unixupdatetime'] = $good['updatetime'];
          $good_info_to_api['brand'] = $good['brand'];
          $good_info_to_api['brand_id'] = $good['brand_id'];
          $good_info_to_api['articul'] = $good['articul'];
          $good_info_to_api['model'] = $good['model'];
          $good_info_to_api['bar_code'] = $good['bar_code'];
          $good_info_to_api['color_code'] = $good['color_code'];
          $good_info_to_api['factory_number'] = $good['factory_number'];
          $good_info_to_api['category'] = $good['type_name'];
          $good_info_to_api['categoryId'] = $good['type1'];
          $alternative_articuls = $this->db->QueryList("SELECT `id`, `articul` FROM `TGoodsalternative_articuls` WHERE `parent` = ".$good['id'], 'articul');
          if(count($alternative_articuls) > 0)
          {
            $good_info_to_api['alternative_articuls'] = array_keys($alternative_articuls);
          }
          else
          {
            $good_info_to_api['alternative_articuls'] = array();
          }
          if($good['type1'] == 1 || $good['type1'] == 2)
          {
            $good_info_to_api['gender'] = $good['gender'];
            $good_info_to_api['age'] = $good['age'];
          }
          if(!$admin)
          {
            if(!empty($good['image']))
            {
              $img_path = $this->site."/actions.php?mode=getimage&picture=main&good=".$good['id']."&key=".$this->auth->user['access_key'];
            }
            else
            {
              $img_path = '';
            }
            $good_info_to_api['picture'] = $img_path;
            if(!empty($good['promo_image']))
            {
              $img_path = $this->site."/actions.php?mode=getimage&picture=promo&good=".$good['id']."&key=".$this->auth->user['access_key'];
            }
            else
            {
              $img_path = '';
            }
            $good_info_to_api['promo'] = $img_path;
            if(!empty($good['accessory_image']))
            {
              $img_path = $this->site."/actions.php?mode=getimage&picture=accessory&good=".$good['id']."&key=".$this->auth->user['access_key'];
            }
            else
            {
              $img_path = '';
            }
            $good_info_to_api['accessory'] = $img_path;
            $good_info_to_api['images'] = "";
            $images = $this->db->QueryArray("SELECT * FROM `TGoodsimages` WHERE `parent` = ".$good['id']);
            if(count($images) > 0)
            {
              foreach($images as $image)
              {
                switch(strtolower($image['image']))
                {
                  case "2.jpg" :
                  {
                    $good_info_to_api['images']['front'] = $this->site."/actions.php?mode=getimage&picture=front&good=".$good['id']."&key=".$this->auth->user['access_key'];
                  }
                    break;
                  case "3.jpg" :
                  {
                    $good_info_to_api['images']['left'] = $this->site."/actions.php?mode=getimage&picture=left&good=".$good['id']."&key=".$this->auth->user['access_key'];
                  }
                    break;
                  case "4.jpg" :
                  {
                    $good_info_to_api['images']['right'] = $this->site."/actions.php?mode=getimage&picture=right&good=".$good['id']."&key=".$this->auth->user['access_key'];
                  }
                    break;
                  case "5.jpg" :
                  {
                    $good_info_to_api['images']['left_back'] = $this->site."/actions.php?mode=getimage&picture=left_back&good=".$good['id']."&key=".$this->auth->user['access_key'];
                  }
                    break;
                  case "6.jpg" :
                  {
                    $good_info_to_api['images']['right_back'] = $this->site."/actions.php?mode=getimage&picture=right_back&good=".$good['id']."&key=".$this->auth->user['access_key'];
                  }
                    break;
                  case "vp.png":
                  {
                    $good_info_to_api['images']['virtual_room'] = $this->site."/actions.php?mode=getimage&picture=virtual_room&good=".$good['id']."&key=".$this->auth->user['access_key'];
                  }
                    break;
                  default: continue;
                }
              }
            }
            if($good['view_3d'])
            {
              $images_3d = $this->db->QueryArray("SELECT * FROM `TImages3DView` WHERE `good_id` = ".$good['id']." ORDER BY `degree`");
              $links = array();
              $link_srt = "";
              if(count($images_3d) > 0)
              {
                foreach($images_3d as $image)
                {
                  $links[] = $this->site."/actions.php?mode=getimage&good=".$good['id']."&3d=1&degree=".$image['degree']."&key=".$this->auth->user['access_key'];
                }
                $link_srt = implode(";", $links);
              }

              $good_info_to_api['view_3d'] = "";
              if(count($images_3d) == 36)
                $good_info_to_api['view_3d'] = $link_srt;
              if(count($images_3d) == 36)
              {
                $good_info_to_api['preview_3d'] = $this->site."/actions.php?mode=getimage&good=".$good['id']."&3d=1&degree=all&key=".$this->auth->user['access_key'];
              }
              else
              {
                $good_info_to_api['preview_3d'] = "";
              }
            }

          }
          else
          {
            $good_info_to_api['supplier_price'] = $good['supplier_price'];
          }
          $good_info_to_api['supplier'] = $good['supplier'];
          $good_info_to_api['factory'] = $good['brand_factory'];
          if($good['type1'] == 1 || $good['type1'] == 2)
          {
            $good_info_to_api['country'] = $good['country'];
            $good_info_to_api['rostest'] = $good['rostest'];
            $good_info_to_api['gost'] = $good['gost'];
            $good_info_to_api['case'] = $good['case'];
            $good_info_to_api['release'] = !empty($good['release']) ? date("d.m.Y", (int)$good['release']) : "";
            $good_info_to_api['release_rf'] = !empty($good['release_rf']) ? date("d.m.Y", (int)$good['release_rf']) : "";
            $good_info_to_api['style'] = $good['good_style'];
            $good_info_to_api['rim_shape'] = $good['rim_shapes'];
            $good_info_to_api['face_type'] = $good['facetype'];
            $good_info_to_api['rim_properties'] = $this->GetGoodPropertyValue('Rim', $good);
            $good_info_to_api['lense_properties'] = $this->GetGoodPropertyValue('Lenses', $good);
            $good_info_to_api['description'] = $this->GetGoodPropertyValue('Description', $good);
          }
          if($good['type1'] == 4)
          {
            $good_info_to_api['properties'] = $this->GetGoodPropertyValue('Properties', $good);
          }
          $this->AddToResponse(array("response" => array("code" => 1, "message" => "Товар успешно найден.")));
          $this->AddToResponse(array("good" => $good_info_to_api));
        }
        else
        {
          $this->AddToResponse(array("response" => array("code" => 3, "message" => "Данный товар не доступен по Вашей подписке.")));
        }
      }
      else
      {
        $this->AddToResponse(array("response" => array("code" => 2, "message" => "Товар не найден в базе.")));
      }
    }
    else
    {
      $this->AddToResponse(array("response" => array("code" => 4, "message" => "Ваш ключ не прошел проверку.")));
    }

    return $this->GetResponse();
  }

  public function GetGoodsCount($post, $session)
  {
    $check_user = $this->CheckAutorization($post, $session);
    if($check_user['check'] && isset($check_user['user']))
    {
      $this->auth->Read($check_user['user']['auth']);
      if (isset($check_user['admin']))
      {
        $count = $this->db->QueryLine("SELECT COUNT(*) as `count`
                FROM `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> ''");

      }
      else
      {
        $count = $this->db->QueryLine("SELECT COUNT(*) as `count`
                FROM `TUsersSubscribes`, `TUsersSubscribeEntries`, `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `TUsersSubscribes`.`user` = ".$this->auth->user['id']." AND `TUsersSubscribeEntries`.`status` = '1'
                AND `TUsersSubscribeEntries`.`subscribe` = `TUsersSubscribes`.`id`
                AND `TUsersSubscribeEntries`.`brand` = `goods`.`brand` AND `TUsersSubscribeEntries`.`rubric` = `goods`.`type1`");
      }
      $this->AddToResponse(array("response" => array("code" => 1, "message" => "Количество товаров успешно выгружено.")));
      $this->AddToResponse(array("count" => $count['count']));
    }
    else
    {
      $this->AddToResponse(array("response" => array("code" => 3, "message" => "Ваш ключ не прошел проверку.")));
    }

    return $this->GetResponse();
  }

  public function GetPagesCount($post, $session)
  {
    $check_user = $this->CheckAutorization($post, $session);
    if($check_user['check'] && isset($check_user['user']))
    {
      $this->auth->Read($check_user['user']['auth']);
      if (isset($check_user['admin']))
      {
        $count = $this->db->QueryLine("SELECT COUNT(*) as `count`
                FROM `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `goods`.`articul` <> ''");

      }
      else
      {
        $count = $this->db->QueryLine("SELECT COUNT(*) as `count`
                FROM `TUsersSubscribes`, `TUsersSubscribeEntries`, `TGoods` as `goods`
                WHERE `goods`.`visibility` = '1' AND `TUsersSubscribes`.`user` = ".$this->auth->user['id']." AND `TUsersSubscribeEntries`.`status` = '1'
                AND `TUsersSubscribeEntries`.`subscribe` = `TUsersSubscribes`.`id`
                AND `TUsersSubscribeEntries`.`brand` = `goods`.`brand` AND `TUsersSubscribeEntries`.`rubric` = `goods`.`type1`");
      }
      if($count['count'] == 0)
      {
        $pages = 0;
      }
      else
      {
        $pages = ceil($count['count'] / MAX_API_GOODS_COUNT);
      }
      $this->AddToResponse(array("response" => array("code" => 1, "message" => "Количество страниц успешно выгружено.")));
      $this->AddToResponse(array("count" => $pages));
    }
    else
    {
      $this->AddToResponse(array("response" => array("code" => 3, "message" => "Ваш ключ не прошел проверку.")));
    }

    return $this->GetResponse();
  }
}