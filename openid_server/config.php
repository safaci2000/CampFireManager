<?php
/**
 * The URL for the server.
 *
 * This is the location of server.php. For example:
 *
 * $server_url = 'http://example.com/~user/server.php';
 *
 * This must be a full URL.
 */
$server_url = "http://localhost/server/server.php";

/**
 * Initialize an OpenID store
 *
 * @return object $store an instance of OpenID store (see the
 * documentation for how to create one)
 */
function getOpenIDStore()
{
    require_once 'Auth/OpenID/MySQLStore.php';
    require_once 'DB.php';

    $dsn = array(
                 'phptype'  => 'mysql',
                 'username' => 'CampFire',
                 'password' => 'CampFire',
                 'hostspec' => 'localhost'
                 );

    $db =& DB::connect($dsn);

    if (PEAR::isError($db)) {
        return null;
    }

    $db->query("USE CampFire");
        
    $s =& new Auth_OpenID_MySQLStore($db);

    $s->createTables();

    return $s;
}

