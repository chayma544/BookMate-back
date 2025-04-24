<?php 
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
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE user_id = ?");
                $stmt->execute([$_GET['user_id']]);
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($books);
            } 
            elseif (isset($_GET['book_id'])) {
                $stmt = $pdo->prepare("SELECT * FROM livre WHERE `book_id` = ?");
                $stmt->execute([$_GET['book_id']]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($book) {
                    echo json_encode($book);
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
                echo json_encode($books);
            } 
            else {
                if (isset($_GET['user_id'])) {
                    $stmt = $pdo->prepare("SELECT * FROM livre WHERE availability = 1 AND user_id <> 1");
                    $stmt->execute([$_GET['user_id']]);
                } else {
                    $stmt = $pdo->query("SELECT * FROM livre WHERE availability = 1");
                }
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($books);
            }
            break;

        case 'POST':
            $requestData = json_decode(file_get_contents("php://input"), true);

            if (empty($requestData['title']) || empty($requestData['author_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and author name are required']);
                break;
            }

            $user_id = $requestData['user_id'];
            $title = $requestData['title'];
            $author_name = $requestData['author_name'];

            $userCheck = $pdo->prepare("SELECT 1 FROM user WHERE user_id = ?");
            $userCheck->execute([$user_id]);

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
            $duplicateCheck->execute([$title, $author_name, $user_id]);

            if ($duplicateCheck->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'This user already has a book with the same title and author']);
                break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO livre 
                (title, author_name, language, genre, release_date, status, URL, dateAjout, availability, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");

            $stmt->execute([
                $requestData['title'],
                $requestData['author_name'],
                $requestData['language'] ?? 'Unknown',
                $requestData['genre'] ?? 'Other',
                $requestData['release_date'] ?? null,
                $requestData['status'] ?? 'good',
                $requestData['URL'] ?? '',
                $requestData['availability'] ?? 1,
                $requestData['user_id'] 
            ]);

            http_response_code(201);
            echo json_encode([
                'id' => $pdo->lastInsertId(),
                'message' => 'Book added successfully'
            ]);
            break;

        case 'PUT':
            $requestData = json_decode(file_get_contents("php://input"), true);

            if (empty($requestData['book_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }

            $checkBook = $pdo->prepare("SELECT * FROM livre WHERE `book_id` = ?");
            $checkBook->execute([$requestData['book_id']]);
            $currentData = $checkBook->fetch(PDO::FETCH_ASSOC);

            if (!$currentData) {
                http_response_code(404);
                echo json_encode(['error' => 'Book not found']);
                break;
            }

            $mergedData = array_merge($currentData, $requestData);

            $updateFields = [];
            $params = [];

            $allowedFields = [
                'title', 
                'author_name', 
                'language', 
                'genre',
                'release_date', 
                'status', 
                'availability',
                'user_id',
                'URL'
            ];

            foreach ($allowedFields as $field) {
                if (isset($requestData[$field])) {
                    $updateFields[] = "`$field` = ?";
                    $params[] = $requestData[$field];
                }
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields provided for update']);
                break;
            }

            $params[] = $requestData['book_id'];

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
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Book ID is required']);
                break;
            }

            $bookId = $_GET['id'];

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
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>
