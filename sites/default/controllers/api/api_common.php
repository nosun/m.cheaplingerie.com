<?php

function PHPRPC_Authentication($authstatus, $msg="")
{
	$tmp = array("PHPRPC_Authentication"=>$authstatus, "PHPRPC_Authentication_Message"=>$msg);
	return json_encode($tmp);
}

?>