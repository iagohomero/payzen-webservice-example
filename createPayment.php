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
$commonRequest = new commonRequest;
$commonRequest->paymentSource = 'EC';
$commonRequest->submissionDate = new DateTime('now', new DateTimeZone('UTC'));

$threeDSRequest = new threeDSRequest;
$threeDSRequest->mode = "ENABLED_CREATE";

$paymentRequest = new paymentRequest;
$paymentRequest->amount = "2990";
$paymentRequest->currency = "986";
$paymentRequest->manualValidation = '0';

$orderRequest = new orderRequest;
$orderRequest->orderId = "myOrder";

$cardRequest = new cardRequest;
$cardRequest->number = "4970100000000000";
$cardRequest->scheme = "VISA";
$cardRequest->expiryMonth = "12";
$cardRequest->expiryYear = "2023";
$cardRequest->cardSecurityCode = "123";
$cardRequest->cardHolderBirthDay = "2008-12-31";

$customerRequest = new customerRequest;
$customerRequest->billingDetails = new billingDetailsRequest;
$customerRequest->billingDetails->email = "test.payzen@gmail.com";

$customerRequest->extraDetails = new extraDetailsRequest;

$techRequest = new techRequest;

//	Chamada da operação createPayment	

try {
    $createPaymentRequest = new createPayment;
    $createPaymentRequest->commonRequest = $commonRequest;
    $createPaymentRequest->threeDSRequest =  $threeDSRequest;
    $createPaymentRequest->paymentRequest = $paymentRequest;
    $createPaymentRequest->orderRequest = $orderRequest;
    $createPaymentRequest->cardRequest = $cardRequest;
    $createPaymentRequest->customerRequest = $customerRequest;
    $createPaymentRequest->techRequest = $techRequest;

    $createPaymentRequest->commonRequest->submissionDate = $createPaymentRequest->commonRequest->submissionDate->format(dateTime::W3C);

    $createPaymentResponse = new createPaymentResponse();
    $createPaymentResponse = $client->createPayment($createPaymentRequest);
} catch (SoapFault $fault) {
    //Gerenciamento das exceções		
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

//Analise da resposta
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
    //Analise da resposta
    if ($createPaymentResponse->createPaymentResult->commonResponse->responseCode != "0") {
        //process error				
    } else {
        //Processo finalizado com sucesso					
        //Teste da presença do du transactionStatusLabel:
        if (isset($createPaymentResponse->createPaymentResult->commonResponse->transactionStatusLabel)) {
            //O cartão não é alistado ou 3DS Desativado																
            // O pagamento foi aceito	
            // O código abaixo deve ser modificado para integrar as atualizações de base de dados etc..
            switch ($createPaymentResponse->createPaymentResult->commonResponse->transactionStatusLabel) {
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
                    // O pagamento foi recusado							
                default:
                    echo "pagamento recusado";
                    break;
            }
        } else {
            // se ausente = a transação não é criada, portanto o cartão não é alistado
            // segue então a geração do formulário de redirecionamento 3DS

            //Deve-se resgatar o código de sessão para manter a sessão durante a análise da resposta do acs
            $cookie = getJsessionId($client);

            // Guardar o código de sessão no campo MD. Este campo será retornado idêntico pelo ACS
            $MD = setJsessionId($client) . "+" . $createPaymentResponse->createPaymentResult->threeDSResponse->authenticationRequestData->threeDSRequestId;

            //Inicializar os outros campos necessários ao redirecionamento para o ACS
            $threeDsAcsUrl = $createPaymentResponse->createPaymentResult->threeDSResponse->authenticationRequestData->threeDSAcsUrl;
            $threeDsEncodedPareq = $createPaymentResponse->createPaymentResult->threeDSResponse->authenticationRequestData->threeDSEncodedPareq;
            $threeDsServerResponseUrl = "http://127.0.0.1/webservices/ws-v5/retour3DS.php";

            //CUIDADO em modo TESTE, o código de sessão deve ser acrescentado à URL do ACS para manter a sessão HTTP
            $JSESSIONID = setJsessionId($client);
            if ($mode == "TEST") {
                $threeDsAcsUrl = $threeDsAcsUrl . ";jsessionid=" . $JSESSIONID;
            }
            formConstructor($threeDsAcsUrl, $MD, $threeDsEncodedPareq, $threeDsServerResponseUrl);
        }
    }
}
?>

<?php
include_once 'v5.php';     // Arquivo contendo a definição dos diferentes objetos 
include_once 'function.php'; // Arquivo contendo todas as funções úteis (geração do uuid, etc...)
include_once 'credentials.php'; // Arquivo contendo as credenciais 

//Resgate da resposta do ACS
//Encontramos o código de sessão no campo MD para manter a sessão HTTP
if (isset($_POST['MD']) and (isset($_POST['PaRes']))) {
    list($JSESSIONID, $threeDSRequestId) = explode("+", $_POST['MD']);

    //Deletar os espaços e os Enters da mensagemPaRes
    $pares = str_replace("\r\n", "", $_POST['PaRes'], $count);;
	
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
    $commonRequest = new commonRequest;
    $commonRequest->submissionDate = new DateTime('now', new DateTimeZone('UTC'));

    $threeDSRequest = new threeDSRequest;
    $threeDSRequest->mode = "ENABLED_FINALIZE";
    $threeDSRequest->requestId = $threeDSRequestId;
    $threeDSRequest->pares = $pares;

    $createPaymentRequest = new createPayment;
    $createPaymentRequest->commonRequest = $commonRequest;
    $createPaymentRequest->threeDSRequest =  $threeDSRequest;

    $createPaymentRequest->commonRequest->submissionDate = $createPaymentRequest->commonRequest->submissionDate->format(dateTime::W3C);

    try {
        //Manter a sessão HTTP
        $client->__setCookie('JSESSIONID', $JSESSIONID);

        //Chamada da operação createPayment
        $createPaymentResponse = new createPaymentResponse();
        $createPaymentResponse = $client->createPayment($createPaymentRequest);
    } catch (SoapFault $fault) {
        //gerenciamento das exceções	
        trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
    }

    /* 
		 *	Exibição dos logs XML para substituir por uma escritura em um arquivo de log.		 
		 */
    echo "<hr> [Request Header] <br/>", htmlspecialchars($client->__getLastRequestHeaders()), "<br/>";
    echo "<hr> [Request] <br/>", htmlspecialchars($client->__getLastRequest()), "<br/>";
    echo "<hr> [Response Header]<br/>", htmlspecialchars($client->__getLastResponseHeaders()), "<br/>";
    echo "<hr> [Response]<br/>", htmlspecialchars($client->__getLastResponse()), "<br/>";
    echo '<hr>';

    //Analise da resposta
    //Resgate do SOAP Header da resposta para armazenar os cabeçalhos em um quadro (aqui $responseHeader)	
    $dom = new DOMDocument;
    $dom->loadXML($client->__getLastResponse(), LIBXML_NOWARNING);
    $path = new DOMXPath($dom);
    $headers = $path->query('//*[local-name()="Header"]/*');
    $responseHeader = array();
    foreach ($headers as $headerItem) {
        $responseHeader[$headerItem->nodeName] = $headerItem->nodeValue;
    }

    //Cálculo da ficha de autenticação da resposta.

    $authTokenResponse = base64_encode(hash_hmac('sha256', $responseHeader['timestamp'] . $responseHeader['requestId'], $key, true));
    if ($authTokenResponse !== $responseHeader['authToken']) {

        //Erro de cálculo ou tentativa de fraude			
        echo 'Erro interno encontrado';
    } else {
        //Análise da resposta
        //Verificação do responseCode
        if ($createPaymentResponse->createPaymentResult->commonResponse->responseCode != "0") {

            //process error
            echo 'erro interno';
        } else {
            //Processo finalizado com sucesso					
            //teste da presença do du transactionStatusLabel:
            if (isset($createPaymentResponse->createPaymentResult->commonResponse->transactionStatusLabel)) {

                // O pagamento foi aceito	
                // O código abaixo deve ser modificado para integrar as atualizações de base de dados etc..						
                switch ($createPaymentResponse->createPaymentResult->commonResponse->transactionStatusLabel) {
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
                        // O pagamento foi recusado						
                    default:
                        echo "pagamento recusado";
                        break;
                }
            } else {
                echo 'erro interno';
            }
        }
    }
} else {
    //retorno do 3DS sem parâmetro ou acesso direto à página de retorno 3DS echo 'error';
}
?>