<?php

require_once __DIR__ . '/vendor/autoload.php';

use Bigcommerce\Api\Client as Bigcommerce;
use Firebase\JWT\JWT;
use Guzzle\Http\Client;
use Handlebars\Handlebars;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Bigcommerce\Api\Connection;

/*// Load from .env file
$dotenv = new Dotenv\Dotenv('../web/');
$dotenv->load();
*/
$app = new Application();
$app['debug'] = true;
session_start();

$app->get('/load', function (Request $request) use ($app) {

	//$app['monolog']->addDebug('load'); 
	$data = verifySignedRequest($request->get('signed_payload'));
	if (empty($data)) {
		return 'Invalid signed_payload.';
	}
	$redis = new Predis\Client(getenv('REDIS_URL'));
	$key = getUserKey($data['store_hash'], $data['user']['email']);
	$user = json_decode($redis->get($key), true);
	if (empty($user)) {
		return 'Invalid user.';
	}

    $agile_domain_redis = $redis->get("agile_domain_".$data['store_hash']);
    $agile_email_redis = $redis->get("agile_email_".$data['store_hash']);
    $agile_rest_api_key_redis = $redis->get("agile_rest_api_key_".$data['store_hash']);
    $agile_sync_customers_redis = $redis->get("agile_sync_customers_".$data['store_hash']);
    $agile_sync_orders_redis = $redis->get("agile_sync_orders_".$data['store_hash']);
    $store_hash = $redis->get('store_hash_'.$data['store_hash']);
    
    header("Location: ../account_settings.php?store_hash=".$store_hash."&agile_domain=".$agile_domain_redis."&agile_email=".$agile_email_redis.'&agile_rest_api_key='.$agile_rest_api_key_redis.'&sync_customers='.$agile_sync_customers_redis.'&sync_orders='.$agile_sync_orders_redis);
    exit;
	/*$client_id = clientId();
	$access_token = getAuthToken($data['store_hash']);

	$connection = new Connection();

	$connection->verifyPeer(false);
	$connection->addHeader('X-Auth-Client', $client_id);
	$connection->addHeader('X-Auth-Token', $access_token);
	$connection->addHeader('Content-Type', 'application/json');
	$connection->addHeader('Accept', 'application/json');
	$response = $connection->get('https://api.bigcommerce.com/stores/'.$data['store_hash'].'/v2/hooks');
	return print_r($response);*/
	//return 'Welcome ' . json_encode($user);
	//return "<pre>".\Cowsayphp\Cow::say("Cool beans, your app is loaded")."</pre>";
});

$app->get('/auth/callback', function (Request $request) use ($app) {
	$redis = new Predis\Client(getenv('REDIS_URL'));

	$payload = array(
		'client_id' => clientId(),
		'client_secret' => clientSecret(),
		'redirect_uri' => callbackUrl(),
		'grant_type' => 'authorization_code',
		'code' => $request->get('code'),
		'scope' => $request->get('scope'),
		'context' => $request->get('context'),
	);

	$client = new Client(bcAuthService());
	$req = $client->post('/oauth2/token', array(), $payload, array(
		'exceptions' => false,
	));
	$resp = $req->send();

	if ($resp->getStatusCode() == 200) {
		$data = $resp->json();

		list($context, $storeHash) = explode('/', $data['context'], 2);
		$key = getUserKey($storeHash, $data['user']['email']);

		// Store the user data and auth data in our key-value store so we can fetch it later and make requests.
		$redis->set($key, json_encode($data['user'], true));
		$redis->set("stores/{$storeHash}/auth", json_encode($data));

		/*$agile_domain_redis = $redis->get("agile_domain");
    $agile_email_redis = $redis->get("agile_email");
    $agile_rest_api_key_redis = $redis->get("agile_rest_api_key");
    $agile_sync_customers_redis = $redis->get("agile_sync_customers");
    $agile_sync_orders_redis = $redis->get("agile_sync_orders");
    $store_hash = $storeHash;  */
    $_SESSION['storeHash'] = $storeHash;
    
    header("Location: ../account_settings.php");
    exit;
		//return 'Hello ' . json_encode($data);
	} else {
		return 'Something went wrong... [' . $resp->getStatusCode() . '] ' . $resp->getBody();
	}

});

$app->get('/agile_settings', function(Request $request) use($app){

  $redis = new Predis\Client(getenv('REDIS_URL'));

  $storeHash = $request->get('store_hash');
  $sync_customers = $request->get('sync_customers');
  $sync_orders = $request->get('sync_orders');

  $agile_domain = $request->get('agile_domain');
  $agile_email = $request->get('agile_email');
  $agile_rest_api_key = $request->get('agile_rest_api_key');

  $redis->set('agile_domain_'.$storeHash, '');
  $redis->set('agile_email_'.$storeHash, '');
  $redis->set('agile_rest_api_key_'.$storeHash, '');
  $redis->set('store_hash_'.$storeHash, ''); 

  $redis->set('agile_domain_'.$storeHash, $agile_domain);
  $redis->set('agile_email_'.$storeHash, $agile_email);
  $redis->set('agile_rest_api_key_'.$storeHash, $agile_rest_api_key);
  $redis->set('store_hash_'.$storeHash, $storeHash);  

  $client_id = clientId();
  $access_token = getAuthToken($storeHash);

  $connection = new Connection();

  $connection->verifyPeer(false);
  $connection->addHeader('X-Auth-Client', $client_id);
  $connection->addHeader('X-Auth-Token', $access_token);
  $connection->addHeader('Content-Type', 'application/json');
  $connection->addHeader('Accept', 'application/json');

  if(isset($sync_customers)){
    $redis->set('agile_sync_customers_'.$storeHash, 'yes');
    $response1 = $connection->post('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/hooks', json_encode(array(
            'scope'=>'store/customer/created',
            'destination'=>'https://agilecrm-bigcommerce.herokuapp.com/customer/created/'.$agile_domain.'/'.$agile_email.'/'.$agile_rest_api_key,
            'is_active' => true
        )));

    $response2 = $connection->post('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/hooks', json_encode(array(
            'scope'=>'store/customer/updated',
            'destination'=>'https://agilecrm-bigcommerce.herokuapp.com/customer/updated/'.$agile_domain.'/'.$agile_email.'/'.$agile_rest_api_key,
            'is_active' => true
        )));
  }
  else{
    $redis->set('agile_sync_customers_'.$storeHash, 'no');
  }

  if(isset($sync_orders)){
    $redis->set('agile_sync_orders_'.$storeHash, 'yes');
    $response3 = $connection->post('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/hooks', json_encode(array(
            'scope'=>'store/order/updated',
            'destination'=>'https://agilecrm-bigcommerce.herokuapp.com/order/updated/'.$agile_domain.'/'.$agile_email.'/'.$agile_rest_api_key,
            'is_active' => true
        )));

    $response4 = $connection->post('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/hooks', json_encode(array(
            'scope'=>'store/order/created',
            'destination'=>'https://agilecrm-bigcommerce.herokuapp.com/order/created/'.$agile_domain.'/'.$agile_email.'/'.$agile_rest_api_key,
            'is_active' => true
        ))); 
  }
  else{
     $redis->set('agile_sync_orders_'.$storeHash, 'no');
  }
  $agile_domain_redis = $redis->get("agile_domain_".$storeHash);
  $agile_email_redis = $redis->get("agile_email_".$storeHash);
  $agile_rest_api_key_redis = $redis->get("agile_rest_api_key_".$storeHash);
  $agile_sync_customers_redis = $redis->get("agile_sync_customers_".$storeHash);
  $agile_sync_orders_redis = $redis->get("agile_sync_orders_".$storeHash);
  $store_hash = $redis->get('store_hash_'.$storeHash);
 
  header("Location: ../account_settings.php?store_hash=".$store_hash."&agile_domain=".$agile_domain_redis."&agile_email=".$agile_email_redis.'&agile_rest_api_key='.$agile_rest_api_key_redis.'&sync_customers='.$agile_sync_customers_redis.'&sync_orders='.$agile_sync_orders_redis);
  exit;
  
  //$response = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/hooks');
  

});

$app->post('/customer/created/{agile_domain}/{agile_email}/{agile_rest_api_key}', function($agile_domain, $agile_email, $agile_rest_api_key) use($app){


    $fullPostData = file_get_contents('php://input');
    $data = json_decode($fullPostData,true);
    //var_dump($data);

    $customer_id = $data['data']['id'];

    list($context, $storeHash) = explode('/', $data['producer'], 2);
    $storeHash = strtolower($storeHash);
    $client_id = clientId();
    $access_token = getAuthToken($storeHash);

    $connection = new Connection();
    $connection->verifyPeer(false);
    $connection->addHeader('X-Auth-Client', $client_id);
    $connection->addHeader('X-Auth-Token', $access_token);
    $connection->addHeader('Content-Type', 'application/json');
    $connection->addHeader('Accept', 'application/json');

    $customer = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/customers/'.$customer_id);   
    $address = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/customers/'.$customer_id.'/addresses');

    $sync_address = array(
                      "address"=> $address[0]->street_2 == "" ? $address[0]->street_1 : $address[0]->street_1.','.$address[0]->street_2,
                      "city"=>$address[0]->city,
                      "state"=>$address[0]->state,
                      "country"=>$address[0]->country
                    );

    $contact_json = array(
          "tags"=>array("BigCommerce"),
          "properties"=>array(
            array(
              "name"=>"first_name",
              "value"=> $customer->first_name,
              "type"=>"SYSTEM",
            ),
            array(
              "name"=>"last_name",
              "value"=>$customer->last_name,
              "type"=>"SYSTEM"
            ),
            array(
              "name"=>"email",
              "value"=>$customer->email,
              "type"=>"SYSTEM"
            ),  
            array(
                "name"=>"address",
                "value"=>json_encode($sync_address),
                "type"=>"SYSTEM"
            ),
            array(
                "name"=>"phone",
                "value"=>$customer->phone,
                "type"=>"SYSTEM"
            ),
          )
        );
    
    $contact_json = json_encode($contact_json);
    $curln = curl_wrap("contacts", $contact_json, "POST", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);                  
    
    return "Success";

});

$app->post('/customer/updated/{agile_domain}/{agile_email}/{agile_rest_api_key}', function($agile_domain,$agile_email, $agile_rest_api_key) use($app){

    $fullPostData = file_get_contents('php://input');
    $data = json_decode($fullPostData,true);
    //var_dump($data);
    $customer_id = $data['data']['id'];

    list($context, $storeHash) = explode('/', $data['producer'], 2);
    $storeHash = strtolower($storeHash);
    $client_id = clientId();
    $access_token = getAuthToken($storeHash);

    $connection = new Connection();
    $connection->verifyPeer(false);
    $connection->addHeader('X-Auth-Client', $client_id);
    $connection->addHeader('X-Auth-Token', $access_token);
    $connection->addHeader('Content-Type', 'application/json');
    $connection->addHeader('Accept', 'application/json');

    $customer = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/customers/'.$customer_id);   
    $address = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/customers/'.$customer_id.'/addresses');

    $result = curl_wrap("contacts/search/email/".$customer->email, null, "GET", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);
    $result = json_decode($result, false, 512, JSON_BIGINT_AS_STRING);

    if($result){
    	if(count($result)>0)
	        $contact_id = $result->id;
	    else
	        $contact_id = "";
    }

    $sync_address = array(
                      "address"=> $address[0]->street_2 == "" ? $address[0]->street_1 : $address[0]->street_1.','.$address[0]->street_2,
                      "city"=>$address[0]->city,
                      "state"=>$address[0]->state,
                      "country"=>$address[0]->country
                    );

    $contact_json = array(
    	  "id" => $contact_id,
          "tags"=>array("BigCommerce"),
          "properties"=>array(
            array(
              "name"=>"first_name",
              "value"=>$customer->first_name,
              "type"=>"SYSTEM",
            ),
            array(
              "name"=>"last_name",
              "value"=>$customer->last_name,
              "type"=>"SYSTEM"
            ),
            array(
              "name"=>"email",
              "value"=>$customer->email,
              "type"=>"SYSTEM"
            ),  
            array(
                "name"=>"address",
                "value"=>json_encode($sync_address),
                "type"=>"SYSTEM"
            ),
            array(
                "name"=>"phone",
                "value"=>$customer->phone,
                "type"=>"SYSTEM"
            ),
          )
        );
    
    $contact_json = json_encode($contact_json);
    $curlupdate = curl_wrap("contacts/edit-properties", $contact_json, "PUT", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);               
    error_log(print_r($curlupdate,true));
    return "Success";

});

$app->post('order/created/{agile_domain}/{agile_email}/{agile_rest_api_key}', function($agile_domain, $agile_email, $agile_rest_api_key) use($app){

    $fullPostData = file_get_contents('php://input');
    $data = json_decode($fullPostData,true);
    //var_dump($data);
    $order_id = $data['data']['id'];

    list($context, $storeHash) = explode('/', $data['producer'], 2);
    $storeHash = strtolower($storeHash);
    $client_id = clientId();
    $access_token = getAuthToken($storeHash);

    $connection = new Connection();
    $connection->verifyPeer(false);
    $connection->addHeader('X-Auth-Client', $client_id);
    $connection->addHeader('X-Auth-Token', $access_token);
    $connection->addHeader('Content-Type', 'application/json');
    $connection->addHeader('Accept', 'application/json');
    
    $order = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/orders/'.$order_id);  
    $customer = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/customers/'.$order->customer_id);  
    $products = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/orders/'.$order_id.'/products'); 

    $result = curl_wrap("contacts/search/email/".$customer->email, null, "GET", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);
    $result = json_decode($result, false, 512, JSON_BIGINT_AS_STRING);

    if(count($result)>0){

        $contact_id = $result->id;
        $productname = array();
                    
        $street = $order->billing_address->street_2 == '' ? $order->billing_address->street_1 : $order->billing_address->street_1.','.$order->billing_address->street_2;
        $city = $order->billing_address->city;
        $state = $order->billing_address->state;
        $country = $order->billing_address->country;

        foreach ($products as $product_item) {
            $productname[] = fn_js_escape($product_item->name);
        }
        $noteproductname = implode(',',$productname);
        $productname = implode('","',$productname);
        $Str = $productname;
        $Str = preg_replace('/[^a-zA-Z0-9_.]/', '_', $Str);
        
        if($Str[0]=="_"){
          $Str = ltrim($Str, "_");
        }

        $contact_json = array(
            "id" => $contact_id, 
           "tags" => array($Str)
        );
        error_log(print_r($contact_json,true));
       $contact_json = stripslashes(json_encode($contact_json));
       
       $curltags = curl_wrap("contacts/edit/tags", $contact_json, "PUT", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);

  
       $billingaddress = $street.",".$city.",".$state.",".$country;
       $grandtotal = $order->total_inc_tax;
       $orderid = $order_id;         
    
        $note_json = array(
          "subject"=> "Order# ". $orderid ,
          "description"=>"Order status: ".$order->status."\nTotal amount:".$grandtotal."\nItems(id-qty):".$noteproductname."\nBilling:".$billingaddress,
          "contact_ids"=>array($contact_id)
        );

        $note_json = json_encode($note_json);
        $curls = curl_wrap("notes", $note_json, "POST", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);
    }

    return "success";


});

$app->post('order/updated/{agile_domain}/{agile_email}/{agile_rest_api_key}', function($agile_domain, $agile_email, $agile_rest_api_key) use($app){

    $fullPostData = file_get_contents('php://input');
    $data = json_decode($fullPostData,true);
    //var_dump($data);
    $order_id = $data['data']['id'];
    error_log(print_r($fullPostData,true));
    list($context, $storeHash) = explode('/', $data['producer'], 2);
    $storeHash = strtolower($storeHash);
    $client_id = clientId();
    $access_token = getAuthToken($storeHash);

    $connection = new Connection();
    $connection->verifyPeer(false);
    $connection->addHeader('X-Auth-Client', $client_id);
    $connection->addHeader('X-Auth-Token', $access_token);
    $connection->addHeader('Content-Type', 'application/json');
    $connection->addHeader('Accept', 'application/json');
    error_log($access_token);
    $order = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/orders/'.$order_id);  
    $customer = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/customers/'.$order->customer_id);  
    $products = $connection->get('https://api.bigcommerce.com/stores/'.$storeHash.'/v2/orders/'.$order_id.'/products'); 

    $result = curl_wrap("contacts/search/email/".$customer->email, null, "GET", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);
    $result = json_decode($result, false, 512, JSON_BIGINT_AS_STRING);

    if(count($result)>0){

        $contact_id = $result->id;
        $productname = array();
                    
        $street = $order->billing_address->street_2 == '' ? $order->billing_address->street_1 : $order->billing_address->street_1.','.$order->billing_address->street_2;
        $city = $order->billing_address->city;
        $state = $order->billing_address->state;
        $country = $order->billing_address->country;

        foreach ($products as $product_item) {
            $productname[] = fn_js_escape($product_item->name);

        }
        $noteproductname = implode(',',$productname);
        $productname = implode('","',$productname);
        $Str = $productname;
        $Str = preg_replace('/[^a-zA-Z0-9_.]/', '_', $Str);
        
        if($Str[0]=="_"){
          $Str = ltrim($Str, "_");
        }
            
        $contact_json = array(
            "id" => $contact_id, 
           "tags" => array($Str)
        );
       error_log(print_r($Str,true));
       $contact_json = stripslashes(json_encode($contact_json));
       $curltags = curl_wrap("contacts/edit/tags", $contact_json, "PUT", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);

       $billingaddress = $street.",".$city.",".$state.",".$country;
       $grandtotal = $order->total_inc_tax;
       $orderid = $order_id;         
    
        $note_json = array(
          "subject"=> "Order# ". $orderid ,
          "description"=>"Order status: ".$order->status."\nTotal amount:".$grandtotal."\nItems(id-qty):".$noteproductname."\nBilling:".$billingaddress,
          "contact_ids"=>array($contact_id)
        );

        $note_json = json_encode($note_json);
        $curls = curl_wrap("notes", $note_json, "POST", "application/json", $agile_domain, $agile_email, $agile_rest_api_key);
    }

    return "success";


});

function curl_wrap($entity, $data, $method, $content_type, $agile_domain=null, $agile_email=null, $agile_rest_api_key=null) {

        if ($content_type == NULL) {
            $content_type = "application/json";
        }
       
        $agile_url = "https://" . $agile_domain . ".agilecrm.com/dev/api/" . $entity;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_UNRESTRICTED_AUTH, true);
        switch ($method) {
            case "POST":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case "GET":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                break;
            case "PUT":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;
            case "DELETE":
                $url = $agile_url;
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                break;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-type : $content_type;", 'Accept : application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $agile_email . ':' . $agile_rest_api_key);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

// Our web handlers
/*$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
 return 'Welcome to Root! I got nothing.';
});
*/
$app->get('/cowsay', function() use($app) {
  $app['monolog']->addDebug('cowsay');
  return "<pre>".\Cowsayphp\Cow::say("Cool beans")."</pre>";
});

/**
 * Configure the static BigCommerce API client with the authorized app's auth token, the client ID from the environment
 * and the store's hash as provided.
 * @param string $storeHash Store hash to point the BigCommece API to for outgoing requests.
 */
function configureBCApi($storeHash)
{
	Bigcommerce::configure(array(
		'client_id' => clientId(),
		'auth_token' => getAuthToken($storeHash),
		'store_hash' => $storeHash
	));
}

/**
 * @param string $storeHash store's hash that we want the access token for
 * @return string the oauth Access (aka Auth) Token to use in API requests.
 */
function getAuthToken($storeHash)
{
	$redis = new Predis\Client(getenv('REDIS_URL'));
	$authData = json_decode($redis->get("stores/{$storeHash}/auth"));
	return $authData->access_token;
}

/**
 * @param string $jwtToken 	customer's JWT token sent from the storefront.
 * @return string customer's ID decoded and verified
 */
function getCustomerIdFromToken($jwtToken)
{
	$signedData = JWT::decode($jwtToken, clientSecret(), array('HS256', 'HS384', 'HS512', 'RS256'));
	return $signedData->customer->id;
}

/**
 * This is used by the `GET /load` endpoint to load the app in the BigCommerce control panel
 * @param string $signedRequest Pull signed data to verify it.
 * @return array|null null if bad request, array of data otherwise
 */
function verifySignedRequest($signedRequest)
{
	list($encodedData, $encodedSignature) = explode('.', $signedRequest, 2);

	// decode the data
	$signature = base64_decode($encodedSignature);
	$jsonStr = base64_decode($encodedData);
	$data = json_decode($jsonStr, true);

	// confirm the signature
	$expectedSignature = hash_hmac('sha256', $jsonStr, clientSecret(), $raw = false);
	if (!hash_equals($expectedSignature, $signature)) {
		error_log('Bad signed request from BigCommerce!');
		return null;
	}
	return $data;
}

/**
 * @return string Get the app's client ID from the environment vars
 */
function clientId()
{
	$clientId = getenv('BC_CLIENT_ID');
	return $clientId ?: '';
}

/**
 * @return string Get the app's client secret from the environment vars
 */
function clientSecret()
{
	$clientSecret = getenv('BC_CLIENT_SECRET');
	return $clientSecret ?: '';
}

/**
 * @return string Get the callback URL from the environment vars
 */
function callbackUrl()
{
	$callbackUrl = getenv('BC_CALLBACK_URL');
	return $callbackUrl ?: '';
}

/**
 * @return string Get auth service URL from the environment vars
 */
function bcAuthService()
{
	$bcAuthService = getenv('BC_AUTH_SERVICE');
	return $bcAuthService ?: '';
}

function getUserKey($storeHash, $email)
{
	return "kitty.php:$storeHash:$email";
}

function fn_js_escape($str)
{
    return strtr($str, array('\\' => '\\\\',  "'" => "\\'", '"' => '\\"', "\r" => '\\r', "\n" => '\\n', "\t" => '\\t', '</' => '<\/', "/" => '\\/'));
}

$app->run();
