<?php 

// Start session at the beginning before any output
session_start();

define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');
header("Access-Control-Allow-Origin: https://localhost:4200"); // SPECIFIC domain
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // Cache preflight for 24h --> 86400 minutes

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php'; // Include JWT configuration


// input sanitization
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Handle preflight requests here's an example:

    //1.A frontend app (example.com) is trying to call an API hosted at (api.example.com).
    //2.The browser sends an OPTIONS request first to verify permissions.
    //3.This PHP code intercepts the request and allows it by returning a 200 status.
    //4.If the preflight request is successful, the browser proceeds with the actual request (e.g., POST, GET).


// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// JWT Authentication Check (for all methods except GET)
// added after auth
$method = $_SERVER['REQUEST_METHOD'];
$requiresAuth = $method !== 'GET'; // Only require auth for POST, PUT, DELETE

if ($requiresAuth) {
    //Extracts the JWT from the Authorization: Bearer <token> header.
    //Why? Standard way to pass tokens in HTTP requests
   // $token = getBearerToken();
    //Verifies the token's signature using the secret key from jwt.php.
   // $decoded = validateJWT($token);
    
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Invalid or missing token']);
        exit();
    }
    
    // Store user ID from token for later use
    $current_user_id = $decoded->sub;

    // Session-based rate limiting
    $rateLimitKey = 'book_api_'.($current_user_id ?? 'guest');
    $currentTime = time();
    
    // Initialize or clean up rate limit data if needed
    if (!isset($_SESSION['rate_limits'][$rateLimitKey]) || 
        $_SESSION['rate_limits'][$rateLimitKey]['reset_time'] < $currentTime) {
        
        // Set up new rate limit window
        $_SESSION['rate_limits'][$rateLimitKey] = [
            'count' => 1,
            'reset_time' => $currentTime + 60 // 60 seconds window
        ];
    } else {
        // Increment request count
        $_SESSION['rate_limits'][$rateLimitKey]['count']++;
        
        // Check if rate limit exceeded
        if ($_SESSION['rate_limits'][$rateLimitKey]['count'] > 100) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Too many requests',
                'retry_after' => $_SESSION['rate_limits'][$rateLimitKey]['reset_time'] - $currentTime
            ]);
            exit();
        }
    }
}




function normalizeBook(array $book): array {
    return [
        'id' => $book['book_id'],
        'title' => $book['title'],
        'author' => $book['author_name'],
        'language' => $book['language'],
        'genre' => $book['genre'],
        'releaseDate' => $book['release_date'],
        'status' => $book['status'],
        'coverImage' => $book['URL'],
        'availability' => (bool) $book['availability'],
        'userId' => $book['user_id']
    ];
}

try {
    switch ($method) {
        /*case 'GET':
            if (isset($_GET['user_id'])) {
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE user_id = ?");
                $stmt->execute([$_GET['user_id']]);
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($books);
            } 
            elseif (isset($_GET['book_id'])) {
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE `book_id` = ?");
                $stmt->execute([$_GET['book_id']]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                //$stmt is an object that holds the prepared SQL query.
                //The structure of the query never changes, only the values do.


                //fetch() gets one row at a time.


                //PDO::FETCH_ASSOC ensures the result is an associative array (["column_name" => value]) else returns false

                //The :: operator is used in four main cases:

                //1-echo MathHelper::add(5, 10);-->access methods
                //2-echo Config::SITE_NAME; -->SITE_NAME is a variable containing a value(output : the value of this variable)
                //3-return parent::makeSound()-->calling parent methods
                //4-$object->method(); VS ClassName::method();  ===>PDO is a classname(php data objects)

                if ($book) {
                    echo json_encode($book);
                    //json_encode() pour convertir le tableau associatif en format json
                } else {
                    http_response_code(204);
                    //On envoie un code d'erreur HTTP 204 (book Not Found)
                    echo json_encode(['error' => 'Book not found']);
                    //Cela permet au client de comprendre que la requête a échoué.
                }
            } 
            // Search books with filters
            elseif (isset($_GET['title']) || isset($_GET['genre']) || isset($_GET['author'])) {


                //permet d'éviter les erreurs et de gérer le cas où un paramètre n'est pas fourni
                

                $title = isset($_GET['title']) ? "%{$_GET['title']}%" : "%";
                //commence ou se termine par le titre IF WE DON'T SPECIFY WE GET ALL THE TITLES
                $genre = isset($_GET['genre']) ? $_GET['genre'] : "%";
                //commence par le genre
                $author = isset($_GET['author']) ? "%{$_GET['author']}%" : "%";
                //se termine par le nom de l'auteur



                
                $stmt = $pdo->prepare("
                    SELECT * FROM livre 
                    WHERE (title LIKE :title AND author_name LIKE :author
                    AND genre LIKE :genre
                    AND availability = 'available')
                ");
                
                //the prepared sql query is in $stmt


                //form of an associative array

                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':genre' => $genre
                ]);
                // :search, :author, and :genre are placeholders in the SQL query.

                //$search, $author, and $genre are the actual values we pass into the query.

                //execute([...]) binds the values to the placeholders and runs the query.

                //this executes the sql query


                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                //gets all the matching rows from the database
                echo json_encode($books);
                //y5arajlekk les livres trouvés en format json
            }
            // Get all available books
            //if the user didn't specify anything
            else {
                if (isset($_GET['user_id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE availability = 1 AND user_id <> 1");//i hard coded the uzser_iid to try it out
                    $stmt->execute([$_GET['user_id']]);
                } else {
                    $stmt = $pdo->query("SELECT * FROM livre WHERE availability = 1");
                }
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($books);
            }
            break;*/
            //we hardcoded into 1
            case 'GET':
                if (isset($_GET['own_books']) && $_GET['own_books'] === 'true') {
                    // Fetch the user's own books (for "My Library" page), hardcoded user_id = 1
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE user_id = ?");
                    $stmt->execute([1]); // Hardcoded user_id = 1
                    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $normalizedBooks = array_map('normalizeBook', $books);
                    echo json_encode($normalizedBooks);
                } 
                elseif (isset($_GET['book_id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE `book_id` = ?");
                    $stmt->execute([$_GET['book_id']]);
                    $book = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($book) {
                        $normalizedBook = normalizeBook($book);
                        echo json_encode($normalizedBook);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Book not found']);
                    }
                } 
                elseif (isset($_GET['title']) || isset($_GET['genre']) || isset($_GET['author'])) {
                    $title = isset($_GET['title']) ? "%{$_GET['title']}%" : "%";
                    $genre = isset($_GET['genre']) ? $_GET['genre'] : "%";
                    $author = isset($_GET['author']) ? "%{$_GET['author']}%" : "%";
        
                    $stmt = $pdo->prepare("
                        SELECT * FROM livre 
                        WHERE (title LIKE :title AND author_name LIKE :author
                        AND genre LIKE :genre
                        AND availability = 1)
                    ");
        
                    $stmt->execute([
                        ':title' => $title,
                        ':author' => $author,
                        ':genre' => $genre
                    ]);
        
                    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $normalizedBooks = array_map('normalizeBook', $books);
                    echo json_encode($normalizedBooks);
                } 
                else {
                    // Fetch available books, excluding user_id = 1 (for "Home" page)
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE availability = 1 AND user_id <> ?");
                    $stmt->execute([1]); // Hardcoded user_id = 1
                    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $normalizedBooks = array_map('normalizeBook', $books);
                    echo json_encode($normalizedBooks);
                }
                break;
        /*case 'POST':
            $requestData = json_decode(file_get_contents("php://input"), true);

            if (empty($requestData['title']) || empty($requestData['author_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and author name are required']);
                break;
            }
            
            

            // this is the old method : $user_id = $requestData['user_id'];
            $user_id = $current_user_id; // From JWT token
            $title = $requestData['title'];
            $author_name = $requestData['author_name'];

            // Check if user exists
            $userCheck = $pdo->prepare("SELECT 1 FROM user WHERE user_id = ?");
            $userCheck->execute([$user_id]);
            //=== :type+value
            //== :value
            
            if ($userCheck->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                break;
            }

            // Check for duplicate book
            $duplicateCheck = $pdo->prepare("
                SELECT 1 FROM livre
                WHERE title = ? 
                AND author_name = ? 
                AND user_id = ?
            ");
            $duplicateCheck->execute([$title, $author_name, $user_id]);
            
            if ($duplicateCheck->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'This user already has a book with the same title and author']);
                break;
            }
            
        
            // Insert new book into database
            $stmt = $pdo->prepare("
                INSERT INTO livre 
                (title, author_name, language, genre, release_date, status, dateAjout, availability, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
        
            $stmt->execute([
                $requestData['title'],
                $requestData['author_name'],
                $requestData['language'] ?? 'Unknown',
                $requestData['genre'] ?? 'Other',
                $requestData['release_date'] ?? null,
                $requestData['status'] ?? 'good',
                $requestData['availability'] ?? 'available',
                $user_id  // Changed from $requestData['user_id'] to use the JWT token user
            ]);
        
            // Return success response
            http_response_code(201);
            echo json_encode([
                'id' => $pdo->lastInsertId(),
                'message' => 'Book added successfully'
            ]);
            break;*/



            case 'POST':
                $requestData = json_decode(file_get_contents("php://input"), true);
    
                if (empty($requestData['title']) || empty($requestData['authorName'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Title and author name are required']);
                    break;
                }
    
                $title = $requestData['title'];
                $authorName = $requestData['authorName'];
    
                // Hardcode user_id to 1
                $userId = 1;
    
                $userCheck = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
                $userCheck->execute([$userId]);
    
                if ($userCheck->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    break;
                }
    
                $duplicateCheck = $pdo->prepare("
                    SELECT 1 FROM livre
                    WHERE title = ? 
                    AND author_name = ? 
                    AND user_id = ?
                ");
                $duplicateCheck->execute([$title, $authorName, $userId]);
    
                if ($duplicateCheck->rowCount() > 0) {
                    http_response_code(409);
                    echo json_encode(['error' => 'This user already has a book with the same title and author']);
                    break;
                }
    
                // Explicitly cast availability to boolean (1 or 0)
                $availability = isset($requestData['availability']) ? (int) $requestData['availability'] : 1;
                $availability = $availability ? 1 : 0;
    
                $stmt = $pdo->prepare("
                    INSERT INTO livre 
                    (title, author_name, language, genre, release_date, status, URL, dateAjout, availability, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");
    
                $stmt->execute([
                    $requestData['title'],
                    $requestData['authorName'],
                    $requestData['language'] ?? 'Unknown',
                    $requestData['genre'] ?? 'Other',
                    $requestData['releaseDate'] ?? null,
                    $requestData['status'] ?? 'good',
                    $requestData['coverImage'] ?? '',
                    $availability,
                    $userId // Hardcoded user_id = 1
                ]);
    
                $bookId = $pdo->lastInsertId();
    
                // Fetch the newly added book to return in the response
                $fetchStmt = $pdo->prepare("SELECT * FROM livre WHERE book_id = ?");
                $fetchStmt->execute([$bookId]);
                $newBook = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    
                http_response_code(201);
                echo json_encode([
                    'book' => normalizeBook($newBook),
                    'message' => 'Book added successfully'
                ]);
                break;
    









        

        case 'PUT':

            $requestData = sanitizeInput(json_decode(file_get_contents("php://input"), true));
            
            // Verify book ID exists
            if (empty($requestData['book_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }
        
            // Get current book data
            // First check if book exists AND belongs to current user
            // auth changes on requette
            $checkBook = $pdo->prepare("
                SELECT * 
                FROM livre 
                WHERE `book_id` = ? AND user_id = ?
            ");
            //auth changes here !!
            $checkBook->execute([$requestData['book_id'], $current_user_id]);

            // check if book exists before making any changes
            if ($checkBook->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found or not owned by you']);
                break;
            }


            $currentData = $checkBook->fetch(PDO::FETCH_ASSOC);
        
            //if (!$currentData) {
              //  http_response_code(404);
              //  echo json_encode(['error' => 'Book not found']);
              //  break;
            //}
        
            // Merge new data with existing data (preserve unchanged fields)
            $mergedData = array_merge($currentData, $requestData);
        
            // Prepare dynamic update query
            $updateFields = [];
            $params = [];
            
            // List of allowed fields to update
            $allowedFields = [
                'title', 
                'author_name', 
                'language', 
                'genre',
                'release_date', 
                'status', 
                'availability'
                // Removed user_id from allowed updates for security
            ];
        
            foreach ($allowedFields as $field) {
                if (isset($requestData[$field])) {
                    $updateFields[] = "`$field` = ?";
                    $params[] = $requestData[$field];
                }
            }
        
            // If no valid fields provided
            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields provided for update']);
                break;
            }
        
            // Add book_id to params
            $params[] = $requestData['book_id'];
        
            // Build and execute dynamic query
            $query = "UPDATE livre SET " . implode(', ', $updateFields) . " WHERE `book_id` = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
        
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Book updated successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'No changes made']);
            }
            break;






        case 'DELETE':
            // Verify book ID is not empty
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }

            //verify book belongs to current user
            $verifyOwnership = $pdo->prepare("
                SELECT 1 FROM livre 
                WHERE book_id = ? AND user_id = ?
            ");

            $verifyOwnership->execute([$_GET['id'], $current_user_id]);
    
            if ($verifyOwnership->rowCount() === 0) {
                // user is trying to delete someone else's books
                http_response_code(403);
                echo json_encode(['error' => 'You can only delete your own books']);
                break;
            }
            $bookId = $_GET['id'];
            
            // Option 1: Soft delete (update availability)
            
            
            // Option 2: Hard delete
             $stmt = $pdo->prepare("DELETE FROM livre WHERE book_id = ?");
             $stmt->execute([$bookId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Book removed successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    error_log("[".date('Y-m-d H:i:s')."] Database Error: ".$e->getMessage()."\n", 3, __DIR__."/../logs/error.log");
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        // Don't expose detailed errors in production
        'message' => (ENVIRONMENT === 'development') ? $e->getMessage() : 'Internal server error'
    ]);
}
/**
 * Normalize a book's data.
 *
 * @param array $book The book data to normalize.
 * @return array The normalized book data.
 */


?>
