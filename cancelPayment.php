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
$queryRequest->uuid = "3ce802e3df34434c9f172c6dc02e9ae2";

//	 Chamada da operação cancelPayment		
try {
    $cancelPaymentRequest = new cancelPayment;
    $cancelPaymentRequest->commonRequest = $commonRequest;
    $cancelPaymentRequest->queryRequest = $queryRequest;

    $cancelPaymentResponse = $client->cancelPayment($cancelPaymentRequest);
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
    if ($cancelPaymentResponse->cancelPaymentResult->commonResponse->responseCode != "0") {
        //process error				
    } else {
        //Processo finalizado com sucesso					
        //Teste da presença do transactionStatusLabel:
        if (isset($cancelPaymentResponse->cancelPaymentResult->commonResponse->transactionStatusLabel)) {
            //O cartão não é alistado ou 3DS Desativado																
            // O pagamento foi aceito	
            // O código abaixo deve ser modificado para integrar as atualizações de base de dados etc..

            switch ($cancelPaymentResponse->cancelPaymentResult->commonResponse->transactionStatusLabel){
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
