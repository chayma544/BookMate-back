<?php 

header('Content-Type: application/json'); // Force JSON response
error_reporting(E_ALL); // Show all errors
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *"); 
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['user_id'])) {
                error_log("Received user_id: " . $_GET['user_id']); // Debug log
                $stmt = $pdo->prepare("SELECT * FROM user WHERE user_id = ?");
                $stmt->execute([$_GET['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Fetched user: " . json_encode($user)); // Debug log
                if ($user) {
                    echo json_encode($user);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($users);
            }
            break;

        case 'POST':
            // Parse incoming JSON data
            $requestData = json_decode(file_get_contents("php://input"), true);

            // Validate required fields for adding a user
            if (empty($requestData['FirstName']) || empty($requestData['LastName']) || empty($requestData['email'])) {
                http_response_code(400);
                echo json_encode(['error' => 'First name, last name, and email are required']);
                break;
            }

            // Check if email already exists
            $emailCheck = $pdo->prepare("SELECT 1 FROM profil WHERE email = ?");
            $emailCheck->execute([$requestData['email']]);

            if ($emailCheck->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Email already exists']);
                break;
            }

            // Insert new user into database
            $stmt = $pdo->prepare("
                INSERT INTO user (FirstName, LastName, age, address, user_swap_score) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $requestData['FirstName'],
                $requestData['LastName'],
                $requestData['age'] ?? null,
                $requestData['address'] ?? null,
                0 // Default swap score
            ]);

            $userId = $pdo->lastInsertId();

            // Insert profile data for the new user
            $profileStmt = $pdo->prepare("
                INSERT INTO profil (user_id, email, password) 
                VALUES (?, ?, ?)
            ");
            $profileStmt->execute([
                $userId,
                $requestData['email'],
                password_hash($requestData['password'], PASSWORD_DEFAULT) // Password hashing
            ]);

            http_response_code(201);
            echo json_encode([
                'id' => $userId,
                'message' => 'User added successfully'
            ]);
            break;

            case 'PUT': //  If someone sends a request to update a user (PUT = change)
    
                //  Grab the data the user sent in the body of the request (it's in JSON format)
                $requestData = json_decode(file_get_contents("php://input"), true);
            
                //  Make sure they told us WHO they want to update (must include user_id)
                if (empty($requestData['user_id'])) {
                    http_response_code(400); // 400 = Bad Request
                    echo json_encode(['error' => 'User ID is required']);
                    break;
                }
            
                //  Look in the database to see if this user exists
                $checkUser = $pdo->prepare("SELECT * FROM user WHERE `user_id` = ?");
                $checkUser->execute([$requestData['user_id']]);
                $currentData = $checkUser->fetch(PDO::FETCH_ASSOC);
            
                //  If the user doesn’t exist, tell them
                if (!$currentData) {
                    http_response_code(404); // 404 = Not Found
                    echo json_encode(['error' => 'User not found']);
                    break;
                }
            
                //  Combine the old user info with the new changes they gave us
                $mergedData = array_merge($currentData, $requestData);
            
                //  We're going to build the update sentence dynamically
                $updateFields = []; // This will hold things like: "FirstName = ?"
                $params = [];       // This will hold the values like: "Lina", "Tunis", etc.
            
                //  Only allow these fields to be updated (for safety)
                $allowedFields = [
                    'FirstName', 
                    'LastName', 
                    'age', 
                    'address'
                ];
            
                //  Loop through allowed fields and see if any were provided in the request
                foreach ($allowedFields as $field) {
                    if (isset($requestData[$field])) {
                        $updateFields[] = "`$field` = ?";     // Add the field to the update list
                        $params[] = $requestData[$field];     // Add the value to use
                    }
                }
            
                //  If the person didn’t send any valid fields to update, reject it
                if (empty($updateFields)) {
                    http_response_code(400); // 400 = Bad Request
                    echo json_encode(['error' => 'No valid fields provided for update']);
                    break;
                }
            
                //  Add user_id at the end so we can tell the DB which user to update
                $params[] = $requestData['user_id'];
            
                //  Build the final update query like: UPDATE user SET FirstName = ?, age = ? WHERE user_id = ?
                $query = "UPDATE user SET " . implode(', ', $updateFields) . " WHERE `user_id` = ?";
                $stmt = $pdo->prepare($query); // Prepare the SQL query
                $stmt->execute($params);       // Execute with all the values
            
                //  If at least one row was changed, success!
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'User updated successfully']);
                } else {
                    //  Otherwise, maybe the data was the same as before
                    http_response_code(404);
                    echo json_encode(['error' => 'No changes made']);
                }
            
                break;
            

        case 'DELETE':
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                break;
            }

            // Delete user profile first
            $profileStmt = $pdo->prepare("DELETE FROM profil WHERE user_id = ?");
            $profileStmt->execute([$_GET['id']]);

            // Delete the user record
            $stmt = $pdo->prepare("DELETE FROM user WHERE user_id = ?");
            $stmt->execute([$_GET['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'User removed successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}

function addSwapScore($userId) {
    global $pdo;
    // Increase the user's swap score by 1 each time a swap is made
    $stmt = $pdo->prepare("UPDATE user SET user_swap_score = user_swap_score + 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}
?>
