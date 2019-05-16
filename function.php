<?php
function getAuthToken($requestId, $timestamp, $key)
{
	$data = "";
	$data = $requestId . $timestamp;
	$authToken = hash_hmac("sha256", $data, $key, true);
	$authToken = base64_encode($authToken);
	//var_dump($authToken);
	return $authToken;
}

function gen_uuid()
{
	if (function_exists('random_bytes')) {
		// PHP 7
		$data = random_bytes(16);
	} elseif (function_exists('openssl_random_pseudo_bytes')) {
		// PHP 5.3, Open SSL required
		$data = openssl_random_pseudo_bytes(16);
	} else {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff)
		);
	}

	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 100
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6 & 7 to 10

	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function setHeaders($shopId, $requestId, $timestamp, $mode, $authToken, $key, $client)
{
	//Criação dos cabeçalhos shopId, requestId, timestamp, mode e authToken	
	$ns = 'http://v5.ws.vads.lyra.com/Header/';
	$headerShopId = new SOAPHeader($ns, 'shopId', $shopId);
	$headerRequestId = new SOAPHeader($ns, 'requestId', $requestId);
	$headerTimestamp = new SOAPHeader($ns, 'timestamp', $timestamp);
	$headerMode = new SOAPHeader($ns, 'mode', $mode);
	$authToken = getAuthToken($requestId, $timestamp, $key);

	$headerAuthToken = new SOAPHeader($ns, 'authToken', $authToken);
	//Adição dos cabeçalhos no SOAP Header	
	$headers = array(
		$headerShopId,
		$headerRequestId,
		$headerTimestamp,
		$headerMode,
		$headerAuthToken
	);

	$client->__setSoapHeaders($headers);
}

function setJsessionId($client)
{
	$cookie = $_SESSION['JSESSIONID'];
	$client->__setCookie('JSESSIONID', $cookie);
	return $cookie;
}

/**
 *  
 *
 * @param $client 
 * @return string $JSESSIONID
 */
function getJsessionId($client)
{
	//recuperação do cabeçalho da resposta
	$header = ($client->__getLastResponseHeaders());

	if (!preg_match("#JSESSIONID=([A-Za-z0-9\._]+)#", $header, $matches)) {
		return "Nenhum ID de Sessão Retornado."; //Este caso nunca deverá acontecer;
		die;
	}

	$JSESSIONID = $matches[1];
	$_SESSION['JSESSIONID'] = $JSESSIONID;
	//print_r($JSESSIONID);

	return $JSESSIONID;
}

function formConstructor($threeDsAcsUrl, $threeDSrequestId, $threeDsEncodedPareq, $threeDsServerResponseUrl)
{

	$msg = ('
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr"> 
<head> 
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/>
<title>3DS</title>
<script type="text/javascript">
<!--
     function submitForm(){
         document.redirectForm.submit();
     }
 -->
</script>
</head>

<body id="lyra" onLoad="setTimeout(\'submitForm()' . '\',500);">
<div id="container">
<div id="paymentSolutionInfo">
<div id="title">&nbsp;</div>
</div>

<hr class="ensureDivHeight"/>
<br/>
<br/>

<br/>
<br/>
<br/>
	<form name="redirectForm" action="' . $threeDsAcsUrl . '" method="POST">
		<input type="hidden" name="PaReq" value="' . $threeDsEncodedPareq . '"/>
		<input type="hidden" name="TermUrl" value="' . $threeDsServerResponseUrl . '"/>
		<input type="hidden" name="MD" value="' . $threeDSrequestId . '"/>
		
		<noscript><input type="submit" name="Go" value="Click to continue"/></noscript>
	</form>
	<div id="backToBoutiqueBlock"> </div> 
<div id="footer"> </div> 
</div>
</body>
</html>');

	echo $msg;
}
