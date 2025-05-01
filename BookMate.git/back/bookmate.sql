CREATE TABLE `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `age` int(11) NOT NULL DEFAULT 18,
  `address` varchar(50) NOT NULL,
  `user_swap_score` int(11) NOT NULL DEFAULT 0,
  `email` varchar(50) NOT NULL UNIQUE,
  `password` varchar(50) NOT NULL,
  `imageURL` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`)/* we need to add a role attribute and  */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/* esm taswira random  */
CREATE TABLE `livre` (
  `book_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `author_name` varchar(50) NOT NULL,
  `language` varchar(50) NOT NULL DEFAULT 'English',
  `genre` varchar(50) NOT NULL,
  `release_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'good',
  `dateAjout` date NOT NULL DEFAULT curdate(),
  `user_id` int(11) DEFAULT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `availability` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Boolean: 1=yes/available, 0=no/unavailable',
  PRIMARY KEY (`book_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `livre_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
  `requester_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `type` enum('BORROW','EXCHANGE') NOT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED') NOT NULL,
  `datedeb` date DEFAULT NULL,
  `durée` int(11) DEFAULT NULL,
  `reasonText` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  KEY `requester_id` (`requester_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `livre` (`book_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
