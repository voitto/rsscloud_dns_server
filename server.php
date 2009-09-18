<?php
$accounts = array(
  array(
    'test',
    'loose.ly'
  ),
  array(
    'brian',
    'bh.ly'
  )
);
if (!function_exists('dns_get_record'))
  dl("dns_get_record.so");
$data = '';
$feedurl = '';
$ip = '174.129.159.23';
$db   = "mydns";
$host = "localhost";
$user = "root";
$pass = "yourrootsqlpassword";
$conn = mysql_connect( $host, $user, $pass ); 
$select = mysql_select_db( $db );
if ( acct_auth($accounts) ) {
  $name = mysql_escape_string( $_POST['name'] );
  $record = mysql_escape_string( $_POST['record'] );
  $feedname = mysql_escape_string( $_POST['feedname'] );
  if (isset($_POST['feedurl']))
    $feedurl = mysql_escape_string( $_POST['feedurl'] );
  $parts = split( '\.',acct_domain($accounts) );
  if ( count($parts) == 2 ) {
    $sql = "SELECT * FROM soa WHERE ns LIKE '".$parts[0].".".$parts[1].".'";
    $result = mysql_query( $sql );
    if (mysql_num_rows($result) == 1) {
      $zoneid = mysql_result($result,0,"id");
      if ($zoneid) {
        $sql = "SELECT * FROM rr WHERE type LIKE '$record' AND zone = $zoneid AND name LIKE '$name'";
        $result = mysql_query( $sql );
        if (mysql_num_rows($result) == 1) {
          $id = mysql_result($result,0,'id');
          if (empty($feedurl))
            $sql = "UPDATE rr SET feedname = '$feedname' WHERE id = $id";
          else
            $sql = "UPDATE rr SET feedname = '$feedname', data = '$feedurl' WHERE id = $id";
          $result = mysql_query( $sql );
          if (!$result)
            http_error('error updating feedname');
        } elseif (mysql_num_rows($result) == 0) {
          $sql = "INSERT INTO rr (zone,name,type,data,aux,ttl,feedname) VALUES ($zoneid,'$name','$record','$feedurl',0,86400,'$feedname')";
          $result = mysql_query( $sql );
          if (!$result)
            http_error('error inserting zone record');
        } else {
          http_error('error updating zone record');
        }
      } else {
        http_error('bad zoneid while updating zone');
      }
    } elseif(!mysql_num_rows($result))  {
      $zone = $parts[0].".".$parts[1].".";
      $sql = "INSERT INTO soa (origin,ns,mbox,serial,refresh,retry,expire,minimum,ttl,active,xfer) VALUES ('$zone','$zone','postmaster.$zone',2009091702,300,300,86400,86400,86400,'Y','')";
      $result = mysql_query( $sql );
      if ($result) {
        $id = mysql_insert_id();
        $sql = "INSERT INTO rr (zone,name,type,data,aux,ttl,feedname) VALUES ($id,'$name','$record','$feedurl',0,86400,'$feedname')";
        $result = mysql_query( $sql );
        if (!$result)
          http_error('error inserting zone record');
        $sql = "INSERT INTO rr (zone,name,type,data,aux,ttl,feedname) VALUES ($id,'$name','A','$ip',0,86400,'$feedname')";
        $result = mysql_query( $sql );
        if (!$result)
          http_error('error inserting zone record');
      } else {
        http_error('error inserting zone');
      }
      $data = $feedurl;
    } else {
      http_error('error creating zone');
    }
  } else {
		http_error('must be a domain like domain.com');
  }
}
if (empty($data) && isset($_POST['name']) && isset($_POST['record']) && isset($_POST['feedname'])) {
  $name = mysql_escape_string( $_POST['name'] );
  $record = mysql_escape_string( $_POST['record'] );
  $feedname = mysql_escape_string( $_POST['feedname'] );
  $sql = "SELECT data FROM rr WHERE type LIKE '$record' AND name LIKE '$name' AND feedname LIKE '$feedname'";
  $result = mysql_query( $sql );
  if ($result && mysql_num_rows($result) == 1) {
    $result = @dns_get_record($feedname);
    if (is_array($result))
      foreach($result as $arr)
        if ($arr['type'] == $record)
          $data = $arr['txt'];
  }
}
function http_error($str) {
  header('HTTP/1.1 500 Internal Server Error'); 
  echo $str;
  exit;
}
function acct_domain($accounts) {
  foreach($accounts as $arr) {
    if (md5( $_POST['name'].$_POST['record'].$_POST['feedname'].$arr[0] ) == $_POST['key'])
      return $arr[1];
  }
  http_error('not authorized');
}
function acct_auth($accounts) {
  foreach($accounts as $arr) {
    if (md5( $_POST['name'].$_POST['record'].$_POST['feedname'].$arr[0] ) == $_POST['key'])
      return true;
  }
  return false;
}
header('HTTP/1.1 200 OK'); 
echo $data;