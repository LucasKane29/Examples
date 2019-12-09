<?php
use \Curl\Curl;
class TParser
{
  private $parser_dir_name = 'tparser',
          $parser_content_dir_name = 'content',
          $parser_queue_name = 'queue.json',
          $cookie_file = 'cookies.txt',
          $parser_work_name = 'work',
          $parser_content_dir = '',
          $parser_dir = '',
          $parser_queue = '',
          $parser_work = '',
          $queue = array(),
          $max_work_time = 90,
          $max_pages = 3,
          $main_page = "https://www.ozon.ru",
          $google_bot_useragent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36 OPR/56.0.3051.116",
          $request_array = array("areaId" => 2,
                                 "areaData" => array("fias" => "0c5b2444-70a0-4932-980c-b4dc0d3f02b5",
                                                     "latitude" => 55.755787,
                                                     "longitude" => 37.617634),
                                 "currency" => array("RUB"),
                                 "page" => 1,
                                 "filters" => array(),
                                 "category" => 0,
                                 "sort" => "SORT_TYPE_RELEVANCY"
          ),
          $request_api_url = "https://api.ozon.ru/catalog-api.bx/navi/v1/catalog/search",
          $months = array(
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
          );
  function __construct()
  {
    $this->parser_dir = '/'.$this->parser_dir_name.'/';
    $this->parser_content_dir = $this->parser_dir.$this->parser_content_dir_name.'/';
    $this->parser_queue = $this->parser_dir.$this->parser_queue_name;
    $this->parser_work = $this->parser_dir.$this->parser_work_name;
    $this->cookie_file = DR.$this->parser_dir.$this->cookie_file;
    if (!is_dir(DR.$this->parser_dir)) mkdir(DR.$this->parser_dir, 0755);
    if (!is_dir(DR.$this->parser_content_dir)) mkdir(DR.$this->parser_content_dir, 0755);
    //die(pre($this->cookie_file));
    $this->readQueue();
  }

  public function remove($id)
  {
    if (isset($this->queue[$id]))
    {
      unset($this->queue[$id]);
      $this->updateQueue();
    }
  }

  public function get($id)
  {
    if (is_file(DR.$this->parser_content_dir.$id.'.json'))
    {
      return file_get_contents(DR.$this->parser_content_dir.$id.'.json');
    }
    else
    {
      return false;
    }
  }

  public function push($url)
  {
    if (!empty($url))
    {
      $check = $this->isUniqueUrl($url);

      if ($check === true)
      {
        $length = count($this->queue);
        $id = $length + 1;

        $this->queue[$id] = array(
          'url' => $url,
          'addtime' => time(),
          'parsetime' => 0,
          'current_page' => 0,
          'all_pages' => 0,
          'all_goods' => 0,
          'goods_parsed' => 0,
          'parsetime' => 0,
          'status' => '',
          'finished' => 0,
        );
        $this->queue[$id]['id'] = $id;

        $this->updateQueue();

        return $id;
      }
      else
      {
        return $check;
      }
    }

    return false;
  }

  public function parse()
  {
    if ($this->checkWorkFile())
    {
      //$this->createWorkFile();

      $queue = $this->getNextQueue();

      if (!empty($queue))
      {
        if($queue['current_page'] != $queue['all_pages'] || $queue['parsetime'] == 0)
        {
          $id = $queue['id'];
          $url = $queue['url'];
          $current_page = $queue['current_page'] + 1;
          $content = $this->getContent($url);
          $this->skipProtect($url, $content);
          $content = $this->getJSONContent($url, $current_page);
          if (!empty($content) && $content !== NULL)
          {
            $status = $content['status'];
            $data = $content['data'];
            $domain = $content['domain'];

            if ($this->checkStatus($status))
            {
              if($queue['parsetime'] == 0)
              {
                $pages = isset($data->totalPages) ? $data->totalPages : 0;
                $all_goods = isset($data->totalFound) ? $data->totalFound : 0;
                $goods_count = 0;
              }
              else
              {
                $pages = $queue['all_pages'];
                $all_goods = $queue['all_goods'];
                $goods_count = $queue['goods_parsed'];
              }

              if ($pages != 0 && $current_page <= $pages)
              {
                $previous_goods = array();
                if($goods_count > 0)
                {
                  $previous_goods = $this->getPreviousContent($id);
                }
                $goods = array();
                $page_goods = array();
                if(isset($data->tiles) && count($data->tiles) > 0)
                {
                  $i = 0;
                  foreach ($data->tiles as $tile)
                  {
                    $goods[$i]['title'] = $tile->name;
                    $goods[$i]['url'] = $domain.$tile->relLink;
                    $goods[$i]['price'] = $tile->price->price;
                    $goods[$i]['price_curr'] = $tile->price->currency;
                    $goods[$i]['delivery'] = "";
                    $goods[$i]['city'] = "";
                    $goods[$i]['date'] = "";
                    $last_breadcrumb = end($tile->breadCrumbs);
                    $goods[$i]['category'] = $last_breadcrumb->name;
                    $goods[$i]['company'] = end($tile->brand);
                    $goods[$i]['images'] = array($tile->main->info->coverImage);
                    if(count($tile->main->images) > 0)
                    {
                      $goods[$i]['images'] = array_merge($goods[$i]['images'], $tile->main->images);
                    }
                    $goods_count++;
                    $i++;
                  }
                }
                if($current_page < $pages)
                {
                  $j = 0;
                  $current_page += 1;
                  for($i = 1; $i <= $this->max_pages && $current_page <= $pages; $i++)
                  {
                    $content = $this->getJSONContent($url, $current_page);
                    $status = $content['status'];
                    $data = $content['data'];
                    $domain = $content['domain'];
                    if(isset($data->tiles) && count($data->tiles) > 0)
                    {
                      foreach ($data->tiles as $tile)
                      {
                        $page_goods[$j]['title'] = $tile->name;
                        $page_goods[$j]['url'] = $domain.$tile->relLink;
                        $page_goods[$j]['price'] = $tile->price->price;
                        $page_goods[$j]['price_curr'] = $tile->price->currency;
                        $page_goods[$j]['delivery'] = "";
                        $page_goods[$j]['city'] = "";
                        $page_goods[$j]['date'] = "";
                        $last_breadcrumb = end($tile->breadCrumbs);
                        $page_goods[$j]['category'] = $last_breadcrumb->name;
                        $page_goods[$j]['company'] = end($tile->brand);
                        $page_goods[$j]['images'] = array($tile->main->info->coverImage);
                        if(count($tile->main->images) > 0)
                        {
                          $page_goods[$j]['images'] = array_merge($page_goods[$j]['images'], $tile->main->images);
                        }
                        $goods_count++;
                        $j++;
                      }
                    }
                    $current_page += 1;
                  }
                  $current_page -= 1;
                  if(count($previous_goods) > 0)
                  {
                    $goods = array_merge($previous_goods, $goods);
                  }
                  if(count($page_goods) > 0)
                  {
                    $goods = array_merge($goods, $page_goods);
                  }
                }
                $this->saveContent($id, $goods);
                $this->updateQueue($id, $status, $all_goods, $goods_count, $current_page, $pages);
              }
              else
              {
                $this->updateQueue($id, $status, $queue['all_goods'], $queue['goods_parsed'], $queue['current_page'], $queue['all_pages'], 1);
              }
            }
            else
            {
              $this->updateQueue($id, $status, $queue['all_goods'], $queue['goods_parsed'], $queue['current_page'], $queue['all_pages'], 1);
            }
          }
        }
        else
        {
          $this->updateQueue($queue['id'], $queue['status'], $queue['all_goods'], $queue['goods_parsed'], $queue['current_page'], $queue['all_pages'], 1);
        }
      }
      $this->deleteWorkFile();
    }

  }

  private function xpathGetValue($xpath, $query, $from, $dbg = false)
  {
    $item = $xpath->query($query, $from);

    if ($dbg)
    {
      pre($query);
      pre($item);
    }

    if (!empty($item) && $item->length > 0)
    {
      $value = trim($item->item(0)->nodeValue);

      if ($dbg)
      {
        pre($value);
      }

      return $value;
    }
    return false;
  }

  private function xpathGetValues($xpath, $query, $from, $dbg = false)
  {
    $result = array();

    $items = $xpath->query($query, $from);

    if ($dbg)
    {
      pre($query);
      pre($items);
    }

    if (!empty($items))
    {
      foreach($items as $item)
      {
        $value = $item->nodeValue;

        if ($dbg)
        {
          pre($value);
        }

        $result[] = $value;
      }
    }

    return $result;
  }

  private function formatDate($date)
  {
    $month_now = $this->getMonth(date('m', time()));
    $month_yesterday = $this->getMonth(date('m', time() - 24 * 60 * 60));

    $date = str_replace('Сегодня', date('j').' '.$month_now, $date);
    $date = str_replace('Вчера', date('j').' '.$month_yesterday, $date);

    return $date;
  }

  private function getMonth($num)
  {
    if (isset($this->months[$num]))
    {
      return $this->months[$num];
    }
    return '';
  }

  private function formatPrice($price)
  {
    return (int)floor(str_replace(' ', '', $price));
  }

  private function checkStatus($status)
  {
    return ($status == 200);
  }

  private function saveContent($id, $elements, $dbg = false)
  {
    if ($dbg)
    {
      $json = json_encode($elements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    else
    {
      $json = json_encode($elements);
    }

    file_put_contents(DR.$this->parser_content_dir.$id.'.json', $json);
  }

  private function getPreviousContent($id)
  {
    $goods_array = file_get_contents(DR.$this->parser_content_dir.$id.'.json');
    return json_decode($goods_array, true);
  }

  private function getContent($url)
  {
    $curl = new Curl();
    $curl->setUserAgent($this->google_bot_useragent);
    $curl->setReferrer($this->main_page);
    $curl->setCookieJar($this->cookie_file);
    $curl->setCookieFile($this->cookie_file);
    $curl->post($url);

    if ($curl->error)
    {
      echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
    }
    else
    {
      $output = $curl->response;
      $status = $curl->httpStatusCode;
    }
    $domain = parse_url($url);
    $domain = $domain['scheme'].'://'.$domain['host'];
    $curl->close();
    return array('html' => $output, 'status' => $status, 'domain' => $domain);
  }

  private function getJSONContent($url = '', $page = false)
  {
    $curl = new Curl();
    $bearer = "";
    $group = "";
    $cookies = $this->extractCookies(file_get_contents($this->cookie_file));
    if(count($cookies) > 0)
    {
      foreach ($cookies as $cookie)
      {
        if($cookie['name'] == "access_token")
        {
          $bearer = $cookie['value'];
        }
        if($cookie['name'] == "abGroup")
        {
          $group = $cookie['value'];
        }
      }
    }
    if(!empty($bearer) && !empty($group))
    {

      $this->request_array['category'] = $this->getCategory($url);
      if($this->request_array['category'] !== false)
      {
        if($page !== false)
        {
          $this->request_array['page'] = $page;
        }
        $options = json_encode($this->request_array);
        $curl->setUserAgent($this->google_bot_useragent);
        $curl->setReferrer($url);
        $curl->setCookieJar($this->cookie_file);
        $curl->setCookieFile($this->cookie_file);
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Origin', $this->main_page);
        $curl->setHeader('Authorization', 'Bearer '.$bearer);
        $curl->setHeader('Content-Length', strlen($options));
        $curl->setHeader('x-o3-app-name', 'ozon_new');
        $curl->setHeader('x-ozon-abgroup', $group);
        $curl->setHeader(':authority', 'api.ozon.ru');
        $curl->setHeader(':method', 'POST');
        $curl->setHeader(':path', '/catalog-api.bx/navi/v1/catalog/search');
        $curl->setHeader(':scheme', 'https');
        $curl->post($this->request_api_url, $options);

        if ($curl->error)
        {
          echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
        }
        else
        {
          $output = $curl->response;
          $status = $curl->httpStatusCode;
        }

        $domain = parse_url($url);
        $domain = $domain['scheme'].'://'.$domain['host'];
        $curl->close();
        return array('data' => $output, 'status' => $status, 'domain' => $domain);
      }
      else
      {
        return NULL;
      }
    }
  }

  private function skipProtect($url, &$content)
  {
    $html = $content['html'];
    if($html != "")
    {
      $dom = new DOMDocument();
      @$dom->loadHTML($html, LIBXML_NOWARNING);
      $xpath = new DOMXpath($dom);
      $iframe_url = $this->xpathGetValue($xpath,'//iframe/@src', $dom);
      $src_url = $this->xpathGetValue($xpath,'//script/@src', $dom);
      if(($iframe_url != "" && strpos($iframe_url, "/_Incapsula_Resource") !== false) || ($src_url != "" && strpos($src_url, "/_Incapsula_Resource") !== false))
      {
        unlink($this->cookie_file);

        $output = "";
        $curl = new Curl();
        $curl->setUserAgent($this->google_bot_useragent);
        $curl->setCookieJar($this->cookie_file);
        $curl->setCookieFile($this->cookie_file);
        $curl->setReferrer($this->main_page);
        $curl->post($url);

        if ($curl->error)
        {
          echo 'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage . "\n";
        }
        else
        {
          $output = $curl->response;
          $status = $curl->httpStatusCode;
        }
        $curl->close();
        if($output != "")
        {
          $dom = new DOMDocument();
          @$dom->loadHTML($output, LIBXML_NOWARNING);

          $xpath = new DOMXpath($dom);
          $iframe_url = $this->xpathGetValue($xpath,'//iframe/@src', $dom);
          $src_url = $this->xpathGetValue($xpath,'//script/@src', $dom);
          if(($iframe_url != "" && strpos($iframe_url, "/_Incapsula_Resource") !== false) || ($src_url != "" && strpos($src_url, "/_Incapsula_Resource") !== false))
          {
            if(!empty($iframe_url))
            {
              $next_url = $this->main_page.$iframe_url;

            }
            elseif($src_url)
            {
              $next_url = $this->main_page.$src_url;
            }
            $this->getContent($next_url);
            $curl->setReferrer("https://google.ru");
            $main_page_content = $this->getContent($this->main_page);
            $curl->setReferrer($this->main_page);
            $content = $this->getContent($url);
          }
          else
          {
            $domain = parse_url($url);
            $domain = $domain['scheme'].'://'.$domain['host'];
            $content = array('html' => $output, 'status' => $status, 'domain' => $domain);
          }
        }
      }
    }
  }

  private function readQueue()
  {
    if (is_file(DR.$this->parser_queue))
    {
      $content = file_get_contents(DR.$this->parser_queue);
      $this->queue = json_decode($content, true);
    }
  }

  private function updateQueue($id = 0, $status = '', $all_goods = 0, $goods_parsed = 0, $current_page = 0, $all_pages = 0, $finished = 0)
  {
    if (!empty($id) && isset($this->queue[$id]))
    {
      $this->queue[$id]['parsetime'] = time();
      $this->queue[$id]['status'] = $status;
      $this->queue[$id]['current_page'] = $current_page;
      $this->queue[$id]['all_pages'] = $all_pages;
      $this->queue[$id]['all_goods'] = $all_goods;
      $this->queue[$id]['goods_parsed'] = $goods_parsed;
      $this->queue[$id]['finished'] = $finished;
    }
    $content = json_encode($this->queue);
    file_put_contents(DR.$this->parser_queue, $content);
  }

  private function checkWorkFile()
  {
    if (is_file(DR.$this->parser_work))
    {
      $create_time = filectime(DR.$this->parser_work);
      if ($create_time)
      {
        if ((time() - $create_time) >= $this->max_work_time)
        {
          $this->deleteWorkFile();
          return true;
        }
      }
      return false;
    }
    return true;
  }

  private function createWorkFile()
  {
    if (!is_file(DR.$this->parser_work))
    {
      file_put_contents(DR.$this->parser_work, '');
    }
  }

  private function deleteWorkFile()
  {
    if (is_file(DR.$this->parser_work))
    {
      unlink(DR.$this->parser_work);
    }
  }

  private function isUniqueUrl($url)
  {
    if (!empty($this->queue))
    {
      foreach($this->queue as $id => $queue)
      {
        if ($queue['url'] == $url)
        {
          return $id;
        }
      }
    }
    return true;
  }

  private function getNextQueue()
  {
    if (!empty($this->queue))
    {
      $time = 0;
      $id = 0;
      foreach($this->queue as $key => $queue)
      {
        if (($time == 0 || $queue['parsetime'] < $time) && $queue['finished'] != 1)
        {
          $time = $queue['parsetime'];
          $id = $key;

          if ($time == 0) break;
        }
      }
      if (!empty($id))
      {
        return $this->queue[$id];
      }
    }
    return false;
  }

  private function extractCookies($string)
  {
    $cookies = array();

    $lines = explode("\n", $string);

    // iterate over lines
    foreach ($lines as $line) {

      // we only care for valid cookie def lines
      if (isset($line[0]) && substr_count($line, "\t") == 6) {

        // get tokens in an array
        $tokens = explode("\t", $line);

        // trim the tokens
        $tokens = array_map('trim', $tokens);

        $cookie = array();

        // Extract the data
        $cookie['domain'] = $tokens[0];
        $cookie['flag'] = $tokens[1];
        $cookie['path'] = $tokens[2];
        $cookie['secure'] = $tokens[3];

        // Convert date to a readable format
        $cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);

        $cookie['name'] = $tokens[5];
        $cookie['value'] = $tokens[6];

        // Record the cookie.
        $cookies[] = $cookie;
      }
    }

    return $cookies;
  }

  private function getCategory($string)
  {
    if(preg_match('/.*\/category\/(\d*)\/?/', $string, $matches) !== FALSE)
    {
      if(isset($matches[1]) && !empty($matches[1]) && is_numeric($matches[1]))
      {
        return $matches[1];
      }
    }
    return false;
  }
}
?>