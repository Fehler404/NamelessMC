<?php
define( 'MQ_SERVER_ADDR', $default_ip );
define( 'MQ_SERVER_PORT', $default_port );
define( 'MQ_TIMEOUT', 1 );
define( 'PRE', $pre17 );

require('inc/integration/status/MinecraftServerPing.php');

$Timer = MicroTime( true );

$Info = false;
$Query = null;

try
{
	$Query = new MinecraftPing( MQ_SERVER_ADDR, MQ_SERVER_PORT, MQ_TIMEOUT );
	if(PRE == 0){
		$Info = $Query->Query( );
	} else {
		$Info = $Query->QueryOldPre17( );
	}
}
catch( MinecraftPingException $e )
{
	$Exception = $e;
}

if( $Query !== null )
{
	$Query->Close( );
}

$Timer = Number_Format( MicroTime( true ) - $Timer, 4, '.', '' );

?>
