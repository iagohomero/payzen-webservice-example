<?php
include_once 'v5.php';     // Arquivo contendo a definição dos diferentes objetos 
include_once 'function.php'; // Arquivo contendo todas as funções úteis (geração do uuid, etc...)
include_once 'credentials.php'; // Arquivo contendo as credenciais 

$client = new soapClient(
    $wsdl,
    $options = array(
        'trace' => 1,
        'exceptions' => 0,
        'encoding' => 'UTF-8',
        'soapaction' => ''
    )
);

//Geração do header
$requestId = gen_uuid();
$timestamp = gmdate("Y-m-d\TH:i:s\Z");
$authToken = base64_encode(hash_hmac('sha256', $requestId . $timestamp, $key, true));
setHeaders($shopId, $requestId, $timestamp, $mode, $authToken, $key, $client);

//Geração do body
$queryRequest = new queryRequest;
$queryRequest->uuid = "538881d2301b4a45aeff2ab51abcf947";

//	 Chamada da operação refundPayment		
try {
    $refundPaymentRequest = new refundPayment;
    $refundPaymentRequest->commonRequest = $commonRequest;
    $refundPaymentRequest->queryRequest = $queryRequest;
    
    $paymentRequest = new paymentRequest;
    $paymentRequest->amount = "2990";
    $paymentRequest->currency = "986";
    
    $refundPaymentRequest->paymentRequest = $paymentRequest;
    
    $refundPaymentResponse = $client->refundPayment($refundPaymentRequest);
} catch (SoapFault $fault) {
    ///Gestão das exceções
    trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
}

/* Exibição dos logs XML para substituir por uma escritura em um arquivo de log.	
*
* CUIDADO VOCÊ NÃO DEVE SALVAR OS NÚMEROS DE CARTÃO NOS SEUS LOGS
*/
echo "<hr> [Request Header] <br/>", htmlspecialchars($client->__getLastRequestHeaders()), "<br/>";
echo "<hr> [Request] <br/>", htmlspecialchars($client->__getLastRequest()), "<br/>";
echo "<hr> [Response Header]<br/>", htmlspecialchars($client->__getLastResponseHeaders()), "<br/>";
echo "<hr> [Response]<br/>", htmlspecialchars($client->__getLastResponse()), "<br/>";
echo '<hr>';
echo "<hr> [Response SOAP Headers]<br/>";

//Análise da resposta
//Resgate do SOAP Header da resposta para armazenar os cabeçalhos em um quadro (aqui $responseHeader)	
$dom = new DOMDocument;
$dom->loadXML($client->__getLastResponse(), LIBXML_NOWARNING);
$path = new DOMXPath($dom);
$headers = $path->query('//*[local-name()="Header"]/*');
$responseHeader = array();
foreach ($headers as $headerItem) {
    $responseHeader[$headerItem->nodeName] = $headerItem->nodeValue;
}

//Cálculo da ficha de autenticação da resposta				
$authTokenResponse = base64_encode(hash_hmac('sha256', $responseHeader['timestamp'] . $responseHeader['requestId'], $key, true));
if ($authTokenResponse !== $responseHeader['authToken']) {
    //Erro de cálculo ou tentativa de fraude			
    echo 'Erro interno encontrado';
} else {
    //Análise da resposta
    if ($refundPaymentResponse->refundPaymentResult->commonResponse->responseCode != "0") {
        //process error				
    } else {
        //Processo finalizado com sucesso					
        //Teste da presença do transactionStatusLabel:
        if (isset($refundPaymentResponse->refundPaymentResult->commonResponse->transactionStatusLabel)) {
            //O cartão não é alistado ou 3DS Desativado																
            // O pagamento foi aceito	
            // O código abaixo deve ser modificado para integrar as atualizações de base de dados etc..

            switch ($refundPaymentResponse->refundPaymentResult->commonResponse->transactionStatusLabel){
                case "AUTHORISED":
                    echo "pagamento aceito";
                    break;
                case "WAITING_AUTHORISATION":
                    echo "pagamento aceito";
                    break;
                case "AUTHORISED_TO_VALIDATE":
                    echo "pagamento aceito";
                    break;
                case "WAITING_AUTHORISATION_TO_VALIDATE":
                    echo "pagamento aceito";
                    break;
                    // O pagamento é recusado							
                default:
                    echo "pagamento recusado";
                    break;
            }
        }
    }
}
