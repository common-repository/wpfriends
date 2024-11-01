<?php
/*
Plugin Name: WPfriends
Author: Sascha Bias
Version: 0.1.3
*/


function liz_install() 
{
  global $wpdb;

  if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) 
  {
    if (!empty($wpdb->charset)) 
    {
      $charset_collate .= " DEFAULT CHARACTER SET $wpdb->charset";
    }
    if (!empty($wpdb->collate)) 
    {
      $charset_collate .= " COLLATE $wpdb->collate";
    }
  }

  $result = $wpdb->query("
    CREATE TABLE wpfriends (
      `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
      `displayname` VARCHAR( 255 ) NOT NULL ,
      `type` VARCHAR( 16 ) NOT NULL ,
      `data` VARCHAR( 255 ) NOT NULL
    ) $charset_collate
  ");
}
register_activation_hook(__FILE__, 'liz_install');


function liz_admin_menu()
{
  add_submenu_page('index.php', 'Wordpress friends', 'Friends', 8, __FILE__, 'liz_friends');
  add_options_page('WPfriends', 'WPfriends', 10, basename(__FILE__), 'liz_friends_options');

}
add_action('admin_menu', 'liz_admin_menu');

function liz_friends()
{
  global $wpdb;
  // handle actions
  if ($_POST[lizaction] == 'addfriend')
  {
    $wpdb->query('insert into wpfriends (`type`, `data`) values ("'.$wpdb->escape($_POST[type]).'","'.$wpdb->escape($_POST[data]).'")');
    echo htmlentities($_POST[data])." added<br>";
  }

  /////////////////////////////////////////////////////

  readfile('../wp-content/plugins/wpfriends/adminhead.html');

  $entries = array();
  $friends = $wpdb->get_results('select * from wpfriends');
  $types['wp'] = 'WordPress';
  $types['lj'] = 'LiveJournal';

  if ($_GET['what'] == 'edit')
  {
 echo '<h1>does not work yet, sorry</h1>';
    echo '<table>';
    foreach ($friends as $friend)
    {
      echo '<tr>';
      echo '<td>'.$types[$friend->type].'</td>';
      echo '<td>'.htmlentities($friend->data).'</td>';
      echo '<td><input value="'.htmlentities($friend->displayname).'"></td>';
      echo '</tr>';
    }
    echo '</table>';
  }
  else
  {
    // collect data
    foreach ($friends as $friend)
    {
      foreach (liz_get_feed($friend->type, $friend->data, $friend->displayname) as $entry)
      {
        $entries[$entry[id]] = $entry;
      }
    }

    // prepare data
    $sortarr = array();
    foreach ($entries as $entry)
    {
      $sortarr[$entry['id']] = $entry['time'];
    }
    arsort ($sortarr);
  
    $i = 0;
    // display data
    foreach (array_keys($sortarr) as $entry)
    {
      if ($i++ == 20) break;
      $entry = $entries[$entry];
      echo '<table class="widefat fixed" cellspacing="0">';
      echo '<tr><th class="manage-column"><a href="'.$entry[link].'">'.htmlentities($entry[title]).'</a> ( ' . htmlentities($entry[displayname]) . ', '.date("d.m.Y H:i:s", $entry[time]).' )</th></tr>';
      echo '<tr><td>'.$entry[content].'</th></tr>';
      echo '</table><br>';
    }
  }
}

function liz_friends_options()
{
  $options = array('LJusername', 'LJpassword');
  if ($_POST['action'] == 'optionsave')
  {
    foreach($options as $option)
    {
      if ($_POST[$option] != '**~DONOTSAVETHISOPTION~~!,') update_option('wpfriends.'.$option, $_POST[$option]);
    } 
  }

  $admpage = file_get_contents("../wp-content/plugins/wpfriends/options.html");
  foreach($options as $option)
  {
    if (substr($option, -8, 8) != 'password') { $data = get_option('wpfriends.'.$option); }
    else { $data = '**~DONOTSAVETHISOPTION~~!,'; }
    $admpage = str_replace('['.$option.']', $data, $admpage);
  }
  echo $admpage;
}

/// helper

function liz_get_feed($type, $data, $displayname)
{
  if (empty($displayname)) $displayname = $data . ' @ ' . $type;
  $rr = array();
  $r = array('displayname' => $displayname);
  if ($type == 'wp')
  {
    $url = $data.'/?feed=rss2';
    $xmlstr = liz_download($url);
    if (substr($xmlstr,0,5) == '<?xml')
    {
      $xml = new SimpleXMLElement($xmlstr);
      foreach ($xml->channel->item as $entry)
      {
        $r['id'] = (string) $entry->link;
        $r['time'] = strtotime((string) $entry->pubDate);
        $r['title'] = (string) $entry->title;
        $r['content'] = (string) $entry->description;
        $r['link'] = (string) $entry->link;
        $rr[] = $r;
      }
    }
  }
  elseif ($type == 'lj')
  {
    $username = get_option('wpfriends.LJusername');
    $password = get_option('wpfriends.LJpassword');

    $url = 'http://'.$data.'.livejournal.com/data/atom';
    if (!empty($username) && !empty($password)) 
    {
      $xmlstr = liz_download($url.'?auth=digest', "$username:$password");
    }
    else
    {
      $xmlstr = liz_download($url);
    }
    if (substr($xmlstr,0,5) == '<?xml')
    {
      $xml = new SimpleXMLElement($xmlstr);
      foreach ($xml->entry as $entry)
      {
        $r['id'] = (string) $entry->id;
        $r['time'] = strtotime((string) $entry->updated);
        $r['title'] = (string) $entry->title;
        $r['content'] = (string) $entry->content;
        $r['link'] = (string) $entry->link[0]->attributes()->href;
  //      echo '<pre>'.htmlentities(print_r($entry->link[0]->attributes(),1)).'<pre>';
        $rr[] = $r;
      }
    }
  }



  return $rr;
}


function liz_download ($url, $auth = '') 
{
  $ch = curl_init ($url);
  curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt ($ch, CURLOPT_USERAGENT, 'WordpressFriends Plugin');
  if (! empty($auth))
  {
    curl_setopt ($ch, CURLOPT_USERPWD, $auth);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
  }
  $res = curl_exec ($ch);

  curl_close ($ch);
  return $res;
}

?>
