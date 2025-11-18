<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Auth-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS');

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;
use App\Models\DB;

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new BasePathMiddleware($app));
$app->addErrorMiddleware(true, true, true);

$app->get('/', function (Request $request, Response $response) {
   $response->getBody()->write('Hello World!');
   return $response;
});

$app->get('/gasstations/{gasStationID}/prices/{fuelTypeID}', function (Request $request, Response $response, array $args) {
    $gasStationID = $args['gasStationID'];
    $fuelTypeID = $args['fuelTypeID'];
    $sql = "SELECT * FROM pricedata WHERE gasStationID = :gasStationID AND fuelTypeID = :fuelTypeID";

    try {
        $db = new Db();
        $conn = $db->connect();
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":gasStationID", $gasStationID);
        $stmt->bindParam(":fuelTypeID", $fuelTypeID);
        $stmt->execute();
        $prices = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;

        $response->getBody()->write(json_encode($prices));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    } catch (PDOException $e) {
        $error = array(
            "message" => $e->getMessage()
        );
        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

$app->get('/prices-summary/{fuelTypeID}', function (Request $request, Response $response, array $args) {
    $fuelTypeID = $args['fuelTypeID'];
    $sql = "SELECT COUNT(DISTINCT gasStationID) AS gasStationsCount,
                   MAX(fuelPrice) AS maxPrice,
                   MIN(fuelPrice) AS minPrice,
                   cast(AVG(fuelPrice) AS decimal(6,3)) AS avgPrice
            FROM pricedata
            WHERE fuelTypeID = :fuelTypeID";

    try {
        $db = new Db();
        $conn = $db->connect();
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":fuelTypeID", $fuelTypeID);
        $stmt->execute();
        $pricesSummary = $stmt->fetch(PDO::FETCH_ASSOC);
        $db = null;

        $response->getBody()->write(json_encode($pricesSummary, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    } catch (PDOException $e) {
        $error = array(
            "message" => $e->getMessage()
        );

        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});
       
$app->get('/gasstations/{gasStationID}/prices', function (Request $request, Response $response, array $args) {
    $gasStationID = $args['gasStationID'];
    $sql = "SELECT * FROM pricedata WHERE gasStationID = :gasStationID";
   
    try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':gasStationID', $gasStationID, PDO::PARAM_INT);
      $stmt->execute();
      $priceList = $stmt->fetchAll(PDO::FETCH_OBJ);
      $db = null;
     
      $response->getBody()->write(json_encode($priceList));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(200);
    } catch (PDOException $e) {
      $error = array(
        "message" => $e->getMessage()
      );
   
      $response->getBody()->write(json_encode($error));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(500);
    }
});

use Firebase\JWT\JWT;

class CustomResponse extends \Slim\Psr7\Response {
  public function withJson($data, $status = 200, $encodingOptions = 0) {
      $response = $this->withHeader('Content-Type', 'application/json');
      $response->getBody()->write(json_encode($data, $encodingOptions));
      return $response->withStatus($status);
  }
}

$app->post('/login', function (Request $request, Response $response) {
  $data = $request->getParsedBody();
  $username = $data['username'];
  $password = $data['password'];

  $sql = "SELECT * FROM users WHERE username = :username AND password = :password";

  $db = new Db();
  $conn = $db->connect();
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':username', $username, PDO::PARAM_STR);
  $stmt->bindParam(':password', $password, PDO::PARAM_STR);
  $stmt->execute();
  $user = $stmt->fetch(PDO::FETCH_OBJ);

  // Check if the user is a gas station owner
  $sql = "SELECT * FROM gasstations WHERE username = :username";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':username', $username, PDO::PARAM_STR);
  $stmt->execute();
  $gasStation = $stmt->fetch(PDO::FETCH_OBJ);

  $db = null;

  if (!$user) {
      $error = array(
          "message" => "Τα στοιχεία σας δεν είναι έγκυρα."
      );
      $response = $response->withHeader('content-type', 'application/json')
          ->withStatus(401);
      $response->getBody()->write(json_encode($error));
      return $response;
  }

  // Generate JWT token
  $payload = array(
      "username" => $user->username,
      "password" => $user->password,
      "isGasStationOwner" => $gasStation ? true : false
  );
  $jwt = JWT::encode($payload, "your_secret_key", 'HS256');

  // Set the user variable and return it as part of the response
  $response_data = array(
      "user" => $user,
      "token" => $jwt,
      "payload" => $payload // Add this line to include the payload in the response
  );
  $response = $response->withHeader('content-type', 'application/json')
      ->withStatus(200);
  $response->getBody()->write(json_encode($response_data));
  return $response;
});



$app->post('/orders', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
  
    // Make sure required fields are provided
    if (!isset($data['productID']) || !isset($data['username']) || !isset($data['quantity'])) {
      $error = array(
        "message" => "Missing required fields"
      );
     
      $response->getBody()->write(json_encode($error));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(400);
    }
  
    // Insert order data into database
    $sql = "INSERT INTO orders (productID, username, quantity) VALUES (:productID, :username, :quantity)";
    
    try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':productID', $data['productID']);
      $stmt->bindParam(':username', $data['username']);
      $stmt->bindParam(':quantity', $data['quantity']);
      $stmt->execute();
      $db = null;
  
      $response->getBody()->write(json_encode(array("message" => "Order created successfully")));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(201);
    } catch (PDOException $e) {
      $error = array(
        "message" => $e->getMessage()
      );
     
      $response->getBody()->write(json_encode($error));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(500);
    }
  });

  $app->get('/orders/{gasStationID}', function (Request $request, Response $response, $args) {
    $gasStationID = $args['gasStationID'];
   
    $sql = "SELECT orders.orderID, orders.quantity, pricedata.fuelName, pricedata.fuelPrice, users.email 
            FROM orders
            JOIN pricedata ON orders.productID = pricedata.productID
            JOIN users ON orders.username = users.username
            WHERE pricedata.gasStationID = :gasStationID
            ORDER BY orders.orderID DESC";
   
    try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':gasStationID', $gasStationID);
      $stmt->execute();
      $orders = $stmt->fetchAll(PDO::FETCH_OBJ);
      $db = null;
     
      $response->getBody()->write(json_encode($orders));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(200);
    } catch (PDOException $e) {
      $error = array(
        "message" => $e->getMessage()
      );
   
      $response->getBody()->write(json_encode($error));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(500);
    }
   });

   $app->put('/pricedata/{productID}', function (Request $request, Response $response, $args) {
    $productID = $args['productID'];
    $body = $request->getParsedBody();
   
    $fuelPrice = $body['fuelPrice'];
   
    $sql = "UPDATE pricedata SET fuelPrice = :fuelPrice WHERE productID = :productID";
   
    try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':fuelPrice', $fuelPrice);
      $stmt->bindParam(':productID', $productID);
      $stmt->execute();
      $db = null;
     
      $response->getBody()->write('Fuel price updated successfully');
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(200);
    } catch (PDOException $e) {
      $error = array(
        "message" => $e->getMessage()
      );
   
      $response->getBody()->write(json_encode($error));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(500);
    }
   });

   $app->delete('/orders/{orderID}', function (Request $request, Response $response, $args) {
    $orderID = $args['orderID'];
    
    try {
        $db = new Db();
        $conn = $db->connect();

        // Check if the order exists
        $sql = "SELECT orderID FROM orders WHERE orderID = :orderID";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['orderID' => $orderID]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $response->getBody()->write(json_encode(['error' => 'Order not found']));
            return $response->withHeader('content-type', 'application/json')->withStatus(404);
        }

        // Delete the order
        $sql = "DELETE FROM orders WHERE orderID = :orderID";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['orderID' => $orderID]);

        $response->getBody()->write(json_encode(['success' => 'Order deleted']));
        return $response->withHeader('content-type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
        return $response->withHeader('content-type', 'application/json')->withStatus(500);
    }
});

$app->get('/gasstations', function($request, $response) {
  $sql = "SELECT gasstations.*, users.username 
          FROM gasstations 
          JOIN users ON gasstations.username = users.username";
  try {
      $db = new db();
      $db = $db->connect();
      $stmt = $db->query($sql);
      $gasstations = $stmt->fetchAll(PDO::FETCH_OBJ);
      $db = null;
      $response->getBody()->write(json_encode($gasstations));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  } catch(PDOException $e) {
      echo '{"error": {"text": '.$e->getMessage().'}}';
  }
});

$app->get('/gasstations/prices/{fuelTypeID}', function (Request $request, Response $response, array $args) {
  $fuelTypeID = $args['fuelTypeID'];
  $sql = "SELECT p.*, g.gasStationLat, g.gasStationLong, g.gasStationOwner, g.gasStationAddress, g.ddNormalName, g.fuelCompNormalName
          FROM pricedata p
          JOIN gasstations g ON p.gasStationID = g.gasStationID
          WHERE p.fuelTypeID = :fuelTypeID";

  try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(":fuelTypeID", $fuelTypeID);
      $stmt->execute();
      $prices = $stmt->fetchAll(PDO::FETCH_OBJ);
      $db = null;

      $response->getBody()->write(json_encode($prices));
      return $response
          ->withHeader('content-type', 'application/json')
          ->withStatus(200);
  } catch (PDOException $e) {
      $error = array(
          "message" => $e->getMessage()
      );
      $response->getBody()->write(json_encode($error));
      return $response
          ->withHeader('content-type', 'application/json')
          ->withStatus(500);
  }
});

$app->get('/gasstation/{username}', function (Request $request, Response $response, $args) {
  $username = $args['username'];
 
  $sql = "SELECT gasStationID FROM gasstations WHERE username = :username";
 
  try {
    $db = new Db();
    $conn = $db->connect();
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $db = null;
   
    if (!$result) {
      $error = array(
        "message" => "No gas station found for the provided username"
      );
      $response->getBody()->write(json_encode($error));
      return $response
        ->withHeader('content-type', 'application/json')
        ->withStatus(404);
    }
   
    $response->getBody()->write(json_encode($result));
    return $response
      ->withHeader('content-type', 'application/json')
      ->withStatus(200);
  } catch (PDOException $e) {
    $error = array(
      "message" => $e->getMessage()
    );
 
    $response->getBody()->write(json_encode($error));
    return $response
      ->withHeader('content-type', 'application/json')
      ->withStatus(500);
  }
});

$app->get('/products/{username}', function (Request $request, Response $response, $args) {
  $username = $args['username'];

  $sql = "SELECT pricedata.productID, pricedata.fuelName, pricedata.fuelPrice FROM pricedata INNER JOIN gasstations ON pricedata.gasStationID = gasstations.gasStationID WHERE gasstations.username = :username";

  try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':username', $username);
      $stmt->execute();
      $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $db = null;

      $response->getBody()->write(json_encode($products));
      return $response
          ->withHeader('content-type', 'application/json')
          ->withStatus(200);
  } catch (PDOException $e) {
      $error = array(
          "message" => $e->getMessage()
      );

      $response->getBody()->write(json_encode($error));
      return $response
          ->withHeader('content-type', 'application/json')
          ->withStatus(500);
  }
});

// Handle OPTIONS request
$app->options('/pricedata/{id}', function ($request, $response, $args) {
  $response = $response->withHeader('Access-Control-Allow-Origin', '*')
                       ->withHeader('Access-Control-Allow-Methods', 'PUT')
                       ->withHeader('Access-Control-Allow-Headers', 'Content-Type');
  return $response;
});

$app->run();