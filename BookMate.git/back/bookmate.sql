-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2025 at 07:08 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bookmate`
--

-- --------------------------------------------------------

--
-- Table structure for table `livre`
--

CREATE TABLE `livre` (
  `book_id` int(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `author_name` varchar(50) NOT NULL,
  `language` varchar(50) NOT NULL DEFAULT 'English',
  `genre` varchar(50) NOT NULL,
  `release_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'good',
  `dateAjout` date NOT NULL DEFAULT curdate(),
  `user_id` int(11) DEFAULT NULL,
  `URL` varchar(999) DEFAULT NULL,
  `availability` tinyint(1) DEFAULT NULL COMMENT 'Boolean: 1=yes/available, 0=no/unavailable'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `livre`
--

INSERT INTO `livre` (`book_id`, `title`, `author_name`, `language`, `genre`, `release_date`, `status`, `dateAjout`, `user_id`, `URL`, `availability`) VALUES
(1, 'The Great Gatsby', 'F. Scott Fitzgerald', 'English', 'Classic', NULL, 'good', '2025-04-03', 1, 'https://skyhorse-us.imgix.net/covers/9781949846386.jpg?auto=format&w=298', 1),
(3, 'pride and prejudice', 'jane austen', 'English', 'romance ', '0000-00-00', 'good', '0000-00-00', 1, 'https://m.media-amazon.com/images/M/MV5BMTA1NDQ3NTcyOTNeQTJeQWpwZ15BbWU3MDA0MzA4MzE@._V1_FMjpg_UX1000_.jpg', 1),
(50, 'Crime And Punishment', ' Fyodor Dostoevsky', 'English', 'philosophy', NULL, 'good', '0000-00-00', 1, 'https://www.aliceandbooks.com/covers/Crime_and_Punishment-Fyodor_Dostoevsky-lg.png', 0),
(71, 'Fourth Wing', 'Rebecca Yarros', 'English', 'fantasy', NULL, 'good', '0000-00-00', 1, 'https://images.epagine.fr/019/9780349437019_1_75.jpg', 0),
(72, 'A Court Of Thorns And Roses', 'Sarah J.Mass', 'English', 'fantasy', '0000-00-00', 'kinda old...', '2025-04-20', 1, 'https://sarahjmaas.com/wp-content/uploads/2023/11/ACOTAR_1-min.jpg', 0),
(190, 'الايام', 'طه حسين', 'arabe', 'Classic', NULL, 'good', '0000-00-00', 16, 'https://upload.wikimedia.org/wikipedia/ar/9/93/%D8%BA%D9%84%D8%A7%D9%81_%D9%83%D8%AA%D8%A7%D8%A8_%D8%A7%D9%84%D8%A3%D9%8A%D8%A7%D9%85.jpg', 1),
(1654, 'the alchemist', 'Paulo Cohelo', 'English', 'philosophy', NULL, 'good', '0000-00-00', 32, 'https://m.media-amazon.com/images/I/71zHDXu1TaL._SL1500_.jpg', 1),
(1655, 'Twisted Hate', 'Ana Huang', 'English', 'romance', '0000-00-00', 'like new', '2025-04-20', 1, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `type` enum('BORROW','EXCHANGE') DEFAULT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED') DEFAULT NULL,
  `datedeb` date DEFAULT NULL,
  `durée` int(11) DEFAULT NULL,
  `reasonText` varchar(999) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `age` int(11) NOT NULL DEFAULT 18,
  `address` varchar(50) NOT NULL,
  `user_swap_score` int(11) NOT NULL DEFAULT 0,
  `email` varchar(50) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `imageURL` varchar(999) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `FirstName`, `LastName`, `age`, `address`, `user_swap_score`, `email`, `password`, `imageURL`) VALUES
(1, 'John', 'Doe', 30, '123 Main St', 0, NULL, NULL, ''),
(2, 'Jane', 'Smith', 25, '456 Oak Ave', 0, NULL, NULL, ''),
(3, 'John', 'Doe', 25, '123 Main St', 0, NULL, NULL, ''),
(4, 'John', 'Doe', 25, '123 Main St', 0, NULL, NULL, ''),
(5, 'John', 'Doe', 25, '123 Main St', 0, NULL, NULL, ''),
(6, 'John', 'Doe', 25, '123 Main St', 0, NULL, NULL, ''),
(7, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(8, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(9, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(10, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(11, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(12, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(13, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(14, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(15, 'John', 'Doe', 25, '1234 Main St, SomeCity, Country', 0, NULL, NULL, ''),
(16, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(17, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(18, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(19, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(20, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(21, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(22, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(23, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(24, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(25, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(26, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(27, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(28, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(29, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(30, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(31, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(32, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(33, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(34, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(35, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(36, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(37, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, ''),
(38, 'aaaa', 'bbbbb', 25, '123 Main St', 0, NULL, NULL, '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `livre`
--
ALTER TABLE `livre`
  ADD PRIMARY KEY (`book_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD KEY `requests_ibfk_1` (`requester_id`),
  ADD KEY `requests_ibfk_2` (`book_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`,`password`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `livre`
--
ALTER TABLE `livre`
  MODIFY `book_id` int(50) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1656;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `livre`
--
ALTER TABLE `livre`
  ADD CONSTRAINT `livre_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `livre` (`book_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
