-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2025 at 04:29 PM
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
  `book_id` int(11) NOT NULL,
  `title` varchar(50) NOT NULL,
  `author_name` varchar(50) NOT NULL,
  `language` varchar(50) NOT NULL DEFAULT 'English',
  `genre` varchar(50) NOT NULL,
  `release_date` date DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'good',
  `dateAjout` date NOT NULL DEFAULT curdate(),
  `user_id` int(11) DEFAULT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `availability` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Boolean: 1=yes/available, 0=no/unavailable'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `livre`
--

INSERT INTO `livre` (`book_id`, `title`, `author_name`, `language`, `genre`, `release_date`, `status`, `dateAjout`, `user_id`, `URL`, `availability`) VALUES
(1, 'bhjhbk', 'bkjbb', 'Spanish', 'jnlnl', '0000-00-00', 'like new', '2025-05-07', 52, 'https://sarahjmaas.com/wp-content/uploads/2023/11/ACOTAR_1-min.jpg', 1),
(2, 'ukyuvukvu', 'hbibbiub', 'German', 'tragedy', '0000-00-00', 'like new', '2025-05-07', 52, 'https://images.epagine.fr/142/9782755673142_1_75.jpg', 1),
(3, 'jhbvuvvvuvu', 'bibibiub5554', 'Spanish', 'crime', '0000-00-00', 'like new', '2025-05-07', 52, 'https://images-na.ssl-images-amazon.com/images/S/compressed.photo.goodreads.com/books/1545494980i/40916679.jpg', 1),
(4, 'test', 'test948666', 'German', 'crime', '0000-00-00', 'like new', '2025-05-07', 52, 'https://sarahjmaas.com/wp-content/uploads/2023/11/ACOTAR_1-min.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `type` enum('BORROW','EXCHANGE') NOT NULL,
  `status` enum('PENDING','ACCEPTED','REJECTED') NOT NULL,
  `datedeb` date DEFAULT NULL,
  `durée` int(11) DEFAULT NULL,
  `reasonText` varchar(255) DEFAULT NULL
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
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `imageURL` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `FirstName`, `LastName`, `age`, `address`, `user_swap_score`, `email`, `password`, `imageURL`) VALUES
(2, 'feyra', 'archeron', 18, '123 main', 0, 'aaaaaaaaa@gmail.com', '$2y$10$m8gv0DzDGBxh1qhf3ptMiOQHAZ1Tq0FHaQLrI7rmBpk', 'default_profile.jpg'),
(3, 'Aaron', 'warner', 19, '123 main', 0, 'Aaronwarner@gmail.com', '$2y$10$SDQYWFQ1sA910MWQKOcDZ.GWsx74LIn.wPspzYn86QQ', 'default_profile.jpg'),
(51, 'nesta', 'archeron', 25, '123 main', 0, 'thewitch@gmail.com', '$2y$10$w1XHNuCMhiYxnuk.aQ/MP.wTXR6Db5koSzDpgLRjZVT', 'default_profile.jpg'),
(52, 'wa', 'a', 18, '123 main', 0, 'waa@gmail.com', '$2y$10$0tnCzZIbZttf3oYnHQK91ejVG.b5H.GJKdq2I1uHwbf', 'default_profile.jpg'),
(53, 'chayma', 'labiadh', 20, '123 main', 0, 'chaymalabiadh544@gmail.com', '$2y$10$TZKYSVjZ7EVQoVnsIG49nOnp0705/QFDpkIbTIAdtYV', 'default_profile.jpg');

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
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `requester_id` (`requester_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `livre`
--
ALTER TABLE `livre`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

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
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `livre` (`book_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
